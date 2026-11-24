<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'Superadmin',
    'superadmin_hint' => 'Pode fazer tudo sem papéis e é o único que gerencia papéis e outros superadmins.',
    'roles' => 'Papéis',
    'roles_hint' => 'O administrador pode fazer tudo o que qualquer um dos papéis marcados permitir.',
    'site_roles' => 'Papéis em sites específicos',
    'site_roles_hint' => 'Valem só enquanto esse site está escolhido no painel, além dos papéis acima: editar um site e apenas ver outro.',
    'forbidden' => 'Seu papel não permite isso.',
    'last_superadmin' => 'Este é o último superadmin ativo. Alguém precisa ficar para gerenciar os papéis.',
    'saved' => 'Papel salvo.',
    'deleted' => 'Papel excluído.',
    'create_title' => 'Novo papel',
    'edit_title' => 'Papel: :name',
    'name' => 'Nome',
    'permissions' => 'Permissões',
    'section' => 'Seção',
    'permissions_count' => 'Permissões',
    'users_count' => 'Administradores',
    'select_all' => 'Marcar tudo',
    'clear' => 'Desmarcar tudo',
    'all' => 'Tudo',
    'other' => 'Outros',
    'matrix_hint' => '“Ver” abre a lista e os registros somente para leitura. Criar, editar e excluir também precisam dele, por isso é marcado junto.',
    'index_hint' => 'Um papel é um conjunto de permissões; os papéis são dados aos administradores na página deles. Um superadmin não precisa de papel: pode tudo.',
    'empty' => 'Ainda não há papéis.',
    'delete_title' => 'Excluir o papel?',
    'delete_confirm' => 'os administradores com este papel perderão o que ele permitia.',

    'actions' => [
        'view' => 'Ver',
        'create' => 'Criar',
        'update' => 'Editar',
        'delete' => 'Excluir',
        'use' => 'Emitir e usar tokens',
        'settings' => 'Configurações',
        'import' => 'Importação',
        'export' => 'Exportação',
    ],
];
