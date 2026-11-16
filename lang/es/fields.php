<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'clear' => 'Borrar',

    'content' => [
        'mode_text' => 'Texto',
        'mode_html' => 'HTML',
        'mode_wysiwyg' => 'Editor visual',
    ],

    'editor' => [
        'placeholder' => 'Empieza a escribir…',
        'format' => 'Estilo de texto',
        'format_paragraph' => 'Texto normal',
        'format_h1' => 'Título 1',
        'format_h2' => 'Título 2',
        'format_h3' => 'Título 3',
        'format_h4' => 'Título 4',
        'format_h5' => 'Título 5',
        'format_h6' => 'Título 6',
        'format_blockquote' => 'Cita',
        'font_size' => 'Tamaño de fuente',
        'font_size_default' => 'Tamaño',
        'font_size_pt' => ':size pt',
        'text_color' => 'Color del texto',
        'bold' => 'Negrita',
        'bold_shortcut' => 'Negrita (Ctrl+B)',
        'italic' => 'Cursiva',
        'italic_shortcut' => 'Cursiva (Ctrl+I)',
        'underline' => 'Subrayado',
        'underline_shortcut' => 'Subrayado (Ctrl+U)',
        'strike' => 'Tachado',
        'link' => 'Enlace',
        'link_prompt' => 'URL del enlace:',
        'bullet_list' => 'Lista con viñetas',
        'ordered_list' => 'Lista numerada',
        'heading' => 'Título',
        'image' => 'Imagen',
        'file' => 'Archivo (PDF, DOC, XLS)',
        'align_left' => 'Alinear a la izquierda',
        'align_center' => 'Centrar',
        'align_right' => 'Alinear a la derecha',
        'align_justify' => 'Justificar',
        'table_insert' => 'Insertar tabla',
        'table_add_row' => 'Añadir fila',
        'table_delete_row' => 'Eliminar fila',
        'table_add_column' => 'Añadir columna',
        'table_delete_column' => 'Eliminar columna',
        'table_merge_split' => 'Combinar o dividir celdas',
        'table_delete' => 'Eliminar tabla',
    ],

    'upload' => [
        'from_library' => 'Desde la biblioteca',
        'progress' => 'Subiendo… :percent %',
        'too_big' => 'El archivo supera el tamaño máximo (:max KB).',
        'too_large' => 'El archivo es demasiado grande: el servidor ha rechazado la subida.',
        'network_error' => 'No se pudo conectar con el servidor. Comprueba tu conexión.',
        'aborted' => 'Subida cancelada.',
        'no_url' => 'El servidor no ha devuelto ningún enlace al archivo subido.',
        'http_error' => 'Error al subir el archivo (HTTP :status).',
        'failed' => 'No se pudo subir el archivo.',
        'request_failed' => 'La solicitud ha fallado',
        'session_expired' => 'Tu sesión ha caducado. Actualiza la página y vuelve a iniciar sesión.',
    ],

    'gallery' => [
        'add_from_library' => 'Añadir desde la biblioteca',
        'counter' => ':current / :count imagen|:current / :count imágenes',
        'file_too_big' => 'El archivo :name supera el tamaño máximo de :max KB',
        'upload_failed' => 'No se pudo subir el archivo :name',
    ],

    'belongs_to_many' => [
        'clear_all' => 'Borrar todo',
        'no_options' => 'No hay opciones disponibles',
    ],

    'map' => [
        'latitude' => 'Latitud',
        'longitude' => 'Longitud',
        'open_on_map' => 'Abrir en el mapa',
        'parse_error' => 'No se pudieron leer las coordenadas. Formato esperado: latitud,longitud; por ejemplo, :example',
    ],

    'repeatable' => [
        'item' => 'Elemento',
        'item_title' => ':label #:number',
        'empty' => 'No hay elementos. Haz clic en «:add» para añadir uno.',
        'move_up' => 'Subir',
        'move_down' => 'Bajar',
    ],

    'slug' => [
        'placeholder' => 'url-slug',
        'generate' => 'Generar a partir del nombre',
        'url_label' => 'URL:',
    ],

    'tree' => [
        'search_placeholder' => 'Buscar…',
        'top_level' => 'Nivel superior',
    ],

    // The standard fields every Bitrix iblock has; the migrator labels
    // generated models with them.
    'standard' => [
        'id' => 'ID',
        'name' => 'Nombre',
        'code' => 'Código',
        'active' => 'Activo',
        'active_from' => 'Activo desde',
        'active_to' => 'Activo hasta',
        'sort' => 'Orden',
        'section' => 'Sección',
        'parent_section' => 'Sección superior',
        'preview_picture' => 'Imagen de vista previa',
        'preview_text' => 'Texto de vista previa',
        'detail_picture' => 'Imagen de detalle',
        'detail_text' => 'Texto de detalle',
        'description' => 'Descripción',
        'picture' => 'Imagen',
        'created_at' => 'Creado',
        'updated_at' => 'Modificado',
    ],

    'tabs' => [
        'label' => 'Pestañas',
        'main' => 'General',
        'section' => 'Sección',
        'seo' => 'SEO',
        'additional' => 'Adicional',
        'custom_fields' => 'Campos personalizados',
        'element_properties' => 'Propiedades de los elementos',
        'preview' => 'Vista previa',
        'detail' => 'Detalle',
        'sections' => 'Secciones',
        'description' => 'Descripción',
    ],

    'validation' => [
        'own_ancestor' => 'Una sección no puede colocarse dentro de sí misma ni de una de sus subsecciones.',
    ],
];
