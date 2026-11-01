<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration (données fictives, mot de passe : "password").
 * DemoSchoolDataSeeder crée leurs profils scolaires associés hors production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ne jamais créer de comptes connus en production.
        if (app()->isProduction()) {
            return;
        }

        $accounts = [
            ['Administrateur Démo', 'admin@centre.test', Role::Admin],
            ['Enseignant Démo', 'enseignant@centre.test', Role::Teacher],
            ['Parent Démo', 'parent@centre.test', Role::Guardian],
            ['Élève Démo', 'eleve@centre.test', Role::Student],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            User::factory()->state(['role' => $role])->create([
                'name' => $name,
                'email' => $email,
            ]);
        }
    }
}
