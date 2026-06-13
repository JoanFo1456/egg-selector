<?php

namespace JoanFo\EggSelector\Providers;

use Illuminate\Support\ServiceProvider;

class EggSelectorProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->callAfterResolving('translator', function ($translator) {
            $translator->getLoader()->addPath(plugin_path('egg-selector', 'lang'));
        });
    }
}
