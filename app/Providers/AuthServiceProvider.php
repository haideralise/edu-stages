<?php
namespace App\Providers;

use App\Auth\WpUserGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::extend('wp-cookie', function ($app, $name, array $config) {
            return new WpUserGuard(
                Auth::createUserProvider($config['provider']),
                $app['request']
            );
        });
    }
}
