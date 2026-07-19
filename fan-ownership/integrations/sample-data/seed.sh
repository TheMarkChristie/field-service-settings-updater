#!/usr/bin/env bash
# Load the Perth Panthers sample data through the platform's own Data API.
#
# Usage:
#   PRX3_SITE=https://your-site.example PRX3_KEY=your-data-api-key ./seed.sh
#
# The key comes from Settings → API & Integrations → "Provision Claude
# connection" (or any Data API key). The Data API must be enabled.
#
# Order matters: members first (owner numbers + register entries via the
# money path), then content, then settings. Safe to re-run: members are
# matched by email (shares are granted again only if you re-run, so run
# once), content inserts new posts each run.

set -euo pipefail

SITE="${PRX3_SITE:?Set PRX3_SITE to the site URL, e.g. https://club.example}"
KEY="${PRX3_KEY:?Set PRX3_KEY to the Data API key}"
BASE="${SITE%/}/wp-json/prx3/v1/data"
DIR="$(cd "$(dirname "$0")" && pwd)"

post() {
  local route="$1" file="$2"
  echo "→ POST ${route} (${file})"
  curl --fail-with-body -sS -X POST "${BASE}/${route}" \
    -H "Content-Type: application/json" \
    -H "X-Prx3-Data-Key: ${KEY}" \
    --data-binary "@${DIR}/${file}"
  echo
}

echo "Seeding ${SITE} …"
post members  members.json
post content  content.json
post settings settings.json
echo "Done. Check Owners → the register, FanPress Chat, Board → Dashboard."
