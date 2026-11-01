<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notifications:prune')]
#[Description('Supprime uniquement les notifications in-app de plus de deux ans')]
class PruneOldNotifications extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subYears(2);
        $deleted = 0;

        do {
            $notificationIds = DB::table('notifications')
                ->where('created_at', '<', $cutoff)
                ->orderBy('created_at')
                ->limit(500)
                ->pluck('id');

            if ($notificationIds->isNotEmpty()) {
                $deleted += DB::table('notifications')
                    ->whereIn('id', $notificationIds)
                    ->delete();
            }
        } while ($notificationIds->isNotEmpty());

        $this->info("{$deleted} notification(s) ancienne(s) supprimée(s).");

        return self::SUCCESS;
    }
}
