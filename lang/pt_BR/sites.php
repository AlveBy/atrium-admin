<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Adicionar site',
    'tabs' => [
        'site' => 'Site',
        'codes' => 'Contadores e códigos',
    ],
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
        'google_tag_id' => 'Google Tag ID',
        'yandex_metrika_id' => 'Contador do Yandex Metrica',
        'yandex_webvisor' => 'Replay de sessões (Webvisor)',
        'counters_skip_admins' => 'Não contar administradores',
        'google_verification' => 'Google Search Console',
        'yandex_verification' => 'Yandex Webmaster',
        'bing_verification' => 'Bing Webmaster Tools',
        'head_code' => 'Código em <head>',
        'body_start_code' => 'Código no início de <body>',
        'body_end_code' => 'Código antes de </body>',
    ],
    'hints' => [
        'code' => 'Letras latinas minúsculas, dígitos e hifens: main, shop, de. Não pode ser alterado depois.',
        'domains' => 'O site abre nestes domínios: example.com, www.example.com. O primeiro é o principal.',
        'languages' => 'Os idiomas de conteúdo deste site. Nenhum selecionado — todos os idiomas ativos.',
        'default_language' => 'As páginas dele não têm prefixo de idioma na URL. Vazio — o idioma padrão de todo o painel.',
        'is_default' => 'Abre em qualquer domínio que nenhum outro site reivindique, e em localhost.',
        'google_tag_id' => 'G-XXXXXXXXXX para o Google Analytics 4 ou GTM-XXXXXXX para o Tag Manager. Apenas o ID: o código do contador é adicionado a todas as páginas automaticamente.',
        'yandex_metrika_id' => 'Apenas o número do contador nas configurações do Metrica: o código é adicionado a todas as páginas automaticamente.',
        'counters_skip_admins' => 'Administradores logados recebem as páginas sem Google Tag e Yandex Metrica, para que as próprias visitas não distorçam as estatísticas. As tags de verificação e o código abaixo sempre são exibidos.',
        'verification' => 'O código da metatag :name. Você pode colar a tag inteira.',
        'snippet' => 'Adicionado como está a todas as páginas deste site: um widget de chat, um pixel, qualquer outro contador.',
        'robots_txt' => 'Vazio — os robôs podem indexar tudo. Uma linha “Sitemap:” com o sitemap.xml deste site é adicionada se não houver.',
        'robots_not_production' => 'Este servidor não é de produção (APP_ENV=:env), então o robots.txt proíbe a indexação, seja qual for o texto aqui.',
        'robots_file_exists' => 'O arquivo public/robots.txt existe: o servidor web o retorna no lugar deste texto. Exclua o arquivo.',
    ],
    'errors' => [
        'domain_invalid' => '“:domain” não é um nome de domínio.',
        'domain_taken' => 'O domínio :domain já é usado pelo site “:site”.',
    ],
];
