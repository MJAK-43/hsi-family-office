<?php

return [
    /*
     * Code secret partagé avec le service Assistant IA (Python).
     * Vide = accès de l'assistant désactivé (404 sur toutes les routes /api/assistant).
     */
    'token' => env('ASSISTANT_API_TOKEN'),
];
