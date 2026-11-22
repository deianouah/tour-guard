<?php
declare(strict_types=1);

/**
 * Builds an AWS Signature V4 "presigned GET" URL for any S3-compatible store
 * (RustFS, MinIO, AWS S3, ...). Pure PHP, no SDK needed.
 *
 * $now is injectable only so the function can be tested against AWS's documented example
 * (see test-s3-sign.php).
 */
function s3_presign_get(
    string $endpoint,
    string $region,
    string $bucket,
    string $accessKey,
    string $secretKey,
    string $key,
    int $expires,
    bool $pathStyle = true,
    ?int $now = null
): string {
    $p = parse_url($endpoint);
    $scheme = $p['scheme'] ?? 'https';
    $host = $p['host'] ?? '';
    if (!empty($p['port'])) {
        $host .= ':' . $p['port'];
    }

    $encKey = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    if ($pathStyle) {
        $uri = '/' . rawurlencode($bucket) . '/' . $encKey;
    } else {
        $host = $bucket . '.' . $host;
        $uri = '/' . $encKey;
    }

    $t = $now ?? time();
    $amzDate = gmdate('Ymd\THis\Z', $t);
    $date = gmdate('Ymd', $t);
    $scope = "$date/$region/s3/aws4_request";

    $query = [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => "$accessKey/$scope",
        'X-Amz-Date'          => $amzDate,
        'X-Amz-Expires'       => (string)$expires,
        'X-Amz-SignedHeaders' => 'host',
    ];
    ksort($query);
    $pairs = [];
    foreach ($query as $k => $v) {
        $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    $canonicalQuery = implode('&', $pairs);

    $canonicalRequest = "GET\n$uri\n$canonicalQuery\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
    $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonicalRequest);

    $k = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $k = hash_hmac('sha256', $region, $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $signature = hash_hmac('sha256', $stringToSign, $k);

    return "$scheme://$host$uri?$canonicalQuery&X-Amz-Signature=$signature";
}
