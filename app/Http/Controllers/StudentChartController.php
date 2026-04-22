<?php

namespace App\Http\Controllers;

use App\Models\EduBmi;
use App\Models\EduResult;
use App\Models\WpUser;
use App\Support\BmiForAge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentChartController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('chart2.viewAny');

        $user = $request->user();
        $role = $user->resolveRole();

        $students = null;

        if ($role === 'admin') {
            $studentIds = EduBmi::distinct()->pluck('user_id');
            $students = WpUser::whereIn('ID', $studentIds)->get();
        }

        return view('account.chart2', [
            'user' => $user,
            'isAdmin' => $role === 'admin',
            'students' => $students,
        ]);
    }

    public function chartData(Request $request): JsonResponse
    {
        $this->authorize('chart2.viewAny');

        $user = $request->user();
        $role = $user->resolveRole();
        $type = $request->input('type', 'bmi');

        if ($role === 'admin' && $request->filled('user_id')) {
            $target = WpUser::with('meta')->findOrFail($request->integer('user_id'));
        } else {
            $target = $user->loadMissing('meta');
        }

        if ($type === 'result') {
            return $this->resultChart($target);
        }

        return $this->bmiChart($target, $type);
    }

    private function bmiChart(WpUser $target, string $type): JsonResponse
    {
        $birthdate = $target->birthdate;
        $gender = $target->gender;

        $records = EduBmi::where('user_id', $target->ID)->orderBy('date')->get();

        $studentSeries = $records->map(function (EduBmi $r) use ($type, $birthdate) {
            $y = match ($type) {
                'height' => $r->height,
                'weight' => $r->weight,
                'hc' => $r->hc,
                default => $r->bmi,
            };

            $months = $birthdate ? BmiForAge::calculateAgeMonths($birthdate, $r->date) : 0;
            $x = $months <= 24 ? round($months / 24 * 100, 2) : round($months / 216 * 100, 2);

            return [$x, $y];
        })->values()->all();

        $labels = match ($type) {
            'height' => ['x' => 'Age (months)', 'y' => 'Height (cm)'],
            'weight' => ['x' => 'Age (months)', 'y' => 'Weight (kg)'],
            'hc' => ['x' => 'Age (months)', 'y' => 'Head Circumference (cm)'],
            default => ['x' => 'Age (months)', 'y' => 'BMI (kg/m²)'],
        };

        $series = ['student' => $studentSeries];

        if ($type === 'bmi' && $gender) {
            $table = config('bmi.percentiles.' . strtolower($gender), []);
            $p5 = $p85 = $p95 = [];
            foreach ($table as $ageMonths => $thresholds) {
                $x = round($ageMonths / 216 * 100, 2);
                $p5[] = [$x, $thresholds['p5']];
                $p85[] = [$x, $thresholds['p85']];
                $p95[] = [$x, $thresholds['p95']];
            }
            $series += ['p5' => $p5, 'p85' => $p85, 'p95' => $p95];
        }

        return response()->json(['data' => [
            'datasets' => $studentSeries,
            'labels' => $labels,
            'series' => $series,
        ]]);
    }

    private function resultChart(WpUser $target): JsonResponse
    {
        $results = EduResult::where('user_id', $target->ID)->orderBy('exam_date')->get();

        $studentSeries = $results->map(fn (EduResult $r) => [
            $r->exam_date,
            (float) $r->exam_data,
        ])->values()->all();

        return response()->json(['data' => [
            'datasets' => $studentSeries,
            'labels' => ['x' => 'Date', 'y' => 'Score'],
            'series' => ['student' => $studentSeries],
        ]]);
    }
}
