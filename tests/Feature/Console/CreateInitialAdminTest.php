<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

it('creates the first active admin and requires a password change', function () {
    $password = 'Secure-Centre-2026!';

    $this->artisan('app:create-initial-admin', [
        '--name' => 'Administrateur initial',
        '--email' => 'admin@centre.example',
    ])
        ->expectsQuestion('Mot de passe (12 caractères minimum)', $password)
        ->expectsQuestion('Confirmez le mot de passe', $password)
        ->expectsOutput('Le compte administrateur admin@centre.example a été créé. Son mot de passe devra être changé à la première connexion.')
        ->assertExitCode(Command::SUCCESS);

    $user = User::query()->where('email', 'admin@centre.example')->firstOrFail();

    expect($user->role)->toBe(Role::Admin)
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

it('refuses to create another admin once an admin exists', function () {
    User::factory()->admin()->create();

    $this->artisan('app:create-initial-admin')
        ->expectsOutput('Un compte administrateur existe déjà. Cette commande ne peut servir qu’à créer le premier.')
        ->assertExitCode(Command::FAILURE);

    $this->assertDatabaseCount('users', 1);
});

it('rejects short or mismatched initial admin passwords', function () {
    $this->artisan('app:create-initial-admin', [
        '--name' => 'Administrateur initial',
        '--email' => 'admin@centre.example',
    ])
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'short')
        ->expectsQuestion('Confirmez le mot de passe', 'different')
        ->expectsOutput('Le mot de passe doit contenir au moins 12 caractères et les deux saisies doivent correspondre.')
        ->assertExitCode(Command::FAILURE);

    $this->assertDatabaseCount('users', 0);
});
