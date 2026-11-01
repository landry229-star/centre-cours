<?php

use Symfony\Component\Console\Command\Command;

it('refuses to seed performance data outside the local environment', function () {
    $this->artisan('performance:seed', [
        '--students' => 5000,
        '--classes' => 150,
        '--confirm-database' => 'centre_cours_test',
    ])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutput('Cette commande est réservée à l’environnement local.');
});
