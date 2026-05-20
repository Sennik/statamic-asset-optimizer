<?php

namespace Sennik\AssetOptimizer\Listeners;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Statamic\Events\AssetUploaded;

class ResizeUploadedAsset
{
    public function handle(AssetUploaded $event): void
    {
        if (! config('asset-optimizer.enabled', true)) {
            return;
        }

        $asset = $event->asset;
        $extension = strtolower($asset->extension() ?? '');

        $allowed = (array) config('asset-optimizer.formats', ['jpg', 'jpeg', 'png', 'webp']);
        if (! in_array($extension, $allowed, true)) {
            return;
        }

        $containers = config('asset-optimizer.containers');
        if (is_array($containers) && ! in_array($asset->container()->handle(), $containers, true)) {
            return;
        }

        $originalSize = (int) ($asset->size() ?? 0);
        $minSize = (int) config('asset-optimizer.min_size_bytes', 100 * 1024);
        if ($originalSize > 0 && $originalSize < $minSize) {
            return;
        }

        try {
            $contents = $asset->disk()->get($asset->path());
        } catch (\Throwable $e) {
            Log::warning('[asset-optimizer] kon bestand niet lezen: ' . $asset->path() . ' — ' . $e->getMessage());
            return;
        }

        if (! $contents) {
            return;
        }

        $manager = new ImageManager($this->driver());

        try {
            $image = $manager->read($contents);
        } catch (\Throwable $e) {
            Log::warning('[asset-optimizer] kon afbeelding niet lezen: ' . $asset->path() . ' — ' . $e->getMessage());
            return;
        }

        $originalWidth = $image->width();
        $originalHeight = $image->height();
        $changed = false;

        // Cap op de langste zijde (longest-edge)
        $maxDimension = config('asset-optimizer.max_dimension');
        if ($maxDimension && max($originalWidth, $originalHeight) > $maxDimension) {
            $image->scaleDown(width: (int) $maxDimension, height: (int) $maxDimension);
            $changed = true;
        }

        // Secundaire vangnet: totaal aantal pixels
        $maxPixels = config('asset-optimizer.max_pixels');
        if ($maxPixels) {
            $w = $image->width();
            $h = $image->height();
            if ($w * $h > $maxPixels) {
                $ratio = sqrt($maxPixels / ($w * $h));
                $newW = (int) floor($w * $ratio);
                $newH = (int) floor($h * $ratio);
                $image->scaleDown(width: $newW, height: $newH);
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        $quality = (int) config('asset-optimizer.quality', 82);

        try {
            $encoded = match ($extension) {
                'jpg', 'jpeg' => $image->toJpeg(quality: $quality),
                'png' => $image->toPng(),
                'webp' => $image->toWebp(quality: $quality),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('[asset-optimizer] kon encoderen niet: ' . $asset->path() . ' — ' . $e->getMessage());
            return;
        }

        if ($encoded === null) {
            return;
        }

        $newBytes = (string) $encoded;

        try {
            $asset->disk()->put($asset->path(), $newBytes);
            // Refresh metadata zodat width/height/size in de asset-meta worden bijgewerkt
            $asset->save();
        } catch (\Throwable $e) {
            Log::warning('[asset-optimizer] kon opslaan niet: ' . $asset->path() . ' — ' . $e->getMessage());
            return;
        }

        if (config('asset-optimizer.log', true)) {
            Log::info(sprintf(
                '[asset-optimizer] %s: %dx%d (%s) → %dx%d (%s)',
                $asset->path(),
                $originalWidth,
                $originalHeight,
                $this->formatBytes($originalSize),
                $image->width(),
                $image->height(),
                $this->formatBytes(strlen($newBytes))
            ));
        }
    }

    private function driver(): GdDriver|ImagickDriver
    {
        return match (strtolower((string) config('asset-optimizer.driver', 'gd'))) {
            'imagick' => new ImagickDriver(),
            default => new GdDriver(),
        };
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
