<?php
// CLI self-test: php test-s3-sign.php
// Checks s3_presign_get() against the example published in AWS's Signature V4 documentation
// ("Authenticating Requests: Using Query Parameters"), so the algorithm is verified
// independently of any particular server.
require __DIR__ . '/s3.php';

$url = s3_presign_get(
    'https://s3.amazonaws.com',
    'us-east-1',
    'examplebucket',
    'AKIAIOSFODNN7EXAMPLE',
    'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'test.txt',
    86400,
    false,           // virtual-hosted style, as in the AWS example
    1369353600       // 2013-05-24T00:00:00Z
);

$expected = 'aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404';
preg_match('/X-Amz-Signature=([0-9a-f]+)$/', $url, $m);
$got = $m[1] ?? '';

echo $url, "\n\n";
if ($got === $expected) {
    echo "PASS: signature matches the AWS documented example\n";
    exit(0);
}
echo "FAIL: expected $expected\n      got      $got\n";
exit(1);
