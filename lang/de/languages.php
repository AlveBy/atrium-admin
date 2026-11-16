<?php

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'create' => 'Sprache hinzufügen',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'active' => 'Aktiv',
        'is_default' => 'Standard',
        'sort' => 'Sortierung',
    ],
    'hints' => [
        'name' => 'So sehen Besucher sie in der Sprachauswahl: English, Deutsch, Español.',
        'code' => 'Zwei oder drei Kleinbuchstaben, optional mit Region: en, de, pt-br. Er bildet den Anfang der URL (/de/…) und lässt sich später nicht mehr ändern.',
        'code_fixed' => 'Unter diesem Code sind die Übersetzungen gespeichert und die URLs beginnen damit, deshalb lässt er sich nicht ändern.',
        'is_default' => 'Ihre Seiten haben kein Sprachpräfix in der URL, und ihr Text wird überall dort angezeigt, wo eine Übersetzung fehlt.',
    ],
    'switch' => 'Sprache der Inhalte',
];
