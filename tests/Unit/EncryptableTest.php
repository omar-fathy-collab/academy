<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptableTest extends TestCase
{
    public function test_encryptable_trait_encrypts_and_decrypts()
    {
        $user = new User;

        $raw = 'my-remember-token-123';

        // Use setAttribute to trigger the trait
        $user->setAttribute('remember_token', $raw);

        // The stored raw attribute (original) should be encrypted (not equal to raw)
        $stored = $user->getAttributes()['remember_token'] ?? null;

        $this->assertNotNull($stored, 'remember_token should be set on attributes');
        $this->assertNotEquals($raw, $stored, 'Stored value should not equal the raw token (should be encrypted)');

        // getAttribute should return the decrypted value
        $this->assertEquals($raw, $user->getAttribute('remember_token'));

        // And Crypt::decryptString on stored value should equal raw
        $this->assertEquals($raw, Crypt::decryptString($stored));
    }
}
