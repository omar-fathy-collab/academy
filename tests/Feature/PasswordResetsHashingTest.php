<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetsHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_existing_command_detects_plain_tokens()
    {
        $table = config('auth.passwords.'.config('auth.defaults.passwords').'.table');

        // Insert a fake plain token row
        $email = 'test@example.com';
        $token = 'plain-reset-token-abc';
        DB::table($table)->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);

        // Dry run should report but not change
        $exit = Artisan::call('passwordresets:hash-existing --dry');
        $this->assertEquals(0, $exit);

        $row = DB::table($table)->where('email', $email)->first();
        $this->assertNotNull($row);
        $this->assertEquals($token, $row->token);

        // Now run actual command
        $exit = Artisan::call('passwordresets:hash-existing');
        $this->assertEquals(0, $exit);

        $row = DB::table($table)->where('email', $email)->first();
        $this->assertNotNull($row);
        $this->assertNotEquals($token, $row->token);
        $this->assertTrue(Hash::check($token, $row->token));
    }
}
