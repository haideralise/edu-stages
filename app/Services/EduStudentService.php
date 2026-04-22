<?php

namespace App\Services;

use App\Models\EduClassUser;
use App\Models\WpUserMeta;
use App\Models\EduUser;
use Illuminate\Support\Facades\DB;

class EduStudentService
{
    public function getStudentProfileData(int $studentId): array
    {
        $metaKeys = [
            'billing_first_name', 'billing_last_name', 'billing_birthdate',
            'billing_school', 'billing_address_1', 'billing_phone',
            'billing_email', 'billing_contactname', 'billing_contactphone',
            'note', 'no_renew'
        ];

        $meta = WpUserMeta::where('user_id', $studentId)
            ->whereIn('meta_key', $metaKeys)
            ->get()
            ->pluck('meta_value', 'meta_key')
            ->toArray();

        return [
            'student' => $meta,
            'note' => [
                'note' => $meta['note'] ?? '',
                'no_renew' => $meta['no_renew'] ?? ''
            ],
            'age' => $this->calculateAge($meta['billing_birthdate'] ?? '')
        ];
    }

    public function getStudentClasses(int $studentId): array
    {
        return EduClassUser::whereJsonContains('student', (string)$studentId)
            ->get(['class_id', 'month', 'class_year'])
            ->toArray();
    }

    public function updateStudentFee(int $studentId, float $fee): void
    {
        EduUser::updateOrCreate(
            ['user_id' => $studentId],
            ['class_fee' => $fee]
        );
    }

    public function calculateAge(string $birthdate): string
    {
        if (empty($birthdate)) {
            return '—';
        }

        try {
            $birth = new \DateTime($birthdate);
            $now = new \DateTime();
            $age = $now->diff($birth)->y;
            return $age . ' 歲';
        } catch (\Throwable $e) {
            return '—';
        }
    }
}
