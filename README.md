# Tour Guard

**Tour Guard** is a lightweight PHP laboratory for displaying 360-degree tour panoramas while limiting who can embed or hot-link them.

It works with XAMPP and does not require Composer, accounts, or a subscription. The project demonstrates browser Fetch Metadata checks, Content Security Policy, signed links, private local assets, and S3-compatible presigned URLs.

## Features

- Restricts tour embedding to the current origin and explicitly approved partners.
- Uses short-lived, HMAC-SHA256 signed image URLs.
- Blocks cross-origin image and JSON requests.
- Keeps local panorama files outside direct public access.
- Supports RustFS, MinIO, AWS S3, and other S3-compatible storage.
- Includes minimal demo pages and HTTP test scripts for Windows and Linux/macOS.
- Requires only PHP and Apache.

## Requirements

- PHP 8.0 or newer
- Apache 2.4 or newer
- XAMPP on Windows, or a compatible Apache/PHP environment
- PHP extensions commonly enabled by default: `curl`, `hash`, and `openssl`

For S3-compatible storage, the bucket must support SigV4 presigned URLs.

## Quick Start with XAMPP

1. Clone or copy the project into `C:\xampp\htdocs\tour-guard`.
2. Copy `config.example.php` to `config.php`.
3. Replace the default secret in `config.php`:

   ```php
   'secret' => 'use-a-long-random-secret',
   ```

4. Start Apache from the XAMPP Control Panel.
5. Open `http://localhost/tour-guard/`.

The demo museum uses the panorama files in `private/tours/museum/`.

## Configuration

Most settings are controlled in `config.php`.

### General

```php
'secret' => 'use-a-long-random-secret',
'ttl' => 300,
'site_origin' => 'https://tours.example.com',
'block_cross_site' => true,
'storage' => 'local',
```

- `secret` signs local image links and must be kept private.
- `ttl` is the lifetime of each signed link, in seconds.
- `site_origin` defines the public origin. Set it explicitly in production.
- `block_cross_site` enables browser context checks.
- `storage` can be `local` or `s3`.

### Tour and Scene Data

Panoramas must use an equirectangular 2:1 image ratio, such as 4096 x 2048 pixels.

```php
'tours' => [
    'museum' => [
        'title' => 'Demo Museum',
        'scenes' => [
            ['id' => 'hall', 'title' => 'Main hall', 'file' => 'hall.jpg'],
            ['id' => 'garden', 'title' => 'Garden', 'file' => 'garden.jpg'],
        ],
    ],
],
```

Place local panorama files in:

```text
private/tours/<tour-id>/<scene-file>
```

Example:

```text
private/tours/museum/hall.jpg
private/tours/museum/garden.jpg
```

### Allowed Embed Partners

Add each approved partner origin to `allowed_embedders`:

```php
'allowed_embedders' => [
    'https://partner-museum.example',
    'https://client.example',
],
```

Partners can embed a tour with:

```html
<iframe
  src="https://tours.example.com/tour.php?id=museum"
  allowfullscreen
  style="border:0;width:100%;height:480px">
</iframe>
```

Do not set `referrerpolicy="no-referrer"` on the partner iframe. The project uses the Referer header as an additional server-side check.

## How It Works

1. `tour.php` sends a `Content-Security-Policy: frame-ancestors` header.
2. The server rejects disallowed cross-origin iframe requests.
3. `tour.php` generates short-lived signed scene URLs.
4. `img.php` validates the HMAC signature, expiry, Fetch Metadata headers, Origin, Referer, and request destination.
5. Apache rules block direct access to `private/`.
6. The browser refreshes scene links before they expire.

Normal top-level links to the tour are allowed. The protection applies to embedding and hot-link contexts, not to a browser that is intentionally viewing the tour page.

## Local Storage

To use local storage, keep:

```php
'storage' => 'local',
```

The local panorama directory is protected by `.htaccess`. Files are delivered by `img.php` only after validation.

## S3-Compatible Storage

Set local storage aside and configure `storage` and the `s3` block:

```php
'storage' => 's3',

's3' => [
    'endpoint' => 'https://s3.example.com',
    'region' => 'us-east-1',
    'bucket' => 'tours',
    'access_key' => 'your-access-key',
    'secret_key' => 'your-secret-key',
    'path_style' => true,
    'prefix' => 'tours/',
],
```

Object keys use this format:

```text
<prefix>/<tour-id>/<scene-file>
```

Keep the bucket private and allow CORS only for origins that should load the tour viewer:

```json
[
  {
    "AllowedOrigins": ["https://tours.example.com"],
    "AllowedMethods": ["GET"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3000
  }
]
```

In this mode, panorama requests go directly to the storage server. Expiring SigV4 URLs and bucket CORS provide the protection; the `Sec-Fetch-*` checks in `img.php` do not run because `img.php` is not serving the image.

## Testing

Start Apache and run the matching test script from the project directory.

### Windows (PowerShell)

```powershell
powershell -ExecutionPolicy Bypass -File .\test-guard.ps1
```

Test link expiry after setting a short TTL:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-guard.ps1 -WaitExpiry
```

Use another base URL when needed:

```powershell
powershell -ExecutionPolicy Bypass -File .\test-guard.ps1 -Base "http://localhost:8080/tour-guard"
```

### Linux or macOS

```bash
chmod +x test-guard.sh
./test-guard.sh
./test-guard.sh --wait-expiry
./test-guard.sh --base "http://localhost:8080/tour-guard"
```

### S3 Signature Test

Test the pure-PHP SigV4 implementation without contacting a server:

```bash
php test-s3-sign.php
```

This validates the signing calculation against the AWS Signature V4 example. It does not verify your S3 provider or CORS configuration.

## Testing the Embedding Protection

Open the project through both host forms:

- `http://localhost/tour-guard/`
- `http://127.0.0.1/tour-guard/`

Open `attacker.html` through the alternate host name. It attempts to load the tour in an iframe and read its JSON. The frame and cross-origin read should be blocked.

To allow `127.0.0.1` for an embedding test, add it to `allowed_embedders`, reload, and remove it when the test is complete.

## Security and Limitations

No client-side protection can prevent all downloading. A browser that can display a panorama can also capture or copy its pixels.

Additional production measures should include:

- A strong, random secret kept outside version control
- HTTPS for the tour and all partner origins
- A short signed-link TTL
- Tiled or lower-resolution panoramas
- Visible watermarks
- Legal terms and access controls where appropriate
- Moving `private/` outside the web root when supported by the deployment

For Laravel or another framework, apply the same `frame-ancestors` policy in middleware and preserve the S3 signing and CORS configuration.

## Project Structure

| Path | Purpose |
|---|---|
| `index.php` | Tour selection page |
| `tour.php` | Tour page and fresh-link JSON endpoint |
| `img.php` | Local signed-image delivery |
| `lib.php` | Configuration, origin checks, signing, and scene links |
| `s3.php` | Pure-PHP Signature V4 presigning |
| `assets/viewer.js` | Minimal WebGL panorama viewer |
| `attacker.html` | Cross-site iframe demonstration |
| `private/` | Local panorama storage |
| `test-guard.ps1` | PowerShell HTTP test suite |
| `test-guard.sh` | Shell HTTP test suite |
| `test-s3-sign.php` | AWS Signature V4 self-test |

## License

No license has been added yet. Add a `LICENSE` file before publishing this project as open-source software.
