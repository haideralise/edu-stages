<?php

namespace App\Providers;

use App\Models\EduBmi;
use App\Models\EduResult;
use App\Policies\BmiPolicy;
use App\Policies\Chart2Policy;
use App\Policies\ResultPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
