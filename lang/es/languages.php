<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Añadir idioma',
    'fields' => [
        'name' => 'Nombre',
        'code' => 'Código',
        'active' => 'Activo',
        'is_default' => 'Predeterminado',
        'sort' => 'Orden',
    ],
    'hints' => [
        'name' => 'Tal como lo verán los visitantes en el selector de idioma: English, Deutsch, Español.',
        'code' => 'Dos o tres letras minúsculas, opcionalmente con región: en, de, pt-br. Pasa a ser el inicio de la URL (/de/…) y después no se puede cambiar.',
        'code_fixed' => 'Las traducciones se guardan con este código y las URL empiezan por él, así que no se puede cambiar.',
        'is_default' => 'Sus páginas no llevan prefijo de idioma en la URL, y su texto se muestra donde falta una traducción.',
    ],
    'switch' => 'Idioma de los contenidos',
];
