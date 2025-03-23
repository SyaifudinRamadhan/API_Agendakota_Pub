<?php

namespace App\Providers;

use Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Blade::directive('currencyEncode', function ($number) {
            return "<?= 'Rp '.strrev(implode('.',str_split(strrev(strval($number)),3))) ?>";
        });
        Blade::directive('currencyDecode', function ($number) {
            $data = intval(preg_replace('/,.*|[^0-9]/', '', $number));
            return "<?= $data ?>";
        });
    }
}
