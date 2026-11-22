<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Import of a module's records from CSV and Excel files.
 */
return [
    'title' => 'Importación',

    'index' => [
        'upload' => 'Subir un archivo',
        'hint' => 'CSV o Excel (xlsx), hasta :size MB. La primera fila contiene los nombres de las columnas. La codificación (UTF-8 o windows-1251) y el separador (punto y coma o coma) se reconocen solos. Un archivo exportado desde este listado se puede importar de vuelta tal cual.',
        'choose' => 'Archivo para importar',
        'submit' => 'Subir',
        'history' => 'Importaciones anteriores',
        'empty' => 'Todavía no se ha importado nada.',
        'file' => 'Archivo',
        'status' => 'Estado',
        'result' => 'Resultado',
        'counts' => 'creados :created, actualizados :updated, errores :failed',
        'user' => 'Quién',
        'console' => 'consola',
        'date' => 'Cuándo',
    ],

    'upload' => [
        'file' => 'archivo',
    ],

    'statuses' => [
        'new' => 'Esperando la comprobación',
        'checking' => 'Comprobando',
        'checked' => 'Comprobado',
        'importing' => 'Importando',
        'done' => 'Listo',
        'failed' => 'Fallido',
        'cancelled' => 'Detenido',
    ],

    'delimiters' => [
        ';' => 'punto y coma',
        ',' => 'coma',
        "\t" => 'tabulación',
    ],

    'show' => [
        'back' => 'Todas las importaciones',
        'file' => ':format, :encoding, separador: :delimiter, :rows filas',
        'workbook' => ':format, :rows filas',
        'checking' => 'Comprobando el archivo…',
        'importing' => 'Importando…',
        'progress' => ':processed de :total filas',
        'cancel' => 'Detener',
        'cancel_confirm' => '¿Detener? Lo que ya se ha importado se conserva.',
        'report' => 'Informe',
        'check_report' => 'Comprobación',
        'checked' => 'La comprobación ha terminado: todavía no se ha escrito nada. Esto es lo que hará la importación.',
        'done' => 'La importación ha terminado.',
        'cancelled' => 'La importación se ha detenido. Lo que ya se había importado se conserva.',
        'counts' => [
            'created' => 'Creados',
            'updated' => 'Actualizados',
            'skipped' => 'Omitidos',
            'failed' => 'Errores',
            'nodes' => 'Elementos del árbol creados',
        ],
        'will' => [
            'created' => 'Se crearán',
            'updated' => 'Se actualizarán',
            'skipped' => 'Se omitirán',
            'failed' => 'Errores',
            'nodes' => 'Elementos del árbol por crear',
        ],
        'row' => 'Fila',
        'outcome' => 'Resultado',
        'reason' => 'Motivo',
        'skipped_row' => 'omitida',
        'failed_row' => 'error',
        'unlisted' => 'Otras :count filas se cuentan, pero no aparecen en la lista.',
        'run' => 'Importar',
        'run_hint' => 'Las filas con errores se dejan fuera; el resto se importa tal como mostró la comprobación.',
        'mapping' => 'Columnas',
        'mapping_hint' => 'Qué campo rellena cada columna, deducido de los nombres de las columnas. Una celda vacía deja el campo como está.',
        'column' => 'Columna',
        'unnamed' => 'Columna :number',
        'samples' => 'En las primeras filas',
        'target' => 'Campo',
        'skip' => '— no importar —',
        'options' => 'Cómo importar',
        'key_hint' => 'Un registro con el mismo valor se actualiza; si no existe, se crea.',
        'separator_hint' => 'Separa un elemento padre de su hijo en una ruta: «Ropa / Chaquetas».',
        'check' => 'Comprobar',
        'check_again' => 'Comprobar de nuevo',
        'check_hint' => 'La comprobación lee todo el archivo y no escribe nada: se verá cuántos registros se crearían y actualizarían y en qué filas hay errores.',
    ],

    'settings' => [
        'mapping' => 'Columnas',
        'key' => 'Buscar registros por',
        'mode' => 'Qué hacer',
        'separator' => 'Separador de la ruta',
    ],

    'modes' => [
        'both' => 'Crear registros nuevos y actualizar los existentes',
        'create' => 'Solo crear registros nuevos',
        'update' => 'Solo actualizar registros existentes',
    ],

    'validation' => [
        'twice' => 'Un campo admite una sola columna; elegido más de una vez: :fields.',
        'key' => 'Los registros se buscan por «:field», pero ninguna columna lo rellena.',
    ],

    'failures' => [
        'empty' => 'El archivo no tiene filas.',
        'format' => 'No es un CSV ni un Excel (xlsx). Un archivo .xls antiguo se puede guardar en Excel como .xlsx o CSV.',
        'unreadable' => 'No se puede leer el archivo de Excel: puede estar dañado o protegido con contraseña.',
        'crashed' => 'La importación se detuvo por un error del servidor. Se ha registrado; lo que ya se había importado se conserva.',
        'missing' => 'No existe el archivo :file.',
        'download' => 'No se pudo descargar el archivo de :url.',
        'column' => 'No existe esa columna para --map: «:column». Se escribe así: «Columna=campo».',
        'option' => 'El módulo no tiene la opción «:option» para --option.',
        'module' => 'El módulo «:module» ya no existe.',
        'key' => 'No se pueden buscar los registros por «:key»: el módulo no tiene ese campo.',
    ],

    'problems' => [
        'no_key' => '«:field» está vacío: el registro no se puede encontrar ni crear.',
        'bad_id' => '«:value» no es un id: debe ser un número entero mayor que cero.',
        'exists' => 'El registro «:key» ya existe, y solo se crean registros nuevos.',
        'missing' => 'El registro «:key» no existe, y solo se actualizan registros existentes.',
        'database' => 'La base de datos rechazó la fila. El error se ha registrado.',
        'not_bool' => '«:field»: «:value» no es ni sí ni no.',
        'not_option' => '«:field»: no existe la opción «:value».',
        'ambiguous_option' => '«:field»: hay más de una opción llamada «:value».',
        'tree_missing' => '«:field»: no existe «:value».',
        'tree_ambiguous' => '«:field»: hay más de uno llamado «:value»: indica su ruta, «Superior / :value».',
        'image_url' => 'La dirección de la imagen no es válida: :url',
        'image_path' => 'La ruta de la imagen no está permitida: :path',
        'image_type' => 'No es una imagen (JPEG, PNG, GIF, WebP o SVG): :path',
        'image_missing' => 'La imagen :path no existe en el sitio.',
        'image_host' => 'La imagen está en una dirección de la red interna, que no se descarga: :url',
        'image_download' => 'No se pudo descargar la imagen: :url',
        'image_large' => 'La imagen pesa más de :max KB: :url',
    ],

    'console' => [
        'module' => 'Ningún módulo «:module» admite importaciones. Los que sí: :modules.',
        'source' => 'Indica el archivo o su URL.',
        'like' => 'No existe la importación número :id para este módulo.',
        'report' => 'Filas: :processed. Creados: :created, actualizados: :updated, omitidos: :skipped, errores: :failed, elementos del árbol creados: :nodes.',
        'row' => 'Fila :row: :messages',
    ],
];
