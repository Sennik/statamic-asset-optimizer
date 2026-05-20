<?php

namespace Sennik\AssetOptimizer\Listeners;

use Illuminate\Support\Facades\Log;
use Sennik\AssetOptimizer\Optimizer;
use Statamic\Events\AssetUploaded;

class ResizeUploadedAsset
{
    public function __construct(private readonly Optimizer $optimizer)
    {
    }

    public function handle(AssetUploaded $event): void
    {
        if (! config('asset-optimizer.enabled', true)) {
            return;
        }

        $asset = $event->asset;
        $result = $this->optimizer->optimize($asset);

        if (! config('asset-optimizer.log', true)) {
            return;
        }

        if ($result->error) {
            Log::warning('[asset-optimizer] ' . $asset->path() . ' — ' . $result->error);
            return;
        }

        if ($result->changed) {
            Log::info(sprintf(
                '[asset-optimizer] %s: %dx%d (%s) → %dx%d (%s)',
                $asset->path(),
                $result->originalWidth,
                $result->originalHeight,
                $this->formatBytes($result->originalBytes),
                $result->newWidth,
                $result->newHeight,
                $this->formatBytes($result->newBytes)
            ));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
