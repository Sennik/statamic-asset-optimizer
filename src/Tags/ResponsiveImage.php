<?php

namespace Sennik\AssetOptimizer\Tags;

use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Imaging\UrlBuilder;
use Statamic\Facades\Asset;
use Statamic\Tags\Tags;

class ResponsiveImage extends Tags
{
    /**
     * {{ responsive_image src="..." }}
     *
     * Outputs a <picture> with WebP + JPEG srcsets generated via Statamic
     * Glide. Each width becomes one Glide URL — Glide caches the rendered
     * derivative so the original is never sent to the browser.
     *
     * Parameters:
     *   src             string|Asset  — required; asset path, ID, or Asset object
     *   alt             string        — accessible alt text
     *   widths          string|array  — pipe/comma-separated list; default from config
     *   sizes           string        — sizes attribute; default "100vw"
     *   class           string        — class on the <img>
     *   loading         string        — "lazy" (default) | "eager"
     *   fetchpriority   string        — "high" | "low"; omit by default
     *   quality         int           — JPEG/WebP quality; default from config
     *   object_position string        — sets style="object-position: …"
     *   wrapper_class   string        — class on the <picture>
     */
    public function index(): string
    {
        $src = $this->params->get('src');

        $asset = $this->resolveAsset($src);
        if (! $asset) {
            return '';
        }

        $widths = $this->parseWidths();
        sort($widths);
        $largest = end($widths);

        $sizes         = (string) $this->params->get('sizes', '100vw');
        $alt           = (string) $this->params->get('alt', '');
        $class         = (string) $this->params->get('class', '');
        $wrapperClass  = (string) $this->params->get('wrapper_class', '');
        $loading       = (string) $this->params->get('loading', 'lazy');
        $fetchPriority = $this->params->get('fetchpriority');
        $objectPos     = (string) $this->params->get('object_position', '');
        $quality       = (int) $this->params->get(
            'quality',
            (int) config('asset-optimizer.responsive.quality', config('asset-optimizer.quality', 82))
        );

        $builder = app(UrlBuilder::class);

        $webpSrcset = $this->buildSrcset($builder, $asset, $widths, $quality, 'webp');
        $jpegSrcset = $this->buildSrcset($builder, $asset, $widths, $quality);
        $defaultSrc = $builder->build($asset, ['w' => $largest, 'q' => $quality]);

        return $this->renderPicture(
            webpSrcset:    $webpSrcset,
            jpegSrcset:    $jpegSrcset,
            defaultSrc:    $defaultSrc,
            sizes:         $sizes,
            alt:           $alt,
            class:         $class,
            wrapperClass:  $wrapperClass,
            loading:       $loading,
            fetchPriority: is_string($fetchPriority) ? $fetchPriority : null,
            objectPos:     $objectPos,
            largest:       (int) $largest,
            asset:         $asset,
        );
    }

    private function resolveAsset(mixed $src): ?AssetContract
    {
        if ($src instanceof AssetContract) {
            return $src;
        }

        if (! is_string($src) || $src === '') {
            return null;
        }

        // Container::path identifier
        if (str_contains($src, '::')) {
            return Asset::findById($src);
        }

        // Default container "assets"; tolerate leading slash
        return Asset::findById('assets::' . ltrim($src, '/'));
    }

    /**
     * @return int[]
     */
    private function parseWidths(): array
    {
        $param = $this->params->get('widths');

        if (is_array($param)) {
            return array_values(array_map('intval', $param));
        }

        if (is_string($param) && $param !== '') {
            return array_values(array_map('intval', preg_split('/[|,]/', $param)));
        }

        return (array) config(
            'asset-optimizer.responsive.widths',
            [400, 800, 1200, 1600, 2000]
        );
    }

    /**
     * @param  int[]  $widths
     */
    private function buildSrcset(UrlBuilder $builder, AssetContract $asset, array $widths, int $quality, ?string $format = null): string
    {
        $items = [];
        foreach ($widths as $w) {
            $params = ['w' => $w, 'q' => $quality];
            if ($format) {
                $params['fm'] = $format;
            }
            $items[] = $builder->build($asset, $params) . ' ' . $w . 'w';
        }
        return implode(', ', $items);
    }

    private function renderPicture(
        string $webpSrcset,
        string $jpegSrcset,
        string $defaultSrc,
        string $sizes,
        string $alt,
        string $class,
        string $wrapperClass,
        string $loading,
        ?string $fetchPriority,
        string $objectPos,
        int $largest,
        AssetContract $asset,
    ): string {
        $escape = fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Intrinsic dimensions if known — prevents CLS.
        $width = (int) $asset->width();
        $height = (int) $asset->height();
        if ($width > 0 && $height > 0) {
            $aspect = $height / $width;
            $renderWidth = $largest;
            $renderHeight = (int) round($largest * $aspect);
        } else {
            $renderWidth = $largest;
            $renderHeight = 0;
        }

        $imgAttrs = [
            'src'    => $defaultSrc,
            'srcset' => $jpegSrcset,
            'sizes'  => $sizes,
            'alt'    => $alt,
        ];
        if ($class)         $imgAttrs['class']         = $class;
        if ($loading)       $imgAttrs['loading']       = $loading;
        if ($fetchPriority) $imgAttrs['fetchpriority'] = $fetchPriority;
        if ($renderWidth)   $imgAttrs['width']         = (string) $renderWidth;
        if ($renderHeight)  $imgAttrs['height']        = (string) $renderHeight;
        $imgAttrs['decoding'] = 'async';
        if ($objectPos) {
            $imgAttrs['style'] = 'object-position: ' . $objectPos . ';';
        }

        $imgHtml = '<img';
        foreach ($imgAttrs as $k => $v) {
            $imgHtml .= ' ' . $k . '="' . $escape((string) $v) . '"';
        }
        $imgHtml .= '>';

        $pictureOpen = '<picture' . ($wrapperClass ? ' class="' . $escape($wrapperClass) . '"' : '') . '>';

        return $pictureOpen
            . '<source type="image/webp" srcset="' . $escape($webpSrcset) . '" sizes="' . $escape($sizes) . '">'
            . $imgHtml
            . '</picture>';
    }
}
