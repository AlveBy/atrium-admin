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
    'superadmin_hint' => 'Hech qanday rolsiz hamma narsani qila oladi. Rollarni va boshqa superadminlarni faqat superadmin boshqaradi.',
    'roles' => 'Rollar',
    'roles_hint' => 'Administrator belgilangan rollardan birortasi ruxsat bergan hamma narsani qila oladi.',
    'forbidden' => 'Rolingiz bunga ruxsat bermaydi.',
    'last_superadmin' => 'Bu oxirgi faol superadmin. Rollarni boshqarish uchun kimdir qolishi kerak.',
    'saved' => 'Rol saqlandi.',
    'deleted' => 'Rol oʻchirildi.',
    'create_title' => 'Yangi rol',
    'edit_title' => 'Rol: :name',
    'name' => 'Nomi',
    'permissions' => 'Huquqlar',
    'section' => 'Boʻlim',
    'permissions_count' => 'Huquqlar',
    'users_count' => 'Administratorlar',
    'select_all' => 'Hammasini belgilash',
    'clear' => 'Hammasini olib tashlash',
    'all' => 'Hammasi',
    'other' => 'Boshqa',
    'matrix_hint' => '“Koʻrish” roʻyxat va yozuvlarni faqat oʻqish uchun ochadi. Yaratish, tahrirlash va oʻchirish usiz foydasiz, shuning uchun u ular bilan birga belgilanadi.',
    'index_hint' => 'Rol — huquqlar toʻplami. Rollar administratorlarga ularning sahifasida beriladi. Superadminga rol kerak emas: unga hamma narsa mumkin.',
    'empty' => 'Hozircha rollar yoʻq.',
    'delete_title' => 'Rol oʻchirilsinmi?',
    'delete_confirm' => 'bu roldagi administratorlar u ruxsat bergan narsalardan mahrum boʻladi.',

    'actions' => [
        'view' => 'Koʻrish',
        'create' => 'Yaratish',
        'update' => 'Tahrirlash',
        'delete' => 'Oʻchirish',
        'use' => 'Tokenlar chiqarish va ishlatish',
        'settings' => 'Sozlamalar',
    ],
];
