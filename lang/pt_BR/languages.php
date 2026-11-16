<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Adicionar idioma',
    'fields' => [
        'name' => 'Nome',
        'code' => 'Código',
        'active' => 'Ativo',
        'is_default' => 'Padrão',
        'sort' => 'Ordem',
    ],
    'hints' => [
        'name' => 'Como os visitantes o verão no seletor de idioma: English, Deutsch, Español.',
        'code' => 'Duas ou três letras minúsculas, opcionalmente com região: en, de, pt-br. Ele vira o início da URL (/de/…) e não pode ser alterado depois.',
        'code_fixed' => 'As traduções são salvas com este código e as URLs começam com ele, por isso ele não pode ser alterado.',
        'is_default' => 'As páginas dele não têm prefixo de idioma na URL, e o texto dele aparece onde falta tradução.',
    ],
    'switch' => 'Idioma do conteúdo',
];
