<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Services\BrandingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-branding', fn (Admin $admin) => $admin->is_super_admin);
        Gate::define('view-audit', fn (Admin $admin, AdminActivityLog $log) => $admin->is_super_admin || $log->admin_id === $admin->id);
        RateLimiter::for('admin-login', fn ($request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('admin-reset', fn ($request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(3)->by(mb_strtolower((string) $request->input('email'))),
        ]);
        ResetPassword::createUrlUsing(fn ($admin, $token) => rtrim(config('app.url'), '/').'/admin/reset-password/'.$token.'?'.http_build_query(['email' => $admin->email]));
        View::composer('components.brand', fn ($view) => $view->with('logoPath', app(BrandingService::class)->path()));
        Paginator::defaultView('components.pagination');
    }
}
