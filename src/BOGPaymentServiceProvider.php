<?php

namespace Zorb\BOGPayment;

use Illuminate\Support\ServiceProvider;

class BOGPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->publishes([
            __DIR__ . '/config/bogpayment.php' => config_path('bogpayment.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/config/bogpayment.php', 'bogpayment');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(BOGPayment::class);
    }
}
