<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use App\Models\Student;
use App\Exports\StudentsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $filter = $request->get('filter', 'all');

        $query = Student::with(['user.profile'])
            ->withCount('groups');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'LIKE', "%{$search}%")
                    ->orWhere('student_id', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'LIKE', "%{$search}%")
                            ->orWhere('username', 'LIKE', "%{$search}%")
                            ->orWhereHas('profile', function ($pq) use ($search) {
                                $pq->where('phone_number', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        if ($filter === 'no_groups') {
            $query->doesntHave('groups');
        } elseif ($filter === 'no_active_groups') {
            $query->whereDoesntHave('groups', function ($gq) {
                $gq->where(function ($sq) {
                    $sq->whereNull('end_date')
                        ->orWhere('end_date', '>', now());
                });
            });
        }

        $students = $query->orderBy('student_name')->paginate(15)->withQueryString();

        return view('students.index', compact('students', 'filter', 'search'));
    }

    public function export()
    {
        return Excel::download(new StudentsExport, 'students_'.now()->format('Y-m-d').'.xlsx');
    }

    // في دالة fetch في StudentsController
    public function fetch(Request $request)
    {
        try {
            Log::info('Students fetch called with params: '.json_encode($request->all()));

            $page = max(1, intval($request->get('page', 1)));
            $searchTerm = trim($request->get('search', ''));
            $filter = $request->get('filter', 'all');
            $limit = 15;

            $query = Student::with(['user.profile'])
                ->withCount('groups');

            if (! empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('student_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('student_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhereHas('user', function ($uq) use ($searchTerm) {
                            $uq->where('email', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('username', 'LIKE', "%{$searchTerm}%")
                                ->orWhereHas('profile', function ($pq) use ($searchTerm) {
                                    $pq->where('phone_number', 'LIKE', "%{$searchTerm}%");
                                });
                        });
                });
            }

            // Filter logic
            if ($filter === 'no_groups') {
                $query->doesntHave('groups');
            } elseif ($filter === 'no_active_groups') {
                $query->whereDoesntHave('groups', function ($gq) {
                    $gq->where(function ($sq) {
                        $sq->whereNull('end_date')
                            ->orWhere('end_date', '>', now());
                    });
                });
            }

            $paginated = $query->orderBy('student_name')->paginate($limit);

            $students = collect($paginated->items())->map(function ($student) {
                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'enrollment_date' => $student->enrollment_date,
                    'email' => $student->user?->email ?? 'N/A',
                    'username' => $student->user?->username ?? 'N/A',
                    'is_active' => (bool) ($student->user?->is_active ?? false),
                    'phone_number' => $student->user?->profile?->phone_number ?? 'N/A',
                    'date_of_birth' => $student->user?->profile?->date_of_birth ?? null,
                    'group_count' => $student->groups_count,
                    'age' => $student->user?->profile?->date_of_birth ? \Carbon\Carbon::parse($student->user->profile->date_of_birth)->age : null,
                ];
            });

            return response()->json([
                'success' => true,
                'students' => $students,
                'total' => $paginated->total(),
                'page' => $paginated->currentPage(),
                'limit' => $paginated->perPage(),
                'totalPages' => $paginated->lastPage(),
            ], 200, [], JSON_NUMERIC_CHECK);

        } catch (\Exception $e) {
            Log::error('Error in students fetch: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Database error',
                'errorCode' => 500,
            ], 500);
        }
    }

    public function searchStudents(Request $request)
    {
        try {
            $searchTerm = trim($request->get('q', ''));

            $students = Student::with('user')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('student_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhereHas('user', function ($q) use ($searchTerm) {
                            $q->where('email', 'LIKE', "%{$searchTerm}%");
                        });
                })
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('student_name')
                ->limit(50)
                ->get(['student_id', 'student_name', 'user_id']);

            $results = $students->map(function ($student) {
                return [
                    'id' => $student->student_id,
                    'text' => $student->student_name,
                ];
            });

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in students search: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Search failed',
            ], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = trim($request->get('q', ''));

            $students = Student::with('user')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('student_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhereHas('user', function ($q) use ($searchTerm) {
                            $q->where('email', 'LIKE', "%{$searchTerm}%");
                        });
                })
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('student_name')
                ->limit(50)
                ->get(['student_id', 'student_name']);

            return response()->json([
                'success' => true,
                'students' => $students,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in students search: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Search failed',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,student_id',
            'student_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'enrollment_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $student = Student::with('user.profile')->findOrFail($request->student_id);

            // Update user
            if ($student->user) {
                $student->user->update([
                    'email' => $request->email,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                // Update profile
                if ($student->user->profile) {
                    $student->user->profile->update([
                        'nickname' => $request->student_name,
                    ]);
                }
            }

            // Update student
            $student->update([
                'student_name' => $request->student_name,
                'enrollment_date' => $request->enrollment_date,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'student_id' => $student->student_id,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating student: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to update student',
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:students,student_id',
        ]);

        try {
            DB::beginTransaction();

            $student = Student::with('user.profile')->findOrFail($request->id);
            $user = $student->user;

            // Delete relationships (using Eloquent to trigger events if any)
            $student->groups()->detach();
            $student->assignmentSubmissions()->delete();
            $student->attendances()->delete();
            $student->ratings()->delete();
            
            // Delete student
            $student->delete();

            // Delete user and profile
            if ($user) {
                if ($user->profile) {
                    $user->profile->delete();
                }
                $user->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully',
                'student_id' => $request->id,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting student: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete student',
            ], 500);
        }
    }

    public function addStudentToWaitingGroup(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,student_id',
            'waiting_group_id' => 'required|integer|exists:groups,group_id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $student = Student::findOrFail($request->student_id);
            
            // Fixed: Use syncWithoutDetaching for pivot tables to avoid duplicates and use Eloquent
            $student->groups()->syncWithoutDetaching([
                $request->waiting_group_id => [
                    'notes' => $request->notes,
                ]
            ]);

            return response()->json(['success' => true, 'message' => 'Student added to group']);
        } catch (\Exception $e) {
            Log::error('Error adding student to group: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }
}
