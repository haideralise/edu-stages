<?php

namespace App\Providers;

use App\Models\EduBmi;
use App\Models\EduResult;
use App\Policies\BmiPolicy;
use App\Policies\Chart2Policy;
use App\Policies\ResultPolicy;
// from P2
use App\Services\AttendanceService;
use App\Services\ClassService;
use App\Services\Common\AttendanceSummaryService;
use App\Services\Common\ClassMonthFacade;
use App\Services\Common\ClassStudentQueryService;
use App\Services\Common\CoachBonusCalculationService;
use App\Services\Common\StudentFeeServiceCommon;
use App\Services\Common\StudentPaymentServiceCommon;
use App\Services\StudentOrderService;
// end from P2
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── from P2 ─────────────────────────────────────────────
        $this->app->singleton(StudentOrderService::class);
        $this->app->singleton(CoachBonusCalculationService::class);
        $this->app->singleton(ClassService::class);
        $this->app->singleton(AttendanceSummaryService::class);
        $this->app->singleton(ClassMonthFacade::class);
        $this->app->singleton(ClassStudentQueryService::class);
        $this->app->singleton(StudentFeeServiceCommon::class);
        $this->app->singleton(StudentPaymentServiceCommon::class);
        $this->app->singleton(AttendanceService::class);
        // ── end from P2 ─────────────────────────────────────────
    }

    public function boot(): void
    {
        Gate::policy(EduBmi::class, BmiPolicy::class);
        Gate::policy(EduResult::class, ResultPolicy::class);

        Gate::define('bmi.listApi', [new BmiPolicy, 'listApi']);
        Gate::define('result.listApi', [new ResultPolicy, 'listApi']);

        $chart2Policy = new Chart2Policy;
        Gate::define('chart2.viewAny', [$chart2Policy, 'viewAny']);
        Gate::define('chart2.view', [$chart2Policy, 'view']);
    }
}
