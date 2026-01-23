<?php

namespace Acoustica\StatamicSupport;

use Acoustica\StatamicSupport\Contracts\SupportProvider;
use Acoustica\StatamicSupport\Listeners\HandleSupportFormSubmission;
use Acoustica\StatamicSupport\Providers\NullProvider;
use Acoustica\StatamicSupport\Services\SpamValidationService;
use Illuminate\Support\Facades\Event;
use Statamic\Events\FormSubmitted;
use Statamic\Providers\AddonServiceProvider;

class SupportServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'statamic-support';

    protected $commands = [
        Console\InstallCommand::class,
    ];

    protected $listen = [
        FormSubmitted::class => [
            HandleSupportFormSubmission::class,
        ],
    ];

    public function bootAddon()
    {
        $this->registerPublishables();
    }

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/support.php', 'support');

        $this->app->singleton(SpamValidationService::class, function ($app) {
            return new SpamValidationService(config('support.spam'));
        });

        $this->app->singleton(SupportProvider::class, function ($app) {
            return $this->resolveProvider();
        });
    }

    protected function resolveProvider(): SupportProvider
    {
        $driver = config('support.provider', 'null');
        $providers = config('support.providers', []);

        if (!isset($providers[$driver])) {
            return new NullProvider();
        }

        $providerConfig = $providers[$driver];
        $providerClass = $providerConfig['driver'] ?? NullProvider::class;

        if (!class_exists($providerClass)) {
            return new NullProvider();
        }

        return new $providerClass($providerConfig);
    }

    protected function registerPublishables()
    {
        $this->publishes([
            __DIR__ . '/../config/support.php' => config_path('support.php'),
        ], 'statamic-support-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/statamic-support'),
        ], 'statamic-support-views');

        $this->publishes([
            __DIR__ . '/../resources/blueprints' => resource_path('blueprints'),
        ], 'statamic-support-blueprints');

        $this->publishes([
            __DIR__ . '/../resources/forms' => resource_path('forms'),
        ], 'statamic-support-forms');

        $this->publishes([
            __DIR__ . '/../resources/fieldsets' => resource_path('fieldsets'),
        ], 'statamic-support-fieldsets');

        $this->publishes([
            __DIR__ . '/../config/support.php' => config_path('support.php'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/statamic-support'),
            __DIR__ . '/../resources/blueprints' => resource_path('blueprints'),
            __DIR__ . '/../resources/forms' => resource_path('forms'),
            __DIR__ . '/../resources/fieldsets' => resource_path('fieldsets'),
        ], 'statamic-support');
    }
}
