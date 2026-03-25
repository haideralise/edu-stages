<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBmiRequest;
use App\Http\Requests\UpdateBmiRequest;
use App\Models\EduBmi;
use App\Models\WpUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentBmiController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EduBmi::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        if ($isAdmin) {
            // Admin sees all BMI records, grouped with student name
            $records = EduBmi::with('user')
                ->orderByDesc('date')
                ->get()
                ->map(function ($bmi) {
                    $bmi->category = $this->bmiCategory($bmi->bmi);
                    $bmi->student_name = $bmi->user?->display_name ?? "Student #{$bmi->user_id}";
                    return $bmi;
                });

            // Get only students for the dropdown (exclude admins and coaches)
            $coachIds = \App\Models\EduClassUser::all()
                ->flatMap(fn ($cu) => $cu->teacher ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique();

            $adminIds = \App\Models\WpUserMeta::where('meta_key', 'wp_3x_capabilities')
                ->where('meta_value', 'like', '%administrator%')
                ->pluck('user_id');

            $excludeIds = $coachIds->merge($adminIds)->unique()->values()->all();

            $students = WpUser::whereNotIn('ID', $excludeIds)
                ->orderBy('display_name')
                ->get();

            // Prepend admin themselves so they can add own records
            $students->prepend($user);
        } else {
            // Student sees own records only
            $records = EduBmi::forUser($user->ID)
                ->orderByDesc('date')
                ->get()
                ->map(function ($bmi) {
                    $bmi->category = $this->bmiCategory($bmi->bmi);
                    return $bmi;
                });

            $students = collect();
        }

        return view('account.mybmi', compact('records', 'isAdmin', 'students'));
    }

    public function show(EduBmi $bmi): JsonResponse
    {
        $this->authorize('update', $bmi);

        return response()->json([
            'id'             => $bmi->id,
            'user_id'        => $bmi->user_id,
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

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        // Admin can specify user_id; student always uses own ID
        $targetUserId = $isAdmin && $request->input('user_id')
            ? (int) $request->input('user_id')
            : $user->ID;

        $date = $request->input('date');
        if (! is_numeric($date)) {
            $date = strtotime($date);
        }

        $bmi = EduBmi::create([
            'user_id' => $targetUserId,
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
     * BMI category using standard thresholds (matches original edu2 system).
     *
     * TODO: Replace with age-derived percentile thresholds from HK-2020-StandardTables
     * when billing_birthdate data and reference tables are available.
     */
    private function bmiCategory(float $bmi): string
    {
        if ($bmi < 18.5) {
            return 'underweight';
        }
        if ($bmi < 25) {
            return 'normal';
        }
        if ($bmi < 30) {
            return 'overweight';
        }

        return 'obese';
    }
}
