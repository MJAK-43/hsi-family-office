<?php

return [
    'confirmed' => 'La confirmation ne correspond pas.',
    'email' => 'Saisissez une adresse email valide, par exemple nom@domaine.com.',
    'required' => 'Ce champ est obligatoire.',
    'string' => 'Ce champ doit être un texte.',
    'max' => [
        'string' => 'Ce champ ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'string' => 'Ce champ doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe apparaît dans des fuites de données connues. Choisissez-en un autre.',
    ],
    'attributes' => [
        'email' => 'adresse email',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
    ],
];
