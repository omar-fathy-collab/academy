<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Session;
use App\Models\SessionMaterial;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_recent_materials_and_can_download()
    {
        // Use fake storage
        Storage::fake('public');

        // Create a student user
        $user = User::factory()->create(['role_id' => 3]);

        // Create student record
        $student = Student::create(['user_id' => $user->id, 'student_name' => 'Test Student']);

        // Create a group and session
        $group = Group::factory()->create();
        $session = Session::factory()->create(['group_id' => $group->group_id]);

        // Attach student to group via pivot
        \DB::table('student_group')->insert(['student_id' => $student->student_id, 'group_id' => $group->group_id]);

        // Put a fake file
        Storage::disk('public')->put('session_materials/test.txt', 'hello world');

        // Create material record
        $material = SessionMaterial::create([
            'session_id' => $session->session_id,
            'uploaded_by' => $user->id,
            'original_name' => 'test.txt',
            'file_path' => 'session_materials/test.txt',
            'mime_type' => 'text/plain',
            'size' => 11,
        ]);

        // Act as student and visit dashboard
        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Recent Materials')
            ->assertSee('test.txt');

        // Attempt to download
        $response = $this->actingAs($user)->get(route('sessions.materials.download', ['id' => $material->id]));
        $response->assertStatus(200);
    }
}
