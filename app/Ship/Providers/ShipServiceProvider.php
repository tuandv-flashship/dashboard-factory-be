<?php

namespace App\Ship\Providers;

use App\Ship\Parents\Models\Model;
use App\Ship\Parents\Models\UserModel;
use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

final class ShipServiceProvider extends ParentServiceProvider
{
    private const USER_ID_ROUTE_PATTERN = '[A-Za-z0-9]+';

    public function boot(): void
    {
        $this->registerRoutePatterns();
        $this->registerMacros();
        RequestException::dontTruncate();
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        UserModel::shouldBeStrict(! $this->app->isProduction());
    }

    private function registerRoutePatterns(): void
    {
        Route::pattern('user_id', self::USER_ID_ROUTE_PATTERN);
    }

    public function registerMacros(): void
    {
        /*
         * Get the App-Identifier header value from the request or use the default app.
         */
        Request::macro('appId', function (): string {
            return $this->header('App-Identifier', config()->string('apiato.defaults.app'));
        });
    }
}
