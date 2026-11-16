<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Añadir sitio',
    'add_domain' => 'Añadir dominio',
    'fields' => [
        'name' => 'Nombre',
        'code' => 'Código',
        'domains' => 'Dominios',
        'languages' => 'Idiomas',
        'default_language' => 'Idioma predeterminado',
        'active' => 'Activo',
        'is_default' => 'Sitio predeterminado',
        'sort' => 'Orden',
    ],
    'hints' => [
        'code' => 'Letras latinas minúsculas, dígitos y guiones: main, shop, de. No se puede cambiar después.',
        'domains' => 'El sitio se abre en estos dominios: example.com, www.example.com. El primero es el principal.',
        'languages' => 'Los idiomas de contenido de este sitio. Ninguno seleccionado: todos los idiomas activos.',
        'default_language' => 'Sus páginas no llevan prefijo de idioma en la URL. Vacío: el idioma predeterminado de todo el panel.',
        'is_default' => 'Se abre en cualquier dominio que no reclame otro sitio, y en localhost.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» no es un nombre de dominio.',
        'domain_taken' => 'El dominio :domain ya lo usa el sitio «:site».',
    ],
];
