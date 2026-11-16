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
    'superadmin' => '超级管理员',
    'superadmin_hint' => '无需任何角色即可执行所有操作，并且只有超级管理员可以管理角色和其他超级管理员。',
    'roles' => '角色',
    'roles_hint' => '管理员可以执行所勾选角色中任意一个允许的操作。',
    'forbidden' => '您的角色不允许此操作。',
    'last_superadmin' => '这是最后一个启用的超级管理员。必须留下一人来管理角色。',
    'saved' => '角色已保存。',
    'deleted' => '角色已删除。',
    'create_title' => '新建角色',
    'edit_title' => '角色：:name',
    'name' => '名称',
    'permissions' => '权限',
    'section' => '模块',
    'permissions_count' => '权限数',
    'users_count' => '管理员数',
    'select_all' => '全选',
    'clear' => '全部取消',
    'all' => '全部',
    'matrix_hint' => '“查看”以只读方式打开列表和记录。创建、编辑和删除都离不开它，因此会一并勾选。',
    'index_hint' => '角色是一组权限；在管理员页面为其分配角色。超级管理员不需要角色：可以执行所有操作。',
    'empty' => '还没有角色。',
    'delete_title' => '删除该角色？',
    'delete_confirm' => '拥有此角色的管理员将失去它所允许的权限。',

    'actions' => [
        'view' => '查看',
        'create' => '创建',
        'update' => '编辑',
        'delete' => '删除',
        'use' => '签发和使用令牌',
    ],
];
