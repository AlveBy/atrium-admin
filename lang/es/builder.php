<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Editor de página: :title',
    'open' => 'Abrir el editor de página',
    'back' => 'Volver al formulario',
    'save_draft' => 'Guardar borrador',
    'publish' => 'Publicar',
    'failed' => 'No ha funcionado. Compruebe la conexión e inténtelo de nuevo.',
    'leave' => 'Los cambios no se han guardado. ¿Salir del editor y perderlos?',
    'stale' => 'La página publicada cambió después de empezar este borrador. Al publicar se preguntará antes de reemplazar esos cambios.',
    'hidden' => 'Esta página está desactivada: publicarla no la muestra en el sitio.',
    'palette' => 'Bloques',
    'palette_hint' => 'Arrastre un bloque a una columna o púlselo para ponerlo después de lo seleccionado.',
    'add_type' => 'Añadir bloque: :type',
    'column_full' => 'Aquí no cabe otro bloque.',
    'structure' => 'Estructura de la página',
    'drop_here' => 'Arrastre un bloque aquí',
    'has_error' => 'Error',
    'not_drawn' => 'Aún no se ve en la página',
    'settings' => 'Ajustes',
    'nothing_selected' => 'Seleccione una sección, una columna o un bloque para cambiar sus ajustes.',
    'move_to' => 'Mover a la columna',
    'move' => 'Mover',
    'status' => [
        'published' => 'Publicada',
        'unsaved' => 'Cambios sin guardar',
        'draft' => 'Borrador guardado el :date, sin publicar',
        'draft_by' => 'Borrador guardado el :date por :author, sin publicar',
    ],
    'notices' => [
        'draft' => 'El borrador está guardado. El sitio muestra la página publicada.',
        'publish' => 'Publicada.',
        'discard' => 'Los cambios se han descartado.',
        'version' => 'La versión está abierta en el editor. Publíquela para que aparezca en el sitio.',
    ],
    'discard' => [
        'button' => 'Descartar cambios',
        'title' => '¿Descartar los cambios?',
        'question' => 'Se eliminan el borrador y los cambios sin guardar; el editor vuelve a la página publicada.',
    ],
    'conflicts' => [
        'draft' => 'Alguien guardó o descartó el borrador de esta página después de que usted la abriera.',
        'published' => 'La página publicada cambió después de empezar esta edición.',
    ],
    'conflict' => [
        'title' => 'La página se ha cambiado en otro lugar',
        'hint' => 'Al cargar la página más reciente se pierden sus cambios sin guardar. Si continúa, sus cambios reemplazan a los otros.',
        'reload' => 'Cargar la página más reciente',
        'force' => 'Continuar de todos modos',
    ],
    'versions' => [
        'button' => 'Versiones',
        'title' => 'Versiones anteriores de la página',
        'hint' => 'Abrir una versión no cambia nada en el sitio hasta que la publique.',
        'replaces' => 'Abrir una versión reemplaza sus cambios sin guardar.',
        'open' => 'Abrir en el editor',
        'current' => 'publicada ahora',
    ],
    'preview' => [
        'title' => 'Vista previa',
        'updating' => 'Actualizando…',
        'hint' => 'Haga clic en la página para seleccionar una parte.',
        'failed' => 'No se pudo mostrar la vista previa.',
        'selected' => 'Seleccionado: :place',
    ],
    'said' => [
        'added' => 'Añadido: :place',
        'moved' => 'Movido, ahora en: :place',
        'removed' => 'Eliminado: :place',
    ],
    'count' => [
        'sections' => ':count sección|:count secciones',
        'blocks' => ':count bloque|:count bloques',
        'empty' => 'La página aún no tiene bloques.',
    ],
];
