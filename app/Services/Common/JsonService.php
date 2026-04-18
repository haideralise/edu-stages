<?php

// Ported from edu2/services/Common/JsonService.php (namespace only)

namespace App\Services\Common;

class JsonService
{
    public function encode_json($data)
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public function decode_json($json, $defVal = [])
    {
        $rt = $defVal;
        if (! empty($json)) {
            $json = trim($json, "\xEF\xBB\xBF");
            $json = trim($json, "\xFE\xFF");
            $rt = json_decode($json, true);
        }
        if ($rt == '' || is_null($rt)) {
            $rt = $defVal;
        }

        return $rt;
    }
}
