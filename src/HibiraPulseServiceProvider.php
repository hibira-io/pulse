<?php

namespace Hibira\Pulse;

use Hibira\Pulse\Livewire\HibiraCard;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

class HibiraPulseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hibira-pulse.php', 'hibira-pulse');

        $this->app->bind(HibiraApi::class, fn ($app) => new HibiraApi(
            $app->make(Factory::class),
            (array) $app['config']->get('hibira-pulse', []),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hibira-pulse');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'hibira-pulse');

        $this->callAfterResolving('livewire', function (LivewireManager $livewire) {
            $livewire->component('hibira.usage', HibiraCard::class);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hibira-pulse.php' => config_path('hibira-pulse.php'),
            ], 'hibira-pulse-config');
            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/hibira-pulse'),
            ], 'hibira-pulse-lang');
        }
    }
}
