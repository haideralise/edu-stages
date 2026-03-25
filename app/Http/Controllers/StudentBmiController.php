<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBmiRequest;
use App\Http\Requests\UpdateBmiRequest;
use App\Models\EduBmi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentBmiController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EduBmi::class);

        $records = EduBmi::forUser($request->user()->ID)
            ->orderByDesc('date')
            ->get()
            ->map(function ($bmi) {
                $bmi->category = $this->bmiCategory($bmi->bmi);
                return $bmi;
            });

        return view('account.mybmi', compact('records'));
    }

    public function show(EduBmi $bmi): JsonResponse
    {
        $this->authorize('update', $bmi);

        return response()->json([
            'id'             => $bmi->id,
            'height'         => $bmi->height,
            'weight'         => $bmi->weight,
            'hc'             => $bmi->hc,
            'date'           => $bmi->date,
            'date_formatted' => date('Y-m-d', $bmi->date),
            'bmi'            => $bmi->bmi,
        ]);
    }

    public function store(StoreBmiRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', EduBmi::class);

        $date = $request->input('date');
        if (! is_numeric($date)) {
            $date = strtotime($date);
        }

        $bmi = EduBmi::create([
            'user_id' => $request->user()->ID,
            'date'    => $date,
            'height'  => $request->input('height'),
            'weight'  => $request->input('weight'),
            'hc'      => $request->input('hc', 0),
            'bmi'     => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $bmi], 201);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record added.');
    }

    public function update(UpdateBmiRequest $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $bmi);

        $date = $request->input('date');
        if (! is_numeric($date)) {
            $date = strtotime($date);
        }

        $bmi->update([
            'date'   => $date,
            'height' => $request->input('height'),
            'weight' => $request->input('weight'),
            'hc'     => $request->input('hc', 0),
            'bmi'    => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $bmi]);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record updated.');
    }

    public function destroy(Request $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $bmi);

        $bmi->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record deleted.');
    }

    /**
     * Age-based BMI category (general child/teen thresholds).
     */
    private function bmiCategory(float $bmi): string
    {
        if ($bmi < 15) {
            return 'underweight';
        }
        if ($bmi < 23) {
            return 'normal';
        }
        if ($bmi < 27) {
            return 'overweight';
        }

        return 'obese';
    }
}
