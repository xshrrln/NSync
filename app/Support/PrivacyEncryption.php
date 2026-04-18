<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

class PrivacyEncryption
{
    public static function isEncrypted(?string $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function encryptStringIfNeeded(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        if ($stringValue === '' || self::isEncrypted($stringValue)) {
            return $stringValue;
        }

        return Crypt::encryptString($stringValue);
    }

    public static function encryptArrayIfNeeded(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString(json_encode(self::normalizeArrayValue($value), JSON_THROW_ON_ERROR));
    }

    public static function normalizeArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
