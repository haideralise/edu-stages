<?php

namespace App\Services;

use App\Models\EduBmi;
use App\Models\EduResult;
use App\Models\WpUser;
use App\Support\BmiForAge;
use Illuminate\Support\Collection;

class ChartDataService
{
    public function getBmiChartData(WpUser $target, string $type): array
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

        return [
            'datasets' => $studentSeries,
            'labels' => $labels,
            'series' => $series,
        ];
    }

    public function getResultChartData(WpUser $target): array
    {
        $results = EduResult::where('user_id', $target->ID)->orderBy('exam_date')->get();

        $studentSeries = $results->map(fn (EduResult $r) => [
            $r->exam_date,
            (float) $r->exam_data,
        ])->values()->all();

        return [
            'datasets' => $studentSeries,
            'labels' => ['x' => 'Date', 'y' => 'Score'],
            'series' => ['student' => $studentSeries],
        ];
    }

    public function getStudentsWithBmi(): Collection
    {
        $studentIds = EduBmi::distinct()->pluck('user_id');

        return WpUser::whereIn('ID', $studentIds)->get();
    }
}
