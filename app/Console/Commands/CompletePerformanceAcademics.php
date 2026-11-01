<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceAcademicDatasetProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('performance:complete-academics {--confirm-database=}')]
#[Description('Ajoute des enseignants et affectations pédagogiques aux classes PERF26')]
class CompletePerformanceAcademics extends Command
{
    public function handle(PerformanceAcademicDatasetProvisioner $provisioner): int
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

        try {
            $counts = $provisioner->populate();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Données pédagogiques fictives ajoutées', 'Nombre'],
            collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->values()->all(),
        );
        $this->warn('La répartition des matières est indicative pour la démonstration; les séries du lycée doivent être confirmées par le centre.');

        return self::SUCCESS;
    }
}
