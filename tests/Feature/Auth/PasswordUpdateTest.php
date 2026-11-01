<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'Nouveau-mdp-2026',
            'password_confirmation' => 'Nouveau-mdp-2026',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('Nouveau-mdp-2026', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'Nouveau-mdp-2026',
            'password_confirmation' => 'Nouveau-mdp-2026',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('a provisioned account must change its temporary password before using the application', function () {
    $user = User::factory()->teacher()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get('/dashboard')
        ->assertRedirect(route('password.change-required'));

    $this->get(route('password.change-required'))
        ->assertOk()
        ->assertSee('remplacez le mot de passe provisoire');

    $this->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'Nouveau-mdp-2026',
        'password_confirmation' => 'Nouveau-mdp-2026',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('Nouveau-mdp-2026', $user->fresh()->password))->toBeTrue();
});

test('a provisioned account cannot use the API before changing its temporary password', function () {
    $user = User::factory()->teacher()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertForbidden()
        ->assertJsonPath('message', 'Vous devez changer votre mot de passe avant de continuer.');
});

test('a provisioned account can log out before changing its temporary password', function () {
    $user = User::factory()->teacher()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});
