<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\DateColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable implements Administrable
{
    use HasApiTokens, HasFactory, InteractsWithAdmin, Notifiable;

    protected $table = 'admin_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_sent_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
        ];
    }

    /**
     * Get admin label for this model
     */
    public static function getAdminLabel(): string
    {
        return __('atrium::menu.administrators');
    }

    /**
     * The routes are admin.users.*, not the admin.admin_users.* the class name gives.
     */
    public static function getAdminRoutePrefix(): string
    {
        return 'users';
    }

    /**
     * Get fields for admin form
     */
    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')
                ->label(__('atrium::account.users.fields.name'))
                ->required()
                ->maxLength(255),

            TextField::make('email')
                ->label(__('atrium::account.users.fields.email'))
                ->required()
                ->email()
                ->unique('admin_users', 'email'),

            TextField::make('password')
                ->label(__('atrium::account.users.fields.password'))
                ->password()
                ->required()
                ->minLength(8)
                ->hideOnEdit(),

            TextField::make('password_confirmation')
                ->label(__('atrium::account.users.fields.password_confirmation'))
                ->password()
                ->required()
                ->hideOnEdit(),

            CheckboxField::make('is_active')
                ->label(__('atrium::account.users.fields.is_active'))
                ->default(true),
        ];
    }

    /**
     * Get columns for admin list
     */
    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label(__('atrium::account.users.fields.id'))
                ->sortable()
                ->width('80px'),

            TextColumn::make('name')
                ->label(__('atrium::account.users.fields.name'))
                ->sortable()
                ->searchable(),

            TextColumn::make('email')
                ->label(__('atrium::account.users.fields.email'))
                ->sortable()
                ->searchable(),

            BooleanColumn::make('is_active')
                ->label(__('atrium::account.users.fields.is_active'))
                ->sortable(),

            DateColumn::make('created_at')
                ->label(__('atrium::account.users.fields.created_at'))
                ->sortable()
                ->format('d.m.Y H:i'),
        ];
    }

    /**
     * Get validation rules for create
     */
    public static function getCreateValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admin_users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get validation rules for update
     */
    public static function getUpdateValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get admin title for edit form
     */
    public function getAdminTitle(): string
    {
        return $this->name;
    }
}
