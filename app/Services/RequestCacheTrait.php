<?php

namespace App\Services;

/**
 * Request-scoped memoization (same pattern as edu2 RequestCacheTrait).
 */
trait RequestCacheTrait
{
    private static array $requestCache = [];

    protected function remember(string $key, callable $callback): mixed
    {
        $requestId = spl_object_hash($this);
        $fullKey = $requestId.':'.$key;

        if (array_key_exists($fullKey, self::$requestCache)) {
            return self::$requestCache[$fullKey];
        }

        return self::$requestCache[$fullKey] = $callback();
    }

    public static function clearRequestCache(): void
    {
        self::$requestCache = [];
    }
}
