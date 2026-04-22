<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EduDistrictService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduDistrictController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduDistrictService $districtService)
    {
    }

    public function index()
    {
        return view('edu.class.district', [
            'list' => $this->districtService->getDistricts(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'district_name' => ['required', 'string', 'max:255'],
        ]);

        // TODO Stage 4+: Migrate to WooCommerce Term API if required
        $term = $this->districtService->store($request->input('district_name'));

        return $this->success(['term_id' => $term->term_id, 'name' => $term->name]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'district_name' => ['required', 'string', 'max:255'],
        ]);

        // TODO Stage 4+: Migrate to WooCommerce Term API if required
        $term = $this->districtService->update($id, $request->input('district_name'));

        return $this->success(['term_id' => $term->term_id, 'name' => $term->name]);
    }
}
