<?php

declare(strict_types=1);

/*
 * Roles and permissions of administrators.
 */
return [
    'superadmin' => 'Superadmin',
    'superadmin_hint' => 'Może wszystko bez ról i jako jedyny zarządza rolami oraz innymi superadminami.',
    'roles' => 'Role',
    'roles_hint' => 'Administrator może wszystko, na co pozwala którakolwiek z zaznaczonych ról.',
    'forbidden' => 'Twoja rola na to nie pozwala.',
    'last_superadmin' => 'To ostatni aktywny superadmin. Ktoś musi zostać, by zarządzać rolami.',
    'saved' => 'Rola zapisana.',
    'deleted' => 'Rola usunięta.',
    'create_title' => 'Nowa rola',
    'edit_title' => 'Rola: :name',
    'name' => 'Nazwa',
    'permissions' => 'Uprawnienia',
    'section' => 'Sekcja',
    'permissions_count' => 'Uprawnienia',
    'users_count' => 'Administratorzy',
    'select_all' => 'Zaznacz wszystko',
    'clear' => 'Odznacz wszystko',
    'all' => 'Wszystko',
    'matrix_hint' => '„Podgląd” otwiera listę i rekordy tylko do odczytu. Tworzenie, edycja i usuwanie bez niego są bezużyteczne, więc zaznacza się razem z nimi.',
    'index_hint' => 'Rola to zestaw uprawnień; role nadaje się administratorom na ich stronie. Superadmin nie potrzebuje roli: może wszystko.',
    'empty' => 'Nie ma jeszcze ról.',
    'delete_title' => 'Usunąć rolę?',
    'delete_confirm' => 'administratorzy z tą rolą stracą to, na co pozwalała.',

    'actions' => [
        'view' => 'Podgląd',
        'create' => 'Tworzenie',
        'update' => 'Edycja',
        'delete' => 'Usuwanie',
        'use' => 'Wydawanie i używanie tokenów',
    ],
];
