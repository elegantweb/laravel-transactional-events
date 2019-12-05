<?php

namespace Elegant\Events;

use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config' => config_path()], 'laravel-transactional-events-config');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/transactional-events.php', 'transactional-events');

        $this->app->extend('events', function ($service, $app) {
            $dispatcher = new TransactionalDispatcher($service);
            $dispatcher->includeEvents($app['config']->get('transactional-events.include'));
            $dispatcher->excludeEvents($app['config']->get('transactional-events.exclude'));
            return $dispatcher;
        });
    }
}
