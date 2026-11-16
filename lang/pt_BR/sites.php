<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Adicionar site',
    'add_domain' => 'Adicionar domínio',
    'fields' => [
        'name' => 'Nome',
        'code' => 'Código',
        'domains' => 'Domínios',
        'languages' => 'Idiomas',
        'default_language' => 'Idioma padrão',
        'active' => 'Ativo',
        'is_default' => 'Site padrão',
        'sort' => 'Ordem',
    ],
    'hints' => [
        'code' => 'Letras latinas minúsculas, dígitos e hifens: main, shop, de. Não pode ser alterado depois.',
        'domains' => 'O site abre nestes domínios: example.com, www.example.com. O primeiro é o principal.',
        'languages' => 'Os idiomas de conteúdo deste site. Nenhum selecionado — todos os idiomas ativos.',
        'default_language' => 'As páginas dele não têm prefixo de idioma na URL. Vazio — o idioma padrão de todo o painel.',
        'is_default' => 'Abre em qualquer domínio que nenhum outro site reivindique, e em localhost.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” não é um nome de domínio.',
        'domain_taken' => 'O domínio :domain já é usado pelo site “:site”.',
    ],
];
