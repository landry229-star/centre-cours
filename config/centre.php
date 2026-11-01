<?php

/*
 * Identité du centre, utilisée sur les reçus PDF (et plus tard sur les bulletins).
 * Tout est modifiable depuis le fichier .env : aucune donnée en dur dans le code.
 */
return [
    'name' => env('CENTRE_NAME', 'Centre de Cours AfriTech'),
    'address' => env('CENTRE_ADDRESS', 'Abomey-Calavi, Bénin'),
    'phone' => env('CENTRE_PHONE', '+229 00 00 00 00'),
    'email' => env('CENTRE_EMAIL'),
    // Chemin du logo, relatif au dossier public/. Facultatif : le reçu s'affiche sans logo s'il est absent.
    'logo' => env('CENTRE_LOGO', 'images/logo.png'),
];
