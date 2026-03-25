<?php

namespace App\Policies;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;

class ResultPolicy
{
    public function viewAny(WpUser $user): bool
    {
        return true;
    }

    public function view(WpUser $user, EduResult $result): bool
    {
        $role = $user->resolveRole();

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'student') {
            return $user->ID === $result->user_id;
        }

        // Coach: check teacher JSON contains coach ID AND student JSON contains student ID
        if ($role === 'coach') {
            return $this->coachOwnsStudentResult($user, $result);
        }

        return false;
    }

    private function coachOwnsStudentResult(WpUser $coach, EduResult $result): bool
    {
        $coachId   = (string) $coach->ID;
        $studentId = (string) $result->user_id;

        return EduClassUser::where('class_id', $result->class_id)
            ->get()
            ->contains(function ($cu) use ($coachId, $studentId) {
                $teachers = $cu->teacher ?? [];
                $students = $cu->student ?? [];

                return in_array($coachId, $teachers) && in_array($studentId, $students);
            });
    }
}
