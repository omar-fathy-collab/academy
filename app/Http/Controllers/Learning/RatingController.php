<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Group;
use App\Models\Rating;
use App\Models\Subcourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    public function admin(Request $request)
    {
        // Check if admin
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        // Get groups with course/subcourse display name
        $groups = DB::table('groups')
            ->select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'subcourses.subcourse_name',
                DB::raw("CASE
                    WHEN subcourses.subcourse_name IS NOT NULL
                    THEN CONCAT(groups.group_name, ' (', COALESCE(courses.course_name, 'Unknown'), ' - ', subcourses.subcourse_name, ')')
                    ELSE CONCAT(groups.group_name, ' (', COALESCE(courses.course_name, 'Unknown'), ')')
                END as group_display_name")
            )
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->leftJoin('subcourses', 'groups.subcourse_id', '=', 'subcourses.subcourse_id')
            ->orderBy(DB::raw('COALESCE(courses.course_name, "Unknown")'))
            ->orderBy(DB::raw('COALESCE(subcourses.subcourse_name, "")'))
            ->orderBy('groups.group_name')
            ->get();

        // Get students
        $students = DB::table('students')
            ->select('student_id', 'student_name')
            ->orderBy('student_name')
            ->get();

        return view('ratings.admin.index', [
            'groups' => $groups,
            'students' => $students
        ]);
    }

    public function fetch(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = 15;
            $offset = ($page - 1) * $limit;

            $query = DB::table('ratings')
                ->select(
                    'ratings.rating_id',
                    'students.student_name',
                    'groups.group_name',
                    'courses.course_name',
                    'subcourses.subcourse_name',
                    'ratings.rating_value',
                    'ratings.comments',
                    'ratings.rated_at',
                    'ratings.rating_type',
                    'users.username as rated_by',
                    'sessions.topic as session_name',
                    DB::raw("CASE
                        WHEN subcourses.subcourse_name IS NOT NULL
                        THEN CONCAT(COALESCE(courses.course_name, 'Unknown'), ' - ', subcourses.subcourse_name)
                        ELSE COALESCE(courses.course_name, 'Unknown')
                    END as full_course_name")
                )
                ->leftJoin('students', 'ratings.student_id', '=', 'students.student_id')
                ->leftJoin('groups', 'ratings.group_id', '=', 'groups.group_id')
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('subcourses', 'groups.subcourse_id', '=', 'subcourses.subcourse_id')
                ->leftJoin('sessions', 'ratings.session_id', '=', 'sessions.session_id')
                ->leftJoin('users', 'ratings.rated_by', '=', 'users.id')
                ->orderBy('ratings.rated_at', 'desc');

            // Apply filters
            if ($request->has('group_id') && $request->group_id) {
                $query->where('ratings.group_id', $request->group_id);
            }

            if ($request->has('student_id') && $request->student_id) {
                $query->where('ratings.student_id', $request->student_id);
            }

            if ($request->has('type') && $request->type) {
                $query->where('ratings.rating_type', $request->type);
            }

            // Get total count
            $total = $query->count();

            // Apply pagination
            $ratings = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'ratings' => $ratings,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            \Log::error('Fetch ratings error: '.$e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
                'ratings' => [],
                'total' => 0,
                'page' => 1,
                'limit' => 15,
            ], 500);
        }
    }

    public function edit($id)
    {
        $rating = Rating::findOrFail($id);

        return response()->json([
            'success' => true,
            'rating' => $rating
        ]);
    }

    public function update(Request $request, $id)
    {
        // Check if admin
        if (auth()->user()->role_id != 1) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'rating_value' => 'required|numeric|min:0|max:5',
                'comments' => 'nullable|string',
                'rating_type' => 'required|in:assignment,session,monthly',
            ]);

            $rating = Rating::findOrFail($id);
            $data = $request->only(['rating_value', 'comments', 'rating_type']);
            $data['rating_value'] = (float) $data['rating_value'];
            $rating->update($data);

            return response()->json(['success' => true, 'message' => 'Rating updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Rating update failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Update failed: '.$e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        // Check if admin
        if (auth()->user()->role_id != 1) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $rating = Rating::findOrFail($id);
            $rating->delete();

            return response()->json(['success' => true, 'message' => 'Rating deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Delete failed'], 500);
        }
    }

    public function monthly(Request $request)
    {
        $groupId = $request->query('group_id');
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        $group = DB::table('groups')
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->where('groups.group_id', $groupId)
            ->select('groups.*', 'courses.course_name')
            ->first();

        if (! $group) {
            return redirect()->back()->with('error', 'Group not found.');
        }

        // Get students in the group
        $students = DB::table('students')
            ->join('student_group', 'students.student_id', '=', 'student_group.student_id')
            ->where('student_group.group_id', $groupId)
            ->select('students.*')
            ->orderBy('students.student_name')
            ->get();

        // Get existing monthly ratings for this month/year
        $existingRatings = DB::table('ratings')
            ->where('group_id', $groupId)
            ->where('rating_type', 'monthly')
            ->whereMonth('rated_at', $month)
            ->whereYear('rated_at', $year)
            ->pluck('rating_value', 'student_id')
            ->toArray();

        return view('ratings.teacher.monthly', [
            'group' => $group,
            'students' => $students,
            'existingRatings' => $existingRatings,
            'month' => (int) $month,
            'year' => (int) $year
        ]);
    }

    public function saveMonthlyRatings(Request $request)
    {
        $request->validate([
            'group_id' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'ratings' => 'nullable|array',
            'ratings.*' => 'numeric|min:0|max:5',
        ]);

        $groupId = $request->group_id;
        $month = $request->month;
        $year = $request->year;
        $ratings = $request->ratings ?? [];

        // Check if teacher owns this group
        $group = DB::table('groups')
            ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->where('groups.group_id', $groupId)
            ->first();
        if (! $group || auth()->user()->role_id == 2 && auth()->id() != $group->user_id) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        DB::beginTransaction();
        try {
            foreach ($ratings as $studentId => $rating) {
                if ($rating > 0) {
                    // Check if rating already exists
                    $existing = DB::table('ratings')
                        ->where('student_id', $studentId)
                        ->where('group_id', $groupId)
                        ->where('rating_type', 'monthly')
                        ->whereMonth('rated_at', $month)
                        ->whereYear('rated_at', $year)
                        ->first();

                    if ($existing) {
                        // Update existing
                        Rating::where('rating_id', $existing->rating_id)
                            ->update([
                                'rating_value' => $rating,
                                'rated_at' => now(),
                                'rated_by' => auth()->id(),
                            ]);
                    } else {
                        // Insert new
                        Rating::create([
                            'student_id' => $studentId,
                            'group_id' => $groupId,
                            'rating_type' => 'monthly',
                            'rating_value' => $rating,
                            'rated_at' => now(),
                            'rated_by' => auth()->id(),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Monthly ratings saved successfully!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Error saving ratings: '.$e->getMessage());
        }
    }
}
