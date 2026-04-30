<?php

namespace App\Services;

use App\Models\EduBmi;
use App\Models\EduClassUser;
use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Support\Collection;

class BmiQueryService
{
    public function getAllRecords(): Collection
    {
        return EduBmi::with('user.meta')
            ->orderByDesc('date')
            ->get()
            ->each(fn (EduBmi $bmi) => $bmi->setAttribute('student_name', $bmi->user?->display_name ?? "Student #{$bmi->user_id}"));
    }

    public function getRecordsForUser(int $userId): Collection
    {
        return EduBmi::with('user.meta')
            ->forUser($userId)
            ->orderByDesc('date')
            ->get();
    }

    public function getStudentList(): Collection
    {
        $coachIds = EduClassUser::allTeacherIds();

        $adminIds = WpUserMeta::where('meta_key', 'wp_3x_capabilities')
            ->where('meta_value', 'like', '%administrator%')
            ->pluck('user_id');

        $excludeIds = $coachIds->merge($adminIds)->unique()->values()->all();

        return WpUser::whereNotIn('ID', $excludeIds)
            ->orderBy('display_name')
            ->get();
    }
}
