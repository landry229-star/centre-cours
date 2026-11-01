<?php

namespace App\Services\Guardians;

use App\Enums\Role;
use App\Models\Guardian;
use App\Services\Accounts\AccountProvisioner;
use DomainException;
use Illuminate\Support\Facades\DB;

class GuardianService
{
    public function __construct(private AccountProvisioner $accounts) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{guardian: Guardian, account_created: bool}
     */
    public function create(array $data, bool $withAccount = false): array
    {
        return DB::transaction(function () use ($data, $withAccount) {
            $guardian = Guardian::create($data);

            if ($withAccount) {
                $this->createAccount($guardian);
            }

            return ['guardian' => $guardian, 'account_created' => $withAccount];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Guardian $guardian, array $data): Guardian
    {
        return DB::transaction(function () use ($guardian, $data) {
            $guardian->fill($data)->save();

            if ($guardian->user !== null) {
                $guardian->user->update([
                    'name' => trim("{$guardian->first_name} {$guardian->last_name}"),
                    'email' => $guardian->email,
                ]);
            }

            return $guardian;
        });
    }

    public function createAccount(Guardian $guardian): void
    {
        if ($guardian->user_id !== null) {
            throw new DomainException('Ce parent possède déjà un accès.');
        }

        if (blank($guardian->email)) {
            throw new DomainException('Une adresse email est nécessaire pour créer un accès.');
        }

        $user = $this->accounts->provision(
            trim("{$guardian->first_name} {$guardian->last_name}"),
            $guardian->email,
            Role::Guardian,
        );

        $guardian->user()->associate($user)->save();
    }

    public function delete(Guardian $guardian): void
    {
        if ($guardian->students()->exists()) {
            throw new DomainException(
                "Impossible de supprimer ce parent : des élèves lui sont rattachés. Réaffectez-les d'abord."
            );
        }

        DB::transaction(function () use ($guardian) {
            if ($guardian->user !== null) {
                $guardian->user->forceFill(['is_active' => false])->save();
            }

            $guardian->delete();
        });
    }
}
