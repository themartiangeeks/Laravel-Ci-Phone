<?php

namespace Martiangeeks\LaravelCiPhone;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use libphonenumber\PhoneNumberUtil;

class OperatorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('libphonenumber', function ($app) {
            return PhoneNumberUtil::getInstance();
        });

        $this->app->alias('libphonenumber', PhoneNumberUtil::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['validator']->extendDependent('ci_phone', Validation\Phone::class . '@validate');

        Rule::macro('ci_phone', function () {
            return new Rules\Phone;
        });
    }
}
