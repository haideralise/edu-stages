<?php

namespace App\Policies;

use App\Models\EduBmi;
use App\Models\WpUser;

class BmiPolicy
{
    public function viewAny(WpUser $user): bool
    {
        return true; // all authenticated users can view own BMI list
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
        return true; // any authenticated user can create own record
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
