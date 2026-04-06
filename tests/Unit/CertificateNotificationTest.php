<?php

namespace Tests\Unit;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_creation_sends_database_notification()
    {
        // Create a user (student)
        $user = User::factory()->create();

        // Create a certificate for that user
        $certificate = Certificate::create([
            'user_id' => $user->id,
            'certificate_type' => 'individual',
            'course_id' => null,
            'group_id' => null,
            'issued_by' => $user->id,
            'certificate_number' => 'TEST-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'issue_date' => now(),
            'status' => 'issued',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
        ]);
    }
}
