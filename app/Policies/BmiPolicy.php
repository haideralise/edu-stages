<?php

namespace App\Policies;

use App\Models\EduBmi;
use App\Models\WpUser;

class BmiPolicy
{
    public function viewAny(WpUser $user): bool
    {
        $role = $user->resolveRole();

        return $role === 'student' || $role === 'admin';
    }

    public function listApi(WpUser $user): bool
    {
        return in_array($user->resolveRole(), ['student', 'coach', 'admin'], true);
    }

    public function view(WpUser $user, EduBmi $bmi): bool
    {
        if ($user->resolveRole() === 'admin') {
            return true;
        }

        return $user->ID === $bmi->user_id;
    }

    public function create(WpUser $user): bool
    {
        $role = $user->resolveRole();

        return $role === 'student' || $role === 'admin';
    }

    public function update(WpUser $user, EduBmi $bmi): bool
    {
        if ($user->resolveRole() === 'admin') {
            return true;
        }

        return $user->ID === $bmi->user_id;
    }

    public function delete(WpUser $user, EduBmi $bmi): bool
    {
        if ($user->resolveRole() === 'admin') {
            return true;
        }

        return $user->ID === $bmi->user_id;
    }
}
