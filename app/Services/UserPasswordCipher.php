<?php

namespace App\Services;

class UserPasswordCipher
{
    private static function key(): string
    {
        return hash('sha256', config('admin.superadmin_password'), true);
    }

    public static function encryptPassword(string $password): string
    {
        $cipher = 'AES-256-CBC';

        $iv = random_bytes(openssl_cipher_iv_length($cipher));

        $encrypted = openssl_encrypt(
            $password,
            $cipher,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        $mac = hash_hmac('sha256', $iv . $encrypted, self::key(), true);

        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted),
            'mac' => base64_encode($mac),
        ]));
    }

    public static function decryptPassword(string $payload): ?string
    {
        try {
            $json = json_decode(base64_decode($payload), true);

            if (!$json || !isset($json['iv'], $json['value'], $json['mac'])) {
                return null;
            }

            $iv = base64_decode($json['iv']);
            $encrypted = base64_decode($json['value']);
            $mac = base64_decode($json['mac']);

            $calcMac = hash_hmac('sha256', $iv . $encrypted, self::key(), true);

            if (!hash_equals($mac, $calcMac)) {
                return null;
            }

            return openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                self::key(),
                OPENSSL_RAW_DATA,
                $iv
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function check(string $plainPassword, string $encryptedPassword): bool
    {
        $decrypted = self::decryptPassword($encryptedPassword);

        if ($decrypted === null) {
            return false;
        }

        return hash_equals($decrypted, $plainPassword);
    }
}
