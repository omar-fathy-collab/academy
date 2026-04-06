<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RememberTokenEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_token_is_encrypted_on_set_and_decrypted_on_get()
    {
        $user = User::factory()->create();

        $user->remember_token = 'plain-token-123';
        $user->save();

        $rawFromDb = $user->getOriginal('remember_token');
        $this->assertNotEmpty($rawFromDb);
        $this->assertNotEquals('plain-token-123', $rawFromDb);

        // Decrypted via model accessor
        $this->assertEquals('plain-token-123', $user->remember_token);

        // Ensure Crypt can decrypt the db value
        $this->assertEquals('plain-token-123', Crypt::decryptString($rawFromDb));
    }
}
