<?php

declare(strict_types=1);

return [
    'create' => 'Add site',
    'add_domain' => 'Add domain',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'domains' => 'Domains',
        'languages' => 'Languages',
        'default_language' => 'Default language',
        'active' => 'Active',
        'is_default' => 'Default site',
        'sort' => 'Sort order',
    ],
    'hints' => [
        'code' => 'Lowercase Latin letters, digits, dashes: main, shop, de. Cannot be changed later.',
        'domains' => 'The site opens on these domains: example.com, www.example.com. The first one is the main one.',
        'languages' => 'The content languages of this site. None selected — all active languages.',
        'default_language' => 'Its pages have no language prefix in the address. Empty — the panel-wide default language.',
        'is_default' => 'Opens on any domain no other site claims, and on localhost.',
    ],
    'errors' => [
        'domain_invalid' => '":domain" is not a domain name.',
        'domain_taken' => 'The domain :domain is already used by the site ":site".',
    ],
];
