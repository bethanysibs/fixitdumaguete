# FixItDumaguete

Local home services marketplace for Dumaguete City, Sibulan, and Dauin. This repository is a single Laravel application. Public pages are server-rendered Blade views. Bootstrap 5, Bootstrap Icons, and jQuery are compiled by Vite. There is no separate frontend app.

Phase 1 is the project foundation: branding, the design system, the public layout, and the homepage. Accounts, bookings, and payments are not built yet.

## Runtime

Use PHP 8.4 from your user account, not the PHP 8.0 binary shipped with XAMPP. Laravel 13 requires PHP 8.3 or newer. XAMPP still provides MariaDB.

PHP, Composer, and the Laravel installer are installed at:

```text
~/.config/herd-lite/bin
```

`~/.zprofile` adds that directory to `PATH`. Open a new terminal after the first install, then check:

```bash
php -v
composer --version
node -v
npm -v
```

Expected local versions used for this project: PHP 8.4.1, Composer 2.8, Node 22, Laravel 13.

## Database

The application expects the XAMPP MariaDB server on `127.0.0.1:3306`, database `fixitdumaguete`, user `root`, and the XAMPP socket. The password in `.env` is empty because that is the local XAMPP default. Do not use that account outside this machine.

Start MariaDB from the XAMPP manager, or from a terminal:

```bash
sudo /Applications/XAMPP/xamppfiles/xampp startmysql
```

Create the database:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS fixitdumaguete CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Run migrations after MariaDB is running:

```bash
php artisan migrate
```

Until that database exists, sessions, cache, and queues stay on local files and the sync driver so the homepage can run. After `php artisan migrate` succeeds, you may set these in `.env` and reload:

```text
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Mail uses the `log` driver. Messages are written to `storage/logs/laravel.log`. No mail is sent.

## First-time setup

From the project directory:

```bash
composer install
npm install
npm run build
php artisan storage:link
php artisan migrate
```

Copy `.env.example` to `.env` first if `.env` is missing, then generate the key:

```bash
php artisan key:generate
```

## Day-to-day commands

```bash
php artisan serve
npm run dev
php artisan test
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

Open the site at [http://127.0.0.1:8000](http://127.0.0.1:8000).

Serve the app with `php artisan serve`. Do not point XAMPP Apache at the project root. That would expose `.env` and would also run Laravel on PHP 8.0. If you later use Apache, its document root must be the `public` directory and its PHP version must be 8.3 or newer.

`npm run dev` recompiles CSS and JavaScript while you edit. `npm run build` writes the files the homepage test expects in `public/build`.

## Git

`.env` is ignored. Do not commit it. Initialize the repository with `git init` if this folder is not already a repository.

## Not in this phase

Registration, login, roles, worker verification, service and location records, search, bookings, payments, reviews, the administrator dashboard, location landing pages, and the XML sitemap are later phases.
