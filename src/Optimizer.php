<?php

namespace Sennik\AssetOptimizer;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Statamic\Contracts\Assets\Asset;

class Optimizer
{
    /**
     * Optimaliseer één asset volgens de configuratie.
     *
     * @param  Asset  $asset
     * @param  bool   $force   negeer min_size_bytes
     * @param  bool   $dryRun  bereken alleen, schrijf niet
     */
    public function optimize(Asset $asset, bool $force = false, bool $dryRun = false): OptimizationResult
    {
        $extension = strtolower($asset->extension() ?? '');

        $allowed = (array) config('asset-optimizer.formats', ['jpg', 'jpeg', 'png', 'webp']);
        if (! in_array($extension, $allowed, true)) {
            return OptimizationResult::skipped('format');
        }

        $containers = config('asset-optimizer.containers');
        if (is_array($containers) && ! in_array($asset->container()->handle(), $containers, true)) {
            return OptimizationResult::skipped('container');
        }

        $originalSize = (int) ($asset->size() ?? 0);

        if (! $force) {
            $minSize = (int) config('asset-optimizer.min_size_bytes', 100 * 1024);
            if ($originalSize > 0 && $originalSize < $minSize) {
                return OptimizationResult::skipped('min-size');
            }
        }

        try {
            $contents = $asset->disk()->get($asset->path());
        } catch (\Throwable $e) {
            return OptimizationResult::failed('read: ' . $e->getMessage());
        }

        if (! $contents) {
            return OptimizationResult::failed('empty-contents');
        }

        $manager = new ImageManager($this->driver());

        try {
            $image = $manager->read($contents);
        } catch (\Throwable $e) {
            return OptimizationResult::failed('decode: ' . $e->getMessage());
        }

        $originalWidth = $image->width();
        $originalHeight = $image->height();
        $changed = false;

        // Cap op de langste zijde
        $maxDimension = config('asset-optimizer.max_dimension');
        if ($maxDimension && max($originalWidth, $originalHeight) > $maxDimension) {
            $image->scaleDown(width: (int) $maxDimension, height: (int) $maxDimension);
            $changed = true;
        }

        // Secundair vangnet: totaal aantal pixels
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
            return OptimizationResult::skipped('no-change');
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
            return OptimizationResult::failed('encode: ' . $e->getMessage());
        }

        if ($encoded === null) {
            return OptimizationResult::failed('encode: unsupported format');
        }

        $newBytes = (string) $encoded;

        if ($dryRun) {
            return new OptimizationResult(
                changed: true,
                reason: 'dry-run',
                originalBytes: $originalSize,
                newBytes: strlen($newBytes),
                originalWidth: $originalWidth,
                originalHeight: $originalHeight,
                newWidth: $image->width(),
                newHeight: $image->height(),
            );
        }

        try {
            $asset->disk()->put($asset->path(), $newBytes);
            $asset->save();
        } catch (\Throwable $e) {
            return OptimizationResult::failed('write: ' . $e->getMessage());
        }

        return new OptimizationResult(
            changed: true,
            reason: 'resized',
            originalBytes: $originalSize,
            newBytes: strlen($newBytes),
            originalWidth: $originalWidth,
            originalHeight: $originalHeight,
            newWidth: $image->width(),
            newHeight: $image->height(),
        );
    }

    private function driver(): GdDriver|ImagickDriver
    {
        return match (strtolower((string) config('asset-optimizer.driver', 'gd'))) {
            'imagick' => new ImagickDriver(),
            default => new GdDriver(),
        };
    }
}
