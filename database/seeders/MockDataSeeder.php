<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Profile;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Course;
use App\Models\SubCourse;
use App\Models\Group;
use App\Models\Session;
use App\Models\Quiz;
use App\Models\Department;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class MockDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        
        // 1. Ensure Roles exist (Handled by RolesTableSeeder/RolesAndPermissionsSeeder)
        
        // 2. Ensure Departments exist
        if (Department::count() == 0) {
            $this->call(DepartmentSeeder::class);
        }
        $departments = Department::all();
        if ($departments->isEmpty()) {
            DB::table('department')->insert([
                ['department_name' => 'General Sciences', 'description' => 'General Science Dept', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $departments = Department::all();
        }

        // 3. Create Teachers (10)
        $teacherRole = 2;
        $teachers = [];
        for ($i = 1; $i <= 10; $i++) {
            $username = 'teacher_' . Str::random(5);
            $user = User::create([
                'username' => $username,
                'email' => $username . '@academy.com',
                'pass' => Hash::make('password123'),
                'role_id' => $teacherRole,
                'is_active' => true,
            ]);

            Profile::create([
                'user_id' => $user->id,
                'nickname' => $faker->name,
                'phone_number' => $faker->phoneNumber,
                'address' => $faker->address,
            ]);

            $teachers[] = Teacher::create([
                'user_id' => $user->id,
                'teacher_name' => $user->name,
                'department_id' => $departments->random()->department_id,
                'hire_date' => now()->subMonths(rand(1, 24)),
                'base_salary' => rand(5000, 15000),
                'salary_percentage' => rand(10, 30),
            ]);
        }

        // 4. Create Courses (5)
        $courseNames = ['Web Development', 'Mobile Apps', 'Cybersecurity', 'Data Science', 'UI/UX Design'];
        $courseModels = [];
        foreach ($courseNames as $name) {
            $course = Course::create([
                'course_name' => $name,
                'description' => $faker->paragraph,
            ]);
            $courseModels[] = $course;

            // Create Subcourses (2 per course)
            for ($j = 1; $j <= 2; $j++) {
                SubCourse::create([
                    'course_id' => $course->course_id,
                    'subcourse_name' => $name . ' Level ' . $j,
                    'subcourse_number' => $j,
                    'description' => 'Advanced topics for ' . $name . ' - Volume ' . $j,
                    'duration_hours' => 20,
                ]);
            }
        }

        $allSubcourses = SubCourse::all();
        $teachersCollection = collect($teachers);

        // 5. Create Groups (8)
        $groups = [];
        for ($k = 1; $k <= 8; $k++) {
            $subcourse = $allSubcourses->random();
            $group = Group::create([
                'group_name' => 'Group ' . $k . ' (' . $subcourse->subcourse_name . ')',
                'course_id' => $subcourse->course_id,
                'subcourse_id' => $subcourse->subcourse_id,
                'teacher_id' => $teachersCollection->random()->teacher_id,
                'schedule' => $faker->randomElement(['Sun/Tue 6PM', 'Mon/Wed 4PM', 'Sat/Thu 10AM']),
                'start_date' => now()->subWeeks(rand(1, 4)),
                'price' => rand(1000, 3000),
                'teacher_percentage' => rand(15, 25),
            ]);
            $groups[] = $group;

            // Create some Sessions for this group
            for ($s = 1; $s <= 4; $s++) {
                $session = Session::create([
                    'group_id' => $group->group_id,
                    'session_date' => now()->addDays($s * 3),
                    'start_time' => '18:00',
                    'end_time' => '21:00',
                    'topic' => 'Module ' . $s . ': Fundamentals',
                    'notes' => $faker->sentence,
                    'created_by' => 1,
                ]);

                // Create a Quiz for the first session
                if ($s == 1) {
                    Quiz::create([
                        'session_id' => $session->session_id,
                        'title' => 'Initial Assessment - ' . $group->group_name,
                        'description' => 'Test basic knowledge',
                        'time_limit' => 45,
                        'max_attempts' => 2,
                        'is_active' => true,
                        'is_public' => true,
                        'created_by' => 1,
                    ]);
                }
            }
        }

        // 6. Create Students (50)
        $studentRole = 3;
        $groupsCollection = collect($groups);
        for ($m = 1; $m <= 50; $m++) {
            $username = 'student_' . Str::random(5);
            $user = User::create([
                'username' => $username,
                'email' => $username . '@academy.com',
                'pass' => Hash::make('password123'),
                'role_id' => $studentRole,
                'is_active' => true,
            ]);

            Profile::create([
                'user_id' => $user->id,
                'nickname' => $faker->name,
                'phone_number' => $faker->phoneNumber,
                'address' => $faker->address,
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'student_name' => $user->name,
                'enrollment_date' => now(),
            ]);

            // Enroll in 1 random group
            $groupToJoin = $groupsCollection->random();
            $student->groups()->attach($groupToJoin->group_id, [
                'enrollment_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
