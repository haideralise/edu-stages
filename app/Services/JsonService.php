<?php

// Ported from edu2/services/Common/JsonService.php (namespace only)

namespace App\Services;

class JsonService
{
    public static function encode_json($data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function decode_json($json, $defVal = [])
    {
        $rt = $defVal;

        if (!empty($json)) {
            // Remove BOM
            $json = trim($json, "\xEF\xBB\xBF");
            $json = trim($json, "\xFE\xFF");

            $decoded = json_decode($json, true);

            if (!is_null($decoded) && $decoded !== '') {
                $rt = $decoded;
            }
        }

        return $rt;
    }
}
