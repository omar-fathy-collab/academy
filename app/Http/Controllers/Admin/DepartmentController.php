<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role_id != 1) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }

                return redirect('/unauthorized');
            }

            return $next($request);
        });
    }

    /**
     * Display departments page
     */
    public function index()
    {
        try {
            $teachers = Teacher::select('teacher_id', 'teacher_name')
                ->orderBy('teacher_name')
                ->get();

            Log::info('Teachers loaded for department page', ['count' => count($teachers)]);

        } catch (\Exception $e) {
            Log::error('Error loading teachers for department page: '.$e->getMessage());
            $teachers = collect();
        }

        return view('departments.index', ['teachers' => $teachers]);
    }

    /**
     * Fetch departments for AJAX requests - FIXED METHOD NAME
     */
    public function fetch(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $searchTerm = $request->get('search', '');
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $query = Department::with(['headTeacher', 'teachers'])
            ->select([
                'department_id',
                'department_name',
                'description',
                'head_teacher_id',
            ]);

        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('department_name', 'LIKE', '%'.$searchTerm.'%')
                    ->orWhere('description', 'LIKE', '%'.$searchTerm.'%')
                    ->orWhereHas('headTeacher', function ($q) use ($searchTerm) {
                        $q->where('teacher_name', 'LIKE', '%'.$searchTerm.'%');
                    });
            });
        }

        $total = $query->count();

        $departments = $query->orderBy('department_id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($department) {
                return [
                    'department_id' => $department->department_id,
                    'department_name' => $department->department_name,
                    'description' => $department->description,
                    'head_teacher' => $department->headTeacher ? $department->headTeacher->teacher_name : 'N/A',
                    'head_teacher_id' => $department->head_teacher_id,
                    'course_count' => 0,
                    'teacher_count' => $department->teachers->count(),
                ];
            });

        return response()->json([
            'departments' => $departments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Get specific department data
     */
    public function get(Department $department): JsonResponse
    {
        return response()->json([
            'department' => [
                'department_id' => $department->department_id,
                'department_name' => $department->department_name,
                'description' => $department->description,
                'head_teacher_id' => $department->head_teacher_id,
                'head_teacher' => $department->headTeacher ? $department->headTeacher->teacher_name : null,
            ],
        ]);
    }

    /**
     * Get teachers for dropdown - FIXED METHOD NAME
     */
    public function getTeachers(): JsonResponse
    {
        try {
            Log::info('Fetching teachers for departments dropdown');

            $teachers = Teacher::select('teacher_id', 'teacher_name')
                ->orderBy('teacher_name')
                ->get()
                ->toArray();

            Log::info('Teachers fetched successfully', ['count' => count($teachers)]);

            return response()->json($teachers);

        } catch (\Exception $e) {
            Log::error('Error fetching teachers: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to load teachers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store new department
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'department_name' => 'required|string|max:45|unique:department,department_name',
            'description' => 'nullable|string|max:500',
            'head_teacher_id' => 'nullable|exists:teachers,teacher_id',
        ]);

        try {
            Department::create([
                'department_name' => $request->department_name,
                'description' => $request->description,
                'head_teacher_id' => $request->head_teacher_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department added successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding department: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error adding department: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update department
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'department_name' => 'required|string|max:45|unique:department,department_name,'.$department->department_id.',department_id',
            'description' => 'nullable|string',
            'head_teacher_id' => 'nullable|exists:teachers,teacher_id',
        ]);

        try {
            $department->update([
                'department_name' => $request->department_name,
                'description' => $request->description,
                'head_teacher_id' => $request->head_teacher_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating department: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete department
     */
    public function destroy(Department $department): JsonResponse
    {
        try {
            $teacherCount = $department->teachers()->count();

            if ($teacherCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with associated teachers',
                ], 400);
            }

            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting department: '.$e->getMessage(),
            ], 500);
        }
    }
}
