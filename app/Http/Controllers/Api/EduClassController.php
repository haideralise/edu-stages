<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListClassesRequest;
use App\Http\Resources\EduClassResource;
use App\Models\EduClass;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class EduClassController extends Controller
{
    use ApiResponse;

    public function index(ListClassesRequest $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);

        $classes = EduClass::query()
            ->forDistrict($request->integer('district_id') ?: null)
            ->forYear($request->input('class_year'))
            ->orderByDesc('class_id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginated(
            $classes,
            EduClassResource::collection($classes->items()),
        );
    }
}
