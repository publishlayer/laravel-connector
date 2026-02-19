<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Commands\DoctorCommand;
use PublishLayer\LaravelConnector\Commands\InstallCommand;

class PublishLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer.php', 'publishlayer');

        $this->app->singleton(PublishLayerClient::class, function ($app): PublishLayerClient {
            $connection = (array) config('publishlayer.connections.default', []);
            $http = (array) config('publishlayer.http', []);

            return new PublishLayerClient($app->make(HttpFactory::class), $connection, $http);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/publishlayer.php' => config_path('publishlayer.php'),
        ], 'publishlayer-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
            ]);
        }
    }
}
