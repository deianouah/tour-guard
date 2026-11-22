# Usage (PowerShell, Windows 10+ has curl.exe built in):
#   powershell -ExecutionPolicy Bypass -File .\test-guard.ps1
#   powershell -ExecutionPolicy Bypass -File .\test-guard.ps1 -WaitExpiry
#   powershell -ExecutionPolicy Bypass -File .\test-guard.ps1 -Base "http://localhost:8080/tour-guard"
param(
  [string]$Base = "http://localhost/tour-guard",
  [switch]$WaitExpiry
)

$evil = "https://evil.example/"
$page = "$Base/tour.php?id=museum"

function Show([string]$label, $actual, $expected) {
  $mark = if ("$actual" -eq "$expected") { "OK " } else { "?? " }
  Write-Host ("{0} {1,-62} {2} (expected {3})" -f $mark, $label, $actual, $expected)
}
function Status([string[]]$curlArgs) {
  & curl.exe -s -o NUL -w "%{http_code}" @curlArgs
}

Write-Host "Target: $Base"
Write-Host ""

Show "owner opens the tour page" (Status @($page)) 200
$hdr = (& curl.exe -s -D - -o NUL $page) -join "`n"
$has = if ($hdr -match "(?i)content-security-policy:[^\n]*frame-ancestors") { "yes" } else { "no" }
Show "page sends CSP frame-ancestors" $has "yes"
Show "tour page embedded by an evil site (iframe, cross-site)" (Status @("-H", "Sec-Fetch-Dest: iframe", "-H", "Sec-Fetch-Site: cross-site", "-H", "Referer: $evil", $page)) 403
Show "tour page embedded by itself (same-origin iframe)" (Status @("-H", "Sec-Fetch-Dest: iframe", "-H", "Sec-Fetch-Site: same-origin", $page)) 200
Show "someone links to the tour (top-level, cross-site navigation)" (Status @("-H", "Sec-Fetch-Dest: document", "-H", "Sec-Fetch-Site: cross-site", "-H", "Referer: $evil", $page)) 200
Show "unknown tour id" (Status @("$Base/tour.php?id=nope")) 404

$json = & curl.exe -s "$page&json=1" | ConvertFrom-Json
if (-not $json -or -not $json.scenes) { Write-Host "Could not read signed links from $page&json=1"; exit 1 }
$img = "$Base/$($json.scenes[0].src)"
Write-Host "Signed image URL: $img"
Write-Host ""

Show "json links requested cross-site by a browser" (Status @("-H", "Sec-Fetch-Site: cross-site", "-H", "Sec-Fetch-Mode: cors", "-H", "Origin: $evil", "$page&json=1")) 403
Show "valid signed image, plain tool (curl)" (Status @($img)) 200
Show "valid signed image from our own page (browser, same-origin)" (Status @("-H", "Sec-Fetch-Site: same-origin", "-H", "Sec-Fetch-Dest: image", $img)) 200
Show "signed image opened directly in a browser tab / address bar" (Status @("-H", "Sec-Fetch-Dest: document", "-H", "Sec-Fetch-Site: none", $img)) 403
Show "valid signed image hotlinked by a foreign site (browser)" (Status @("-H", "Sec-Fetch-Site: cross-site", "-H", "Sec-Fetch-Dest: image", "-H", "Referer: $evil", $img)) 403
Show "valid signed image with a foreign Referer only" (Status @("-H", "Referer: $evil", $img)) 403
Show "tampered signature" (Status @("$($img)0")) 403
Show "image without token" (Status @("$Base/img.php?t=museum&s=hall")) 403
Show "direct request to private/tours/museum/hall.jpg (Apache)" (Status @("$Base/private/tours/museum/hall.jpg")) 403

if ($WaitExpiry) {
  $left = [int]($json.exp - [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()) + 2
  if ($left -gt 120) {
    Write-Host "Link lives $left more seconds; set a small ttl (e.g. 10) in config.php to test expiry."
  } else {
    if ($left -gt 0) { Write-Host "Waiting $left s for the link to expire..."; Start-Sleep -Seconds $left }
    Show "same signed image after expiry" (Status @($img)) 403
  }
}
