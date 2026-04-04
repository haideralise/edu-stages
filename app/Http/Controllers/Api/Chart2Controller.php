<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chart2Request;
use App\Models\EduBmi;
use App\Models\EduResult;
use App\Models\WpUser;
use App\Support\BmiForAge;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Chart2Controller extends Controller
{
    use ApiResponse;

    public function index(Chart2Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->resolveRole();
        $type = $request->input('type', 'bmi');

        // Resolve target user: Auth::id() for students, optional user_id for admin
        if ($role === 'admin' && $request->filled('user_id')) {
            $target = WpUser::with('meta')->findOrFail($request->integer('user_id'));
        } elseif ($role === 'admin' || $role === 'student') {
            $target = $user->loadMissing('meta');
        } else {
            throw new AccessDeniedHttpException;
        }

        // Students can only view own chart
        if ($role === 'student' && $request->filled('user_id') && $request->integer('user_id') !== $user->ID) {
            throw new AccessDeniedHttpException;
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

            $x = $this->ageToX($birthdate, $r->date);

            return [$x, $y];
        })->values()->all();

        $labels = $this->labelsForType($type);

        $series = ['student' => $studentSeries];

        if ($type === 'bmi' && $gender) {
            $series = array_merge($series, $this->referenceCurves($gender));
        }

        return $this->success([
            'datasets' => $studentSeries,
            'labels' => $labels,
            'series' => $series,
            'meta' => [
                'gender' => $gender,
                'birthdate' => $birthdate,
            ],
        ]);
    }

    private function resultChart(WpUser $target): JsonResponse
    {
        $results = EduResult::where('user_id', $target->ID)->orderBy('exam_date')->get();

        $studentSeries = $results->map(fn (EduResult $r) => [
            $r->exam_date,
            (float) $r->exam_data,
        ])->values()->all();

        return $this->success([
            'datasets' => $studentSeries,
            'labels' => ['x' => 'Date', 'y' => 'Score'],
            'series' => ['student' => $studentSeries],
        ]);
    }

    /**
     * Convert birthdate + record date to x-axis percentage.
     * <=24 months: m/24*100; >24 months: m/216*100
     */
    private function ageToX(?string $birthdate, int $recordDate): float
    {
        if (! $birthdate) {
            return 0;
        }

        $months = BmiForAge::calculateAgeMonths($birthdate, $recordDate);

        if ($months <= 24) {
            return round($months / 24 * 100, 2);
        }

        return round($months / 216 * 100, 2);
    }

    private function labelsForType(string $type): array
    {
        return match ($type) {
            'height' => ['x' => 'Age (months)', 'y' => 'Height (cm)'],
            'weight' => ['x' => 'Age (months)', 'y' => 'Weight (kg)'],
            'hc' => ['x' => 'Age (months)', 'y' => 'Head Circumference (cm)'],
            default => ['x' => 'Age (months)', 'y' => 'BMI (kg/m²)'],
        };
    }

    /**
     * Build HK-2020 reference curve series (p5, p85, p95) from config.
     */
    private function referenceCurves(string $gender): array
    {
        $table = config('bmi.percentiles.'.strtolower($gender), []);

        $p5 = [];
        $p85 = [];
        $p95 = [];

        foreach ($table as $ageMonths => $thresholds) {
            $x = round($ageMonths / 216 * 100, 2);
            $p5[] = [$x, $thresholds['p5']];
            $p85[] = [$x, $thresholds['p85']];
            $p95[] = [$x, $thresholds['p95']];
        }

        return [
            'p5' => $p5,
            'p85' => $p85,
            'p95' => $p95,
        ];
    }
}
