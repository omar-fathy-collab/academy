<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DecodeUuidRouteParameters
{
    /**
     * Map of route parameter keys to [table_name, primary_key_column].
     */
    protected $tableMap = [
        'session' => ['sessions', 'session_id'],
        'session_id' => ['sessions', 'session_id'],
        'user' => ['users', 'id'],
        'user_id' => ['users', 'id'],
        'student' => ['students', 'student_id'],
        'student_id' => ['students', 'student_id'],
        'teacher' => ['teachers', 'teacher_id'],
        'teacher_id' => ['teachers', 'teacher_id'],
        'parent' => ['parents', 'parent_id'],
        'parent_id' => ['parents', 'parent_id'],
        'group' => ['groups', 'group_id'],
        'group_id' => ['groups', 'group_id'],
        'video' => ['videos', 'id'],
        'video_id' => ['videos', 'id'],
        'material' => ['session_materials', 'id'],
        'material_id' => ['session_materials', 'id'],
        'quiz' => ['quizzes', 'quiz_id'],
        'quiz_id' => ['quizzes', 'quiz_id'],
        'attempt' => ['quiz_attempts', 'attempt_id'],
        'attempt_id' => ['quiz_attempts', 'attempt_id'],
        'invoice' => ['invoices', 'invoice_id'],
        'invoice_id' => ['invoices', 'invoice_id'],
        'payment' => ['payments', 'payment_id'],
        'payment_id' => ['payments', 'payment_id'],
        'booking' => ['bookings', 'id'],
        'booking_id' => ['bookings', 'id'],
        'assignment' => ['assignments', 'assignment_id'],
        'assignment_id' => ['assignments', 'assignment_id'],
        'course' => ['courses', 'course_id'],
        'course_id' => ['courses', 'course_id'],
        'subcourse' => ['subcourses', 'subcourse_id'],
        'subcourse_id' => ['subcourses', 'subcourse_id'],
        'notification' => ['notifications', 'notification_id'],
        'notification_id' => ['notifications', 'notification_id'],
        'activity' => ['activities', 'id'],
        'activity_id' => ['activities', 'id'],
        'room' => ['rooms', 'room_id'],
        'room_id' => ['rooms', 'room_id'],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $key => $value) {
                // Check if the parameter value is a UUID string
                if (is_string($value) && Str::isUuid($value)) {
                    // Try to resolve the original integer ID if we have a mapping
                    if (isset($this->tableMap[$key])) {
                        [$table, $pk] = $this->tableMap[$key];
                        $record = DB::table($table)->where('uuid', $value)->first([$pk]);
                        
                        if ($record) {
                            $route->setParameter($key, $record->$pk);
                        } else {
                            // If UUID is not found, we can optionally abort 404
                            // abort(404);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
