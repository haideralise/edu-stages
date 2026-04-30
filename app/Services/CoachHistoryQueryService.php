<?php

namespace App\Services;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Support\Collection;

class CoachHistoryQueryService
{
    public function getResults(WpUser $user, bool $isAdmin, ?string $classYear): Collection
    {
        if ($isAdmin) {
            $query = EduResult::query();
        } else {
            $studentIds = EduClassUser::studentIdsForTeacher($user->ID);
            $query = EduResult::whereIn('user_id', $studentIds->all());
        }

        if ($classYear) {
            $query->where('class_year', $classYear);
        }

        return $query->with('user')->orderByDesc('exam_date')->get();
    }

    public function groupByClassMonth(Collection $results): Collection
    {
        return $results->groupBy(fn ($r) => $r->class_year.' '.$r->class_month);
    }

    public function getAvailableYears(): Collection
    {
        return EduResult::distinct()->pluck('class_year')->filter()->sort()->values();
    }

    public function buildCoachNameMap(Collection $results): Collection
    {
        $classIds = $results->pluck('class_id')->unique();
        $classUsers = EduClassUser::whereIn('class_id', $classIds)->get();

        $teacherIds = $classUsers->flatMap(fn ($cu) => $cu->teacher ?? [])
            ->map(fn ($id) => (int) $id)->unique();
        $teacherNames = WpUser::whereIn('ID', $teacherIds)->pluck('display_name', 'ID');

        return $classUsers->groupBy('class_id')->map(function ($rows) use ($teacherNames) {
            return $rows->flatMap(fn ($cu) => $cu->teacher ?? [])
                ->map(fn ($id) => $teacherNames->get((int) $id, 'Unknown'))
                ->unique()->implode(', ');
        });
    }
}
