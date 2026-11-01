<?php

use App\Http\Controllers\Web\NotificationPreferenceController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $routes = $this->app['router']->getRoutes();

    if ($routes->getByName('notifications.preferences.edit') === null) {
        $this->app['router']
            ->get('/test/notifications/preferences', [NotificationPreferenceController::class, 'edit'])
            ->middleware('auth')
            ->name('notifications.preferences.edit');
        $this->app['router']
            ->patch('/test/notifications/preferences', [NotificationPreferenceController::class, 'update'])
            ->middleware('auth')
            ->name('notifications.preferences.update');
        $routes->refreshNameLookups();
        $this->app['url']->setRoutes($routes);
    }
});

it('renders every optional category and explains that private messages remain mandatory', function () {
    $user = User::factory()->guardian()->create();

    $this->actingAs($user)
        ->get(route('notifications.preferences.edit'))
        ->assertSeeText('Préférences de notification')
        ->assertSeeText('Annonce')
        ->assertSeeText('Présence')
        ->assertSeeText('Notes')
        ->assertSeeText('Emploi du temps')
        ->assertSeeText('Paiements')
        ->assertSeeText('Messages privés')
        ->assertSeeText('toujours activés');
});

it('saves only the five optional notification preferences', function () {
    $user = User::factory()->guardian()->create();

    $this->actingAs($user)
        ->patch(route('notifications.preferences.update'), [
            'preferences' => [
                'announcement' => '0',
                'attendance' => '1',
                'grades' => '0',
                'schedule' => '1',
                'payment' => '0',
            ],
        ])
        ->assertRedirect(route('notifications.preferences.edit'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->notification_preferences)->toBe([
        'announcement' => false,
        'attendance' => true,
        'grades' => false,
        'schedule' => true,
        'payment' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.preferences.update'), [
            'preferences' => [
                'announcement' => '1',
                'attendance' => '1',
                'grades' => '1',
                'schedule' => '1',
                'payment' => '1',
                'message' => '0',
            ],
        ])
        ->assertSessionHasErrors('preferences');

    expect($user->fresh()->receivesNotificationCategory('message'))->toBeTrue();
});
