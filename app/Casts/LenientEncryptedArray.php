<?php

namespace App\Casts;

use App\Support\PrivacyEncryption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class LenientEncryptedArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($value);
            $decoded = json_decode($decrypted, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            if ($this->looksLikeEncryptedPayload($value)) {
                return [];
            }

            // Fallback for legacy/plain JSON payloads.
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return PrivacyEncryption::encryptArrayIfNeeded($value);
    }

    private function looksLikeEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
