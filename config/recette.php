<?php

return [
    /*
     * Code secret de la page de recette : https://…/recette/{token}.
     * Vide = page désactivée (404). À définir dans .env : RECETTE_TOKEN=…
     */
    'token' => env('RECETTE_TOKEN'),

    // Pièces jointes d'un retour « À revoir ».
    'max_files' => 10,
    'max_file_kb' => 20480, // 20 Mo par fichier
    'disk' => 'local',
    'directory' => 'recette',
];
