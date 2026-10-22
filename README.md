# Workout application

This application pas a part of Innowise AI course.

Application was fully created from epic specification using Claude code and Innowise accelerator tool.

## Run application

Create database, run docker containers and next commands.

```
composer run setup
composer run dev
```

## Seed the database

Both datasets fill every application table (users, profiles, share links, trainer associations, availability, coach overrides, purchase approvals, notifications, impersonation and deletion logs, audit log).

Small dataset (40 users) for daily development. `php artisan db:seed` runs it automatically in the `local` environment; elsewhere it only creates the Super Admin from `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD`:

```
php artisan migrate:fresh --seed
```

Large dataset (10,000 users, about 45 seconds) for performance checks:

```
php artisan migrate:fresh --seed --seeder=LargeUserSeeder
```

To add a dataset to an existing database without resetting it, run `php artisan db:seed --class=SmallUserSeeder` or `php artisan db:seed --class=LargeUserSeeder`.
