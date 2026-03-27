<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class PasswordCheck
{
    public static function verify(string $password, string $hash): bool
    {
        // Bcrypt
        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$')) {
            return Hash::check($password, $hash);
        }

        // WordPress phpass portable hash ($P$ or $H$)
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return self::verifyPhpass($password, $hash);
        }

        // Ancient MD5 fallback (pre-WP 2.5)
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals($hash, md5($password));
        }

        return false;
    }

    private static function verifyPhpass(string $password, string $stored): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        $countLog2 = strpos($itoa64, $stored[3]);
        $count = 1 << $countLog2;
        $salt = substr($stored, 4, 8);

        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        $encoded = substr($stored, 0, 12);
        $i = 0;
        $len = 16;

        do {
            $value = ord($hash[$i++]);
            $encoded .= $itoa64[$value & 0x3F];
            if ($i < $len) {
                $value |= ord($hash[$i]) << 8;
            }
            $encoded .= $itoa64[($value >> 6) & 0x3F];
            if ($i++ >= $len) {
                break;
            }
            if ($i < $len) {
                $value |= ord($hash[$i]) << 16;
            }
            $encoded .= $itoa64[($value >> 12) & 0x3F];
            if ($i++ >= $len) {
                break;
            }
            $encoded .= $itoa64[($value >> 18) & 0x3F];
        } while ($i < $len);

        return hash_equals($stored, $encoded);
    }
}
