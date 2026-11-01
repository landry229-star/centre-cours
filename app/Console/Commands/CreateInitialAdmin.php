<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:create-initial-admin {--name=} {--email=}')]
#[Description('Crée le premier compte administrateur avec mot de passe défini de façon sécurisée')]
class CreateInitialAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', Role::Admin->value)->exists()) {
            $this->error('Un compte administrateur existe déjà. Cette commande ne peut servir qu’à créer le premier.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Nom complet')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Adresse e-mail'))));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('Le nom est obligatoire et ne doit pas dépasser 255 caractères.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::query()->where('email', $email)->exists()) {
            $this->error('L’adresse e-mail est invalide ou déjà utilisée.');

            return self::FAILURE;
        }

        $password = $this->secret('Mot de passe (12 caractères minimum)');
        $passwordConfirmation = $this->secret('Confirmez le mot de passe');

        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $passwordConfirmation) {
            $this->error('Le mot de passe doit contenir au moins 12 caractères et les deux saisies doivent correspondre.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password): ?User {
            if (User::query()->where('role', Role::Admin->value)->exists()) {
                return null;
            }

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->role = Role::Admin;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->must_change_password = true;
            $user->save();

            return $user;
        });

        if ($user === null) {
            $this->error('Un compte administrateur a été créé entre-temps. Aucun nouveau compte n’a été ajouté.');

            return self::FAILURE;
        }

        $this->info("Le compte administrateur {$user->email} a été créé. Son mot de passe devra être changé à la première connexion.");

        return self::SUCCESS;
    }
}
