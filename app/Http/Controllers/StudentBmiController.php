<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBmiRequest;
use App\Http\Requests\UpdateBmiRequest;
use App\Models\EduBmi;
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

    public function create(): View
    {
        $this->authorize('create', EduBmi::class);

        return view('account.bmi_form', ['bmi' => null]);
    }

    public function store(StoreBmiRequest $request): RedirectResponse
    {
        $this->authorize('create', EduBmi::class);

        $bmi = EduBmi::create([
            'user_id' => $request->user()->ID,
            'date'    => $request->input('date'),
            'height'  => $request->input('height'),
            'weight'  => $request->input('weight'),
            'hc'      => $request->input('hc', 0),
            'bmi'     => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        return redirect()->route('account.mybmi')->with('success', 'BMI record added.');
    }

    public function edit(EduBmi $bmi): View
    {
        $this->authorize('update', $bmi);

        return view('account.bmi_form', compact('bmi'));
    }

    public function update(UpdateBmiRequest $request, EduBmi $bmi): RedirectResponse
    {
        $this->authorize('update', $bmi);

        $bmi->update([
            'date'   => $request->input('date'),
            'height' => $request->input('height'),
            'weight' => $request->input('weight'),
            'hc'     => $request->input('hc', 0),
            'bmi'    => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        return redirect()->route('account.mybmi')->with('success', 'BMI record updated.');
    }

    public function destroy(EduBmi $bmi): RedirectResponse
    {
        $this->authorize('delete', $bmi);

        $bmi->delete();

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
