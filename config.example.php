<?php
// tour-guard configuration.
// Copy this file to config.php and edit it there (config.php is git-ignored).
// If config.php does not exist, this file is used as-is, so the demo runs out of the box.
//
// The getenv() overrides are optional and only useful for automated tests;
// on XAMPP just edit the values directly.

$allowed = array_values(array_filter(array_map('trim', explode(',', getenv('TOURGUARD_ALLOWED') ?: ''))));

return [
    // Secret used to sign image links (HMAC). Anyone who knows it can forge valid links.
    'secret' => getenv('TOURGUARD_SECRET') ?: 'change-this-secret-before-any-deployment',

    // How long a signed image link stays valid, in seconds.
    'ttl' => (int)(getenv('TOURGUARD_TTL') ?: 300),

    // Your own public origin (scheme + host [+ port]). Leave empty to detect it from the
    // request. Set it explicitly in production, especially behind a proxy or CDN.
    'site_origin' => '',

    // Other sites that ARE allowed to embed your tours in an iframe.
    // Example: ['https://partner-museum.example', 'https://client.example']
    // Empty list = only your own site can embed them.
    'allowed_embedders' => $allowed,

    // Reject image/JSON requests that the browser marks as cross-site.
    'block_cross_site' => true,

    // Cross-Origin-Resource-Policy for images served by img.php.
    // 'same-origin' if images and tour page share an origin, 'same-site' if images live on a sibling subdomain.
    'corp' => 'same-origin',

    // Where panorama files live: 'local' (private/tours/...) or 's3' (RustFS / MinIO / AWS S3, presigned links).
    'storage' => getenv('TOURGUARD_STORAGE') ?: 'local',

    // Only used when storage = 's3'. Keep the bucket PRIVATE.
    's3' => [
        'endpoint'   => 'https://s3.example.com',
        'region'     => 'us-east-1',
        'bucket'     => 'tours',
        'access_key' => '',
        'secret_key' => '',
        'path_style' => true,   // https://endpoint/bucket/key (most self-hosted stores)
        'prefix'     => 'tours/', // object key = prefix + <tour id> + '/' + <file>
    ],

    // Your tours. Each scene is one equirectangular panorama (2:1 ratio, e.g. 4096x2048).
    'tours' => [
        'museum' => [
            'title'  => 'Demo Museum',
            'scenes' => [
                ['id' => 'hall',   'title' => 'Main hall', 'file' => 'hall.jpg'],
                ['id' => 'garden', 'title' => 'Garden',    'file' => 'garden.jpg'],
            ],
        ],
    ],
];
