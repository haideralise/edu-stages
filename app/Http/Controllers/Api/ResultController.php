<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListResultsRequest;
use App\Http\Resources\ResultResource;
use App\Models\EduClassUser;
use App\Models\EduLevel;
use App\Models\EduResult;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ResultController extends Controller
{
    use ApiResponse;

    public function index(ListResultsRequest $request): JsonResponse
    {
        $this->authorize('result.listApi');

        $user = $request->user();
        $role = $user->resolveRole();

        $query = EduResult::orderByDesc('exam_date');

        if ($role === 'admin') {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }
        } elseif ($role === 'coach') {
            $studentIds = EduClassUser::studentIdsForTeacher($user->ID);
            if ($request->filled('user_id')) {
                $targetId = $request->integer('user_id');
                if (! $studentIds->contains($targetId)) {
                    throw new AccessDeniedHttpException;
                }
                $query->where('user_id', $targetId);
            } else {
                $query->whereIn('user_id', $studentIds->all());
            }
        } else {
            if ($request->filled('user_id') && $request->integer('user_id') !== $user->ID) {
                throw new AccessDeniedHttpException;
            }
            $query->where('user_id', $user->ID);
        }

        $results = $query->get();
        $levels = EduLevel::getTree();

        return $this->success([
            'results' => ResultResource::collection($results),
            'levels' => $levels,
        ]);
    }
}
