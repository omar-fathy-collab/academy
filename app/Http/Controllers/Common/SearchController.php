<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;

class SearchController extends Controller
{
    public function globalSearch(Request $request)
    {
        $query = $request->input('query');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Search Students
        $students = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('students.student_name', 'LIKE', "%{$query}%")
            ->orWhere('users.username', 'LIKE', "%{$query}%")
            ->orWhere('users.email', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['students.student_id', 'students.student_name', 'users.username', 'users.email'])
            ->map(function($item) {
                return [
                    'id' => $item->student_id,
                    'title' => $item->student_name ?: $item->username,
                    'subtitle' => $item->email,
                    'type' => 'student',
                    'url' => route('student.info.show', ['id' => $item->student_id])
                ];
            });
        
        foreach ($students as $s) $results[] = $s;

        // Search Teachers
        $teachers = DB::table('teachers')
            ->join('users', 'teachers.user_id', '=', 'users.id')
            ->where('teachers.teacher_name', 'LIKE', "%{$query}%")
            ->orWhere('users.username', 'LIKE', "%{$query}%")
            ->orWhere('users.email', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['teachers.teacher_id', 'teachers.teacher_name', 'users.username', 'users.email'])
            ->map(function($item) {
                return [
                    'id' => $item->teacher_id,
                    'title' => $item->teacher_name ?: $item->username,
                    'subtitle' => $item->email,
                    'type' => 'teacher',
                    'url' => route('teachers.edit', ['teacher' => $item->teacher_id])
                ];
            });

        foreach ($teachers as $t) $results[] = $t;

        // Search Courses
        $courses = DB::table('courses')
            ->where('course_name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['course_id', 'course_name'])
            ->map(function($item) {
                return [
                    'id' => $item->course_id,
                    'title' => $item->course_name,
                    'subtitle' => 'Course',
                    'type' => 'course',
                    'url' => route('courses.index', ['search' => $item->course_name])
                ];
            });

        foreach ($courses as $c) $results[] = $c;
        
        // Search Activities
        $activities = DB::table('activities')
            ->where('action', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'action', 'description'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'title' => ucfirst($item->action),
                    'subtitle' => $item->description,
                    'type' => 'activity',
                    'url' => route('activities.index', ['search' => $item->action])
                ];
            });

        foreach ($activities as $a) $results[] = $a;

        return response()->json($results);
    }
}
