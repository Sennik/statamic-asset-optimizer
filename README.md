# Statamic Asset Optimizer

Automatically resize and re-encode images on upload in Statamic. Works for landscape, portrait, and square images using longest-edge sizing — one setting covers every aspect ratio.

## Why

Editors upload originals straight from their camera or phone. A single 8 MB / 6000-pixel hero image kills mobile performance and bloats your storage. This addon hooks into Statamic's `AssetUploaded` event and shrinks the file in place before it's ever served.

Pair it with Glide for responsive serving and a typical homepage drops from ~10 MB of images to under 1 MB.

## What it does

For every image upload that meets your filters:

1. Reads the asset from its disk
2. Scales it down so its longest edge fits within a maximum (default: 2400 px)
3. Optionally caps total pixels for extreme aspect ratios (panoramas, tall portraits)
4. Re-encodes JPEG/WebP at your chosen quality, PNG losslessly
5. Writes the optimised file back over the original
6. Refreshes Statamic's asset metadata
7. Logs the before/after dimensions and bytes (optional)

Originals smaller than `min_size_bytes` are left alone. SVG, GIF, and unknown formats are skipped.

## Installation

```bash
composer require sennik/statamic-asset-optimizer
```

Publish the config (optional — sensible defaults are used out of the box):

```bash
php artisan vendor:publish --tag=asset-optimizer-config
```

## Configuration

```php
return [
    'enabled' => true,
    'driver' => 'gd',              // 'gd' or 'imagick'
    'max_dimension' => 2400,        // longest edge in pixels (null to disable)
    'max_pixels' => null,           // optional total-pixel cap (e.g. 8_000_000)
    'quality' => 82,                // JPEG/WebP quality (1–100)
    'formats' => ['jpg', 'jpeg', 'png', 'webp'],
    'min_size_bytes' => 100 * 1024, // skip files smaller than 100 KB
    'containers' => null,           // restrict to specific container handles, or null for all
    'log' => true,
];
```

All settings can be overridden per environment via `.env`:

```
ASSET_OPTIMIZER_ENABLED=true
ASSET_OPTIMIZER_DRIVER=imagick
```

## How `max_dimension` behaves

| Original | Result |
|---|---|
| 6000 × 4000 (landscape) | 2400 × 1600 |
| 3000 × 4500 (portrait) | 1600 × 2400 |
| 4000 × 4000 (square) | 2400 × 2400 |
| 1800 × 1200 (landscape) | 1800 × 1200 (already within cap) |
| 1500 × 3000 (tall portrait) | 1200 × 2400 |

Aspect ratio is always preserved.

## Requirements

- PHP 8.2+
- Statamic 5 or 6
- `intervention/image` ^3.0 (installed automatically)
- GD or Imagick PHP extension on the server

WebP encoding requires GD compiled with WebP support, or Imagick. Most modern PHP installations have one or both available.

## Optimising existing assets

The listener only fires for new uploads. To process images that were already on disk before you installed the addon, run:

```bash
php artisan asset-optimizer:run
```

Options:

| Flag | Effect |
|---|---|
| `--container=handle` | Limit to specific container(s). Repeat for multiple. |
| `--dry-run` | Show what would change without writing files. |
| `--force` | Process files smaller than `min_size_bytes` too. |
| `-v` | Verbose: log every file that gets resized. |

Example run on production:

```bash
php artisan asset-optimizer:run --dry-run -v
```

The summary table at the end shows total bytes before/after and how much you saved.

## Logging

When `log` is enabled, every optimisation writes a line to `storage/logs/` like:

```
[asset-optimizer] huizen/example.jpg: 6000x4000 (8.1 MB) → 2400x1600 (412 KB)
```

Disable in production once you trust the behaviour by setting `log` to `false`.

## Notes

- The original file is replaced — keep your own backup of source images if you need the full-resolution version. (You're meant to use Glide for any rendering at any size; the post-optimization file is plenty large for that.)
- Glide's derivative cache is not invalidated automatically. For freshly uploaded assets this doesn't matter because no derivatives exist yet. If you ever overwrite an existing asset, run `php please glide:clear` afterwards.
- Animated GIFs and WebPs are not preserved. The addon currently treats them as static images. Add `gif` to `formats` only if you don't need animation.

## License

MIT
