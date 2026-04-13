<?php

use Apiato\Foundation\Apiato;
use Apiato\Http\Middleware\ProcessETag;
use Apiato\Http\Middleware\ValidateJsonContent;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\HomePageController;
use App\Containers\AppSection\Authentication\UI\WEB\Controllers\LoginController;
use App\Containers\AppSection\RequestLog\Middleware\LogRequestErrors;
use App\Ship\Middleware\ValidateAppId;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

$basePath = dirname(__DIR__);
$apiato = Apiato::configure(basePath: $basePath)->create();

// Conditionally load Telescope only when enabled (zero overhead when disabled)
$extraProviders = [
    \App\Providers\HorizonServiceProvider::class,
];

if (env('TELESCOPE_ENABLED', false)) {
    $extraProviders[] = \App\Providers\TelescopeServiceProvider::class;
}

return Application::configure(basePath: $basePath)
    ->withProviders([
        ...$apiato->providers(),
        ...$extraProviders,
    ])
    ->withEvents($apiato->events())
    ->withRouting(
        web: $apiato->webRoutes(),
        channels: __DIR__ . '/../app/Ship/Broadcasting/channels.php',
        health: '/up',
        then: static fn () => $apiato->registerApiRoutes(),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append([
            HandleCors::class,
            ValidateAppId::class,
        ]);
        // middleware aliases can be registered here if needed
        $middleware->api(append: [
            ValidateJsonContent::class,
            ProcessETag::class,
            LogRequestErrors::class,
        ]);
        $middleware->redirectUsersTo(static function (Request $request): string {
            return action(HomePageController::class);
        });
        $middleware->redirectGuestsTo(static function (Request $request): string {
            return action([LoginController::class, 'showForm']);
        });
    })
    ->withCommands($apiato->commands())
    ->withSchedule(function (Schedule $schedule) {
        // Prune Telescope entries older than 48 hours to prevent DB bloat
        if (config('telescope.enabled')) {
            $schedule->command('telescope:prune --hours=48')->daily();
        }
    })
    ->withExceptions(static function (Exceptions $exceptions) {})
    ->create();

