<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'chart.js' => [
        'version' => '4.5.1',
    ],
    'chart.js/helpers' => [
        'path' => './assets/vendor/chart.js/helpers.js',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'internmap' => [
        'path' => './assets/vendor/internmap/internmap.index.js',
    ],
    'd3-array' => [
        'path' => './assets/vendor/d3-array/d3-array.index.js',
    ],
    'chartjs-chart-sankey' => [
        'path' => './assets/vendor/chartjs-chart-sankey/chartjs-chart-sankey.index.js',
    ],
];
