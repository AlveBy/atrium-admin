<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Añadir sitio',
    'tabs' => [
        'site' => 'Sitio',
        'codes' => 'Contadores y códigos',
    ],
    'add_domain' => 'Añadir dominio',
    'fields' => [
        'name' => 'Nombre',
        'code' => 'Código',
        'domains' => 'Dominios',
        'folder' => 'Carpeta',
        'languages' => 'Idiomas',
        'default_language' => 'Idioma predeterminado',
        'active' => 'Activo',
        'is_default' => 'Sitio predeterminado',
        'sort' => 'Orden',
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Contador de Yandex Metrica',
        'yandex_webvisor' => 'Repetición de sesiones (Webvisor)',
        'counters_skip_admins' => 'No contar a los administradores',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Código en <head>',
        'body_start_code' => 'Código al inicio de <body>',
        'body_end_code' => 'Código antes de </body>',
    ],
    'hints' => [
        'code' => 'Letras latinas minúsculas, dígitos y guiones: main, shop, de. No se puede cambiar después.',
        'domains' => 'El sitio se abre en estos dominios: example.com, www.example.com. El primero es el principal.',
        'folder' => 'Para varios sitios en un dominio: site2 abre este sitio en example.com/site2/. Vacío: el sitio está en la raíz de sus dominios. Un sitio sin dominios está en esta carpeta en cualquier dominio.',
        'languages' => 'Los idiomas de contenido de este sitio. Ninguno seleccionado: todos los idiomas activos.',
        'default_language' => 'Sus páginas no llevan prefijo de idioma en la URL. Vacío: el idioma predeterminado de todo el panel.',
        'is_default' => 'Se abre en cualquier dominio que no reclame otro sitio, y en localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX para Google Analytics 4 o GTM-XXXXXXX para Tag Manager. Solo el ID: el código del contador se añade a todas las páginas automáticamente.',
        'yandex_metrika_id' => 'Solo el número del contador de los ajustes de Metrica: el código se añade a todas las páginas automáticamente.',
        'counters_skip_admins' => 'Los administradores con sesión iniciada reciben las páginas sin Google Tag ni Yandex Metrica, para que sus propias visitas no distorsionen las estadísticas. Las etiquetas de verificación y el código de abajo siempre están presentes.',
        'verification' => 'El código de la metaetiqueta :name. Puedes pegar la etiqueta completa.',
        'snippet' => 'Se añade tal cual a todas las páginas de este sitio: un widget de chat, un píxel, cualquier otro contador.',
        'robots_txt' => 'Vacío: los robots pueden indexarlo todo. Si no hay una línea «Sitemap:», se añade una con el sitemap.xml de este sitio.',
        'robots_not_production' => 'Este servidor no es de producción (APP_ENV=:env), así que robots.txt prohíbe la indexación sea lo que sea que se escriba aquí.',
        'robots_file_exists' => 'Existe el archivo :file: el servidor web lo devuelve en lugar de este texto. Elimina el archivo.',
    ],
    'errors' => [
        'domain_invalid' => '«:domain» no es un nombre de dominio.',
        'domain_taken' => 'El dominio :domain ya lo usa el sitio «:site».',
        'folder_invalid' => '«:folder» no es una carpeta: letras latinas, dígitos, guiones y guiones bajos, partes separadas por barras.',
        'folder_reserved' => 'La carpeta :folder es una dirección del propio panel.',
        'folder_default' => 'El sitio predeterminado responde a toda dirección que no reclame otro sitio, por eso está en la raíz. Vacíe la carpeta o haga predeterminado otro sitio.',
        'folder_taken' => 'La dirección :address ya la usa el sitio «:site».',
    ],
];
