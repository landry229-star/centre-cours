<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceDatasetProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('performance:seed {--students=5000} {--classes=150} {--confirm-database=}')]
#[Description('Ajoute un jeu scolaire fictif pour mesurer les performances localement')]
class SeedPerformanceData extends Command
{
    public function handle(PerformanceDatasetProvisioner $provisioner): int
    {
        if (! app()->environment('local')) {
            $this->error('Cette commande est réservée à l’environnement local.');

            return self::FAILURE;
        }

        $database = DB::selectOne('SELECT current_database() AS database_name')->database_name;

        if ($this->option('confirm-database') !== $database) {
            $this->error("Confirmez explicitement la base cible avec --confirm-database={$database}.");

            return self::FAILURE;
        }

        $studentCount = filter_var($this->option('students'), FILTER_VALIDATE_INT);
        $classroomCount = filter_var($this->option('classes'), FILTER_VALIDATE_INT);

        if ($studentCount === false || $studentCount < 1 || $studentCount > 20000) {
            $this->error('Le nombre d’élèves doit être compris entre 1 et 20 000.');

            return self::FAILURE;
        }

        if (
            $classroomCount === false
            || $classroomCount < (int) ceil($studentCount / 40)
            || $classroomCount > min($studentCount, 800)
        ) {
            $this->error('Le nombre de classes doit permettre de respecter une capacité maximale de 40 élèves.');

            return self::FAILURE;
        }

        try {
            $counts = $provisioner->populate($studentCount, $classroomCount);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Données fictives ajoutées', 'Nombre'],
            collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->values()->all(),
        );

        return self::SUCCESS;
    }
}
