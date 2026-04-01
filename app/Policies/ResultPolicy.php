<?php

namespace App\Policies;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;

class ResultPolicy
{
    /**
     * Student and Admin can view the test results list page.
     * Coaches access results via their own /coach/results route.
     */
    public function viewAny(WpUser $user): bool
    {
        $role = $user->resolveRole();

        return $role === 'student' || $role === 'admin';
    }

    public function listApi(WpUser $user): bool
    {
        return in_array($user->resolveRole(), ['student', 'coach', 'admin'], true);
    }

    /**
     * Coach can view results of students in their own classes.
     */
    public function viewAsCoach(WpUser $user): bool
    {
        $role = $user->resolveRole();

        return $role === 'coach' || $role === 'admin';
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

        if ($role === 'coach') {
            return $this->coachOwnsStudentResult($user, $result);
        }

        return false;
    }

    private function coachOwnsStudentResult(WpUser $coach, EduResult $result): bool
    {
        $studentId = (string) $result->user_id;

        return EduClassUser::where('class_id', $result->class_id)
            ->whereTeacher($coach->ID)
            ->get()
            ->contains(fn ($cu) => in_array($studentId, $cu->student ?? []));
    }
}
