<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    /**
     * Check if the attribute should be encrypted.
     */
    protected function isEncryptable(string $key): bool
    {
        return property_exists($this, 'encryptable') && is_array($this->encryptable) && in_array($key, $this->encryptable, true);
    }

    /**
     * Encrypt values before saving to database.
     */
    public function setAttribute($key, $value)
    {
        if ($this->isEncryptable($key) && ! is_null($value) && $value !== '') {
            try {
                // Only encrypt plain values. If encryption fails we'll store the raw value to avoid data loss.
                $value = Crypt::encryptString((string) $value);
            } catch (\Throwable $e) {
                // swallow - leave $value as-is
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Decrypt values when retrieved from database.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($this->isEncryptable($key) && ! is_null($value) && $value !== '') {
            try {
                return Crypt::decryptString((string) $value);
            } catch (\Throwable $e) {
                // Not encrypted or tampered with, return raw
                return $value;
            }
        }

        return $value;
    }
}
