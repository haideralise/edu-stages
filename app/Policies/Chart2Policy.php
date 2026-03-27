<?php

namespace App\Policies;

use App\Models\WpUser;

class Chart2Policy
{
    /**
     * Student can only view own chart; admin can view any.
     * Coach gets 403 (chart2 is student-only per 05eng).
     */
    public function view(WpUser $user, int $targetUserId): bool
    {
        $role = $user->resolveRole();

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'student') {
            return $user->ID === $targetUserId;
        }

        return false;
    }
}
