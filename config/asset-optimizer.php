<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Schakel automatische optimalisatie globaal in of uit.
    |
    */

    'enabled' => env('ASSET_OPTIMIZER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Image driver
    |--------------------------------------------------------------------------
    |
    | Welke Intervention Image driver gebruikt wordt. `gd` werkt overal,
    | `imagick` is sneller en heeft betere kleurprofiel-ondersteuning.
    | Zorg dat de PHP-extensie aanwezig is op de server.
    |
    */

    'driver' => env('ASSET_OPTIMIZER_DRIVER', 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Longest edge
    |--------------------------------------------------------------------------
    |
    | Maximale lengte van de langste zijde (breedte of hoogte) in pixels.
    | Een 6000x4000 landscape wordt teruggeschaald naar 2400x1600.
    | Een 3000x4500 portret wordt teruggeschaald naar 1600x2400.
    | Zet op null om deze cap uit te schakelen.
    |
    */

    'max_dimension' => 2400,

    /*
    |--------------------------------------------------------------------------
    | Total pixels cap (optioneel)
    |--------------------------------------------------------------------------
    |
    | Secundaire vangnet voor extreme aspectratio's (panorama's,
    | hele lange portretten). Bijvoorbeeld 8_000_000 = 8 megapixel.
    | Zet op null om uit te schakelen.
    |
    */

    'max_pixels' => null,

    /*
    |--------------------------------------------------------------------------
    | Quality
    |--------------------------------------------------------------------------
    |
    | Encoding-kwaliteit voor JPEG en WebP (1-100). PNG is altijd lossless.
    |
    */

    'quality' => 82,

    /*
    |--------------------------------------------------------------------------
    | Formats
    |--------------------------------------------------------------------------
    |
    | Welke afbeeldingsformaten worden bewerkt. SVG's, GIF's en andere
    | formaten worden overgeslagen.
    |
    */

    'formats' => ['jpg', 'jpeg', 'png', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | Minimum file size
    |--------------------------------------------------------------------------
    |
    | Bestanden kleiner dan dit aantal bytes worden overgeslagen — die zijn
    | al klein genoeg om verwerking te rechtvaardigen.
    |
    */

    'min_size_bytes' => 100 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Containers
    |--------------------------------------------------------------------------
    |
    | Beperk de optimalisatie tot specifieke asset-containers. Geef een
    | array van container-handles, of null voor alle containers.
    |
    */

    'containers' => null,

    /*
    |--------------------------------------------------------------------------
    | Log
    |--------------------------------------------------------------------------
    |
    | Schrijf elke optimalisatie naar het Laravel log (storage/logs/).
    |
    */

    'log' => true,

    /*
    |--------------------------------------------------------------------------
    | Memory limit
    |--------------------------------------------------------------------------
    |
    | Image decoding (especially with the GD driver) holds the entire
    | uncompressed bitmap in memory. A 6000×4000 photo needs roughly
    | 90 MB just for the raw pixels — well above PHP's default CLI
    | memory_limit of 128M. We bump it for the duration of the work.
    |
    | Set to null to leave PHP's memory_limit untouched.
    |
    */

    'memory_limit' => '512M',

];
