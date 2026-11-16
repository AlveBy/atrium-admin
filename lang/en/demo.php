<?php

declare(strict_types=1);

/*
 * The demo modules (atrium:install --demo).
 */
return [
    'modules' => [
        'products' => 'Products',
        'catalog_sections' => 'Catalog sections',
        'brands' => 'Brands',
        'news' => 'News',
        'stores' => 'Stores',
    ],
    'create' => [
        'product' => 'Add product',
    ],
    'fields' => [
        'article' => 'Article number',
        'price' => 'Price',
        'brand' => 'Brand',
        'label' => 'Label',
        'colors' => 'Colours',
        'released_on' => 'Released',
        'in_stock' => 'In stock',
        'features' => 'Features',
        'related' => 'Goes well with',
        'specs' => 'Specifications',
        'package' => 'In the box',
        'photos' => 'Photos',
        'manual' => 'Manual',
        'logo' => 'Logo',
        'color' => 'Brand colour',
        'website' => 'Website',
        'address' => 'Address',
        'phones' => 'Phones',
        'email' => 'E-mail',
        'opens_at' => 'Opening',
        'location' => 'On the map',
    ],
    'options' => [
        'label' => [
            'new' => 'New',
            'hit' => 'Bestseller',
            'sale' => 'Sale',
        ],
        'colors' => [
            'black' => 'Black',
            'silver' => 'Silver',
            'blue' => 'Blue',
            'pink' => 'Pink',
        ],
    ],
];
