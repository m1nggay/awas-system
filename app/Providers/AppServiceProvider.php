<?php

namespace App\Providers;

use App\Services\BillingService;
use App\Services\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(BillingService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Sidebar badge + notification bell data for the logged-in layout.
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            if (!$user) {
                return;
            }
            $view->with([
                'unreadCount' => DB::table('notifications')->where('user_id', $user->user_id)->where('is_read', false)->count(),
                'notifications' => DB::table('notifications')->where('user_id', $user->user_id)
                    ->orderByDesc('created_at')->orderByDesc('notification_id')->limit(8)->get(),
                // Administrators: where automatic checking (live.js) starts from.
                'liveStart' => $user->isAdmin() ? [
                    'reading' => (int)DB::table('meter_readings')->max('reading_id'),
                    'notif'   => (int)DB::table('notifications')->where('user_id', $user->user_id)->max('notification_id'),
                ] : null,
                'pendingApplicationCount' => $user->isStaff()
                    ? DB::table('membership_applications')->whereIn('status', ['pending_review', 'approved'])->count()
                    : 0,
            ]);
        });
    }
}
