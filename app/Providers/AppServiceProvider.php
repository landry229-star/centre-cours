<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\GuardianMessage;
use App\Services\CentreSettingsService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'home',
            'layouts.app',
            'layouts.guest',
        ], function (ViewInstance $view): void {
            $view->with('centreSettings', app(CentreSettingsService::class)->current());
        });

        View::composer('layouts.navigation', function (ViewInstance $view): void {
            $user = auth()->user();

            if ($user === null) {
                return;
            }

            $unreadMessagesCount = 0;
            $unreadAnnouncementsCount = 0;

            if ($user->isAdmin() || $user->isGuardian()) {
                $unreadMessagesCount = GuardianMessage::query()
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id)
                    ->whereHas('conversation', fn ($query) => $user->isGuardian()
                        ? $query->whereHas('guardian', fn ($guardian) => $guardian->where('user_id', $user->id))
                        : $query)
                    ->count();
            }

            if ($user->isGuardian()) {
                $unreadAnnouncementsCount = Announcement::query()
                    ->whereHas('recipients', fn ($query) => $query
                        ->whereKey($user->id)
                        ->whereNull('announcement_user.read_at'))
                    ->count();
            }

            $view->with([
                'unreadNotificationsCount' => $user->unreadNotifications()->count(),
                'unreadMessagesCount' => $unreadMessagesCount,
                'unreadAnnouncementsCount' => $unreadAnnouncementsCount,
            ]);
        });
    }
}
