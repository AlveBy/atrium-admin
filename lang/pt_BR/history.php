<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

// Machine-translated from English; needs review by a native speaker.

return [
    'title' => 'Registro de alterações',
    'subtitle' => 'Quem alterou o quê e quando, em todos os módulos que você pode abrir.',
    'record_title' => 'Histórico: :title',
    'button' => 'Histórico',
    'empty' => 'Nada foi registrado ainda.',
    'filters' => [
        'module' => 'Módulo',
        'admin' => 'Administrador',
        'from' => 'De',
        'to' => 'Até',
        'all' => 'Todos',
        'apply' => 'Aplicar',
        'reset' => 'Limpar',
    ],
    'events' => [
        'created' => 'Criado',
        'updated' => 'Alterado',
        'deleted' => 'Excluído',
        'restored' => 'Restaurado',
    ],
    'sources' => [
        'admin' => 'no painel',
        'api' => 'via API',
        'import' => 'por importação',
        'console' => 'por um comando',
        'schedule' => 'pelo agendador',
    ],
    'system' => 'Sistema',
    'field' => 'Campo',
    'old' => 'Antes',
    'new' => 'Agora',
    'secret' => 'alterado (o valor não é guardado)',
    'empty_value' => 'vazio',
    'yes' => 'sim',
    'no' => 'não',
    'back' => 'Voltar ao registro',
    'versions' => [
        'title' => 'Versões',
        'hint' => 'O que o formulário continha após cada salvamento. Compare duas versões ou restaure uma anterior.',
        'baseline' => 'Antes do início do histórico',
        'current' => 'atual',
        'from' => 'Comparar',
        'to' => 'com',
        'compare' => 'Comparar',
        'same' => 'As duas versões são iguais.',
        'restore' => 'Restaurar',
        'confirm_title' => 'Restaurar esta versão?',
        'confirm' => 'O registro recebe os valores desta versão. O que ele contém agora fica aqui como uma versão própria.',
        'restored' => 'A versão foi restaurada.',
        'failed' => 'Não foi possível restaurar a versão: :errors',
    ],
];
