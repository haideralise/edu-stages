<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBmiRequest;
use App\Http\Requests\UpdateBmiRequest;
use App\Http\Resources\BmiResource;
use App\Models\EduBmi;
use App\Services\BmiQueryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentBmiController extends Controller
{
    use ApiResponse;

    public function index(Request $request, BmiQueryService $queryService): View
    {
        $this->authorize('viewAny', EduBmi::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        if ($isAdmin) {
            $records = $queryService->getAllRecords();
            $students = $queryService->getStudentList();
        } else {
            $records = $queryService->getRecordsForUser($user->ID);
            $students = collect();
        }

        return view('account.mybmi', compact('records', 'isAdmin', 'students'));
    }

    public function show(EduBmi $bmi): JsonResponse
    {
        $this->authorize('view', $bmi);

        return $this->success(new BmiResource($bmi));
    }

    public function store(StoreBmiRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', EduBmi::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        $targetUserId = $isAdmin && $request->input('user_id')
            ? (int) $request->input('user_id')
            : $user->ID;

        $bmi = EduBmi::create([
            'user_id' => $targetUserId,
            'date' => EduBmi::normalizeDate($request->input('date')),
            'height' => $request->input('height'),
            'weight' => $request->input('weight'),
            'hc' => $request->input('hc', 0),
            'bmi' => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return $this->success(new BmiResource($bmi))->setStatusCode(201);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record added.');
    }

    public function update(UpdateBmiRequest $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $bmi);

        $bmi->update([
            'date' => EduBmi::normalizeDate($request->input('date')),
            'height' => $request->input('height'),
            'weight' => $request->input('weight'),
            'hc' => $request->input('hc', 0),
            'bmi' => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return $this->success(new BmiResource($bmi));
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record updated.');
    }

    public function destroy(Request $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $bmi);

        $bmi->delete();

        if ($request->expectsJson()) {
            return $this->success(['message' => 'Deleted']);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record deleted.');
    }
}
