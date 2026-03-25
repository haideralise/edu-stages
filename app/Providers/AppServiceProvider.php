<?php

namespace App\Providers;

use App\Models\EduBmi;
use App\Models\EduResult;
use App\Policies\BmiPolicy;
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
    }
}
