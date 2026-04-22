<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EduCoachService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduCoachController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduCoachService $coachService)
    {
    }

    public function index()
    {
        $data = $this->coachService->getCoachesData();

        return view('edu.coach.coach', [
            'users' => $data['users'],
            'hourly_wage' => $data['hourly_wage'],
        ]);
    }

    public function updateWage(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'hourly_wage' => ['required', 'numeric', 'min:0'],
        ]);

        $result = $this->coachService->updateWage(
            (int)$request->input('user_id'),
            (float)$request->input('hourly_wage')
        );

        if (!$result['status']) {
            return $this->error($result['message'], 'UPDATE_FAILED', 422);
        }

        return $this->success(['message' => $result['message']]);
    }

    public function privateClasses(Request $request)
    {
        $coachId = (int)$request->get('coach_id', 0);

        $data = $this->coachService->getCoachesData();
        $allCoaches = $data['all_coaches'];

        $coach = collect($allCoaches)->firstWhere('ID', $coachId);
        $coachName = $coach['display_name'] ?? '未知教練';

        $bookings = $coachId > 0
            ? $this->coachService->getPrivateClasses($coachId)
            : [];

        return view('edu.coach.private_classes', [
            'coach_id' => $coachId,
            'coach_name' => $coachName,
            'all_coaches' => $allCoaches,
            'bookings_from_db' => $bookings,
        ]);
    }

    public function savePrivateClasses(Request $request): JsonResponse
    {
        $request->validate([
            'coach_id' => ['required', 'integer', 'min:1'],
            'bookings' => ['required', 'array'],
        ]);

        $result = $this->coachService->savePrivateClasses(
            (int)$request->input('coach_id'),
            $request->input('bookings', [])
        );

        if (!$result['status']) {
            return $this->error($result['message'], 'SAVE_FAILED', 422);
        }

        return $this->success(['message' => $result['message']]);
    }
}
