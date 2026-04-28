<?php

namespace App\Services;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Support\Collection;

class CoachResultQueryService
{
    public function getResults(WpUser $user, bool $isAdmin): Collection
    {
        if ($isAdmin) {
            return EduResult::with('user')->get();
        }

        $studentIds = EduClassUser::studentIdsForTeacher($user->ID);

        return EduResult::with('user')->whereIn('user_id', $studentIds->all())->get();
    }

    public function groupByStudent(Collection $results): Collection
    {
        return $results->groupBy('user_id');
    }
}
