<?php

namespace Xbhub\Filter;

use Xbhub\Filter\Commands\FilterBagMakeCommand;
use Xbhub\Filter\Commands\FilterMakeCommand;
use Illuminate\Support\ServiceProvider;

class FilterServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FilterMakeCommand::class,
                FilterBagMakeCommand::class,
            ]);
        }
    }
}
