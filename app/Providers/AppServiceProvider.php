<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Modules\SuppliersPricing\Contracts\FxRateProviderInterface;
use App\Modules\SuppliersPricing\Providers\OpenExchangeRatesProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FxRateProviderInterface::class, OpenExchangeRatesProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('staging')) {
            URL::forceScheme('https');
        }
    }
}
