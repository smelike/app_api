<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Handlers\Handler\Handler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        //print_r("123");
        $this->registerWorkermanHandle();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    protected function registerWorkermanHandle()
    {
        $this->app->singleton('wk_handle', function () {

            return new Handler('0.0.0.0:80');
        });

    }
}
