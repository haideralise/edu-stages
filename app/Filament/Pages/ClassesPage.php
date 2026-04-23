<?php

namespace App\Filament\Pages;

use App\Services\ClassMonthFacade;
use App\Services\ClassService;
use Filament\Pages\Page;

class ClassesPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Classes';

    protected static ?string $title = 'Class Management';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.classes';

    /**
     * 03eng §III: coaches cannot read the class list in Laravel.
     * Admin only.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getViewData(): array
    {
        $districtId = (int) request()->get('district_id', 0);
        $districtId2 = (int) request()->get('district_id2', 0);
        $lv3 = request()->get('lv3', '');
        $month = request()->get('m', date('Y-m')); // JS uses ?m= param

        // Admin only — no coach branch (03eng §III)
        $filters = [
            'district_id' => $districtId,
            'district_id2' => $districtId2,
            'lv3' => $lv3,
            'month' => $month,
        ];

        // ClassMonthFacade::fetchMonthlyClasses (10eng §1)
        $data = app(ClassMonthFacade::class)
            ->fetchMonthlyClasses($filters, ['with_attendance_level' => 'summary']);

        // Prev/next month navigation for month-selector component
        $preNextMonth = app(ClassService::class)->getPreAndNextMonth();

        // Build district lists from classes_lv3 for district-menu component
        $district = [];
        $district2 = [];
        foreach ($data['classes_lv3'] as $c) {
            if (! empty($c['district_id']) && ! isset($district[$c['district_id']])) {
                $district[$c['district_id']] = $c['district_name'] ?? $c['district_id'];
            }
        }

        return [
            'processedClasses' => $data['processed_classes'],
            'classes_lv3' => $data['classes_lv3'],
            'no_teacher_num' => $data['no_teacher_num'],
            'no_days_num' => $data['no_days_num'],
            'month' => $data['month'],
            'preNextMonth' => $preNextMonth,
            'district' => $district,
            'district2' => $district2,
            'district_id' => $districtId,
            'district_id2' => $districtId2,
            'lv3' => $lv3,
            'current_user' => null,
            'is_admin' => true, // canAccess() guarantees Admin only
        ];
    }
}
