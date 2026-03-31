<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListBmiRequest;
use App\Http\Resources\BmiResource;
use App\Models\EduBmi;
use App\Models\EduClassUser;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BmiController extends Controller
{
    use ApiResponse;

    public function index(ListBmiRequest $request): JsonResponse
    {
        $this->authorize('bmi.listApi');

        $user = $request->user();
        $role = $user->resolveRole();

        $query = EduBmi::with('user.meta')->orderByDesc('date');

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

        $records = $query->get();

        return $this->success(BmiResource::collection($records));
    }
}
