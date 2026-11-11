<?php

namespace Hibira\Pulse\Tests;

use Hibira\Pulse\HibiraPulseServiceProvider;
use Laravel\Pulse\PulseServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, PulseServiceProvider::class, HibiraPulseServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('pulse.enabled', false);
        $app['config']->set('hibira-pulse.token', 'hkb_pat_test');
        $app['config']->set('hibira-pulse.app_id', 'app_1');
        $app['config']->set('hibira-pulse.key', 'key1');
        $app['config']->set('hibira-pulse.secret', 'secret1');
    }
}
