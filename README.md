# Atrium

One admin panel for any number of multilingual sites, built on Laravel 13.

You describe a module once, as an Eloquent model with its fields and list columns. Atrium gives it a list with search and sorting, a form with tabs, a menu entry, permissions, an HTTP API and tools for AI assistants over MCP. The admin comes prebuilt: Node is not needed.

## Install

Into a Laravel 13 app, on PHP 8.3 or newer:

```bash
composer require alveby/atrium
php artisan atrium:install
```

The installer publishes the config and the admin bundle, runs the migrations and asks for the first administrator. The admin is at `/admin`. After the password it asks for a code sent by email, so set up mail first; on a local machine with `MAIL_MAILER=log` the code is at the end of `storage/logs/laravel.log`.

## A module

```bash
php artisan atrium:make-module Article
php artisan migrate
```

writes the model, its migration and a line in `config/atrium.php` → `resources`, and "Articles" is in the menu.

## Documentation

- [Installation](https://github.com/AlveBy/atrium/blob/main/docs/installation.md)
- [Configuration](https://github.com/AlveBy/atrium/blob/main/docs/configuration.md)
- [Your own module](https://github.com/AlveBy/atrium/blob/main/docs/modules.md)
- [Moving from 1C-Bitrix](https://github.com/AlveBy/atrium/blob/main/docs/bitrix.md)

The public theme is [`alveby/atrium-theme`](https://github.com/AlveBy/atrium-theme), the 1C-Bitrix migrator is [`alveby/atrium-bitrix`](https://github.com/AlveBy/atrium-bitrix).

## Contributing

This repository is a read-only copy of `packages/admin` in [AlveBy/atrium](https://github.com/AlveBy/atrium). Issues and pull requests go there.

## License

MIT
