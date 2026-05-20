<?php

namespace Sennik\AssetOptimizer;

use Statamic\Events\AssetUploaded;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $listen = [
        AssetUploaded::class => [
            Listeners\ResizeUploadedAsset::class,
        ],
    ];

    public function bootAddon(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/asset-optimizer.php',
            'asset-optimizer'
        );

        $this->publishes([
            __DIR__ . '/../config/asset-optimizer.php' => config_path('asset-optimizer.php'),
        ], 'asset-optimizer-config');
    }
}
