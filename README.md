# PrintDash Dashboard API

Backend API cho Dashboard giám sát sản xuất realtime xưởng in áo PrintDash.

**Stack**: Laravel Apiato (Porto SAP) + Passport + Reverb (WebSocket)

## Get Started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan passport:client --password --name="Dashboard Web Client"
```

### Seed initial data

```bash
FORCE_SETTINGS_SEED=true php artisan db:seed --class=App\\Containers\\AppSection\\Setting\\Data\\Seeders\\SettingsSeeder_1
```

### Run development server

```bash
php artisan serve          # API → http://localhost:8000
php artisan reverb:start   # WebSocket → ws://localhost:8080
```
