#!/usr/bin/env bash
# Usage: ./test-guard.sh [BASE_URL] [--wait-expiry]
# Simulates browsers (by sending the same Sec-Fetch-* headers they send) and plain tools (curl).
BASE="${1:-http://localhost/tour-guard}"
WAIT="${2:-}"
EVIL="https://evil.example/"
PAGE="$BASE/tour.php?id=museum"

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
show() { # label actual expected
  if [ "$2" = "$3" ]; then mark="OK "; else mark="?? "; fi
  printf '%s %-62s %s (expected %s)\n' "$mark" "$1" "$2" "$3"
}

echo "Target: $BASE"
echo

show "owner opens the tour page" "$(code "$PAGE")" 200
if curl -s -D - -o /dev/null "$PAGE" | grep -qi '^content-security-policy:.*frame-ancestors'; then HAS=yes; else HAS=no; fi
show "page sends CSP frame-ancestors" "$HAS" yes
show "tour page embedded by an evil site (iframe, cross-site)" \
  "$(code -H 'Sec-Fetch-Dest: iframe' -H 'Sec-Fetch-Site: cross-site' -H "Referer: $EVIL" "$PAGE")" 403
show "tour page embedded by itself (same-origin iframe)" \
  "$(code -H 'Sec-Fetch-Dest: iframe' -H 'Sec-Fetch-Site: same-origin' "$PAGE")" 200
show "someone links to the tour (top-level, cross-site navigation)" \
  "$(code -H 'Sec-Fetch-Dest: document' -H 'Sec-Fetch-Site: cross-site' -H "Referer: $EVIL" "$PAGE")" 200
show "unknown tour id" "$(code "$BASE/tour.php?id=nope")" 404

JSON="$(curl -s "$PAGE&json=1")"
SRC="$(printf '%s' "$JSON" | grep -o '"src":"[^"]*"' | head -1 | sed 's/"src":"//;s/"$//')"
EXP="$(printf '%s' "$JSON" | sed -n 's/.*"exp":\([0-9]*\).*/\1/p')"
if [ -z "$SRC" ]; then echo "Could not read signed links from $PAGE&json=1: $JSON"; exit 1; fi
IMG="$BASE/$SRC"
echo "Signed image URL: $IMG"
echo

show "json links requested cross-site by a browser" \
  "$(code -H 'Sec-Fetch-Site: cross-site' -H 'Sec-Fetch-Mode: cors' -H "Origin: $EVIL" "$PAGE&json=1")" 403
show "valid signed image, plain tool (curl)" "$(code "$IMG")" 200
show "valid signed image from our own page (browser, same-origin)" \
  "$(code -H 'Sec-Fetch-Site: same-origin' -H 'Sec-Fetch-Dest: image' "$IMG")" 200
show "signed image opened directly in a browser tab / address bar" \
  "$(code -H 'Sec-Fetch-Dest: document' -H 'Sec-Fetch-Site: none' "$IMG")" 403
show "valid signed image hotlinked by a foreign site (browser)" \
  "$(code -H 'Sec-Fetch-Site: cross-site' -H 'Sec-Fetch-Dest: image' -H "Referer: $EVIL" "$IMG")" 403
show "valid signed image with a foreign Referer only" "$(code -H "Referer: $EVIL" "$IMG")" 403
show "tampered signature" "$(code "${IMG}0")" 403
show "image without token" "$(code "$BASE/img.php?t=museum&s=hall")" 403
show "direct request to private/tours/museum/hall.jpg (Apache)" "$(code "$BASE/private/tours/museum/hall.jpg")" 403

if [ "$WAIT" = "--wait-expiry" ]; then
  LEFT=$(( EXP - $(date +%s) + 2 ))
  if [ "$LEFT" -gt 120 ]; then
    echo "Link lives $LEFT more seconds; set a small ttl (e.g. 10) in config.php to test expiry."
  else
    [ "$LEFT" -gt 0 ] && { echo "Waiting ${LEFT}s for the link to expire..."; sleep "$LEFT"; }
    show "same signed image after expiry" "$(code "$IMG")" 403
  fi
fi
