<?php

namespace App\Support\Security;

class DataMasker
{
    /**
     * Mask sensitive fields in an array or string.
     */
    public static function mask(mixed $data): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if (self::isSensitiveKey($key)) {
                    $value = self::applyMask($value, (string)$key);
                } elseif (is_array($value)) {
                    $value = self::mask($value);
                }
            }
            return $data;
        }

        return $data;
    }

    /**
     * Determine if a key is sensitive.
     */
    private static function isSensitiveKey(string $key): bool
    {
        $sensitive = [
            'password', 'token', 'secret', 'key', 'cvv', 'card', 
            'phone', 'email', 'address', 'ssn', 'national_id', 'authorization'
        ];

        foreach ($sensitive as $term) {
            if (str_contains(strtolower($key), $term)) return true;
        }

        return false;
    }

    /**
     * Apply masking logic.
     */
    private static function applyMask(mixed $value, string $key): string
    {
        if (!is_string($value)) return '****';
        
        $keyLower = strtolower($key);
        $fullRedact = ['password', 'secret', 'token', 'cvv', 'card', 'authorization'];

        foreach ($fullRedact as $term) {
            if (str_contains($keyLower, $term)) return '****';
        }

        $len = strlen($value);
        if ($len <= 4) return '****';

        return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
    }
}
