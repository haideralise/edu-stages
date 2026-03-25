<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Scheme D response helpers (doc 09).
 *
 * Success  → { data, meta }
 * Paginated → { data, meta, links }
 * Error   → { message, code }
 */
trait ApiResponse
{
    protected function success(mixed $data, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(['timestamp' => time()], $meta),
        ]);
    }

    protected function paginated(LengthAwarePaginator $paginator, mixed $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'timestamp'    => time(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    protected function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code'    => $code,
        ], $status);
    }
}
