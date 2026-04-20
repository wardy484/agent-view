#!/usr/bin/env bash
# demo/m5.sh — M5 (Report) end-to-end demo.
#
# Exercises the full report flow:
#   1. Publish two table snapshots via MCP.
#   2. Publish a report snapshot embedding both tables inline.
#   3. Bump one of the embedded snapshots — the report still shows the
#      pinned revision with a "current v{n+1}" stale badge.
#   4. Emit the final report URL for manual follow-up (sharing, revocation,
#      transitive access via REQ-M5-004).

set -euo pipefail
cd "$(dirname "$0")/.."
source "./demo/common.sh"

say "M5 demo — Report (markdown + embedded snapshots)"
ok  "see docs/nexus-spec.md §M5 for REQ-M5-000..008."

demo_reset_db

WORKBENCH_SLUG="m5-report-demo"
TOKEN=$(demo_mint_token "$WORKBENCH_SLUG")

say "publish table #1 (Q4 Sales)"
RESPONSE_A=$(demo_mcp_call "$TOKEN" "present_structured_data" "$(
  cat <<JSON
{
  "workbench_slug": "$WORKBENCH_SLUG",
  "view_type": "table",
  "data_payload": {
    "columns": [{"key":"region","label":"Region"},{"key":"rev","label":"Revenue"}],
    "rows": [{"region":"NA","rev":"\$120k"},{"region":"EU","rev":"\$88k"}]
  },
  "title": "Q4 Sales"
}
JSON
)")
SNAP_ID_A=$(echo "$RESPONSE_A" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["snapshot_id"] ?? "";')
echo "   snapshot_id=$SNAP_ID_A"

say "publish table #2 (Pipeline)"
RESPONSE_B=$(demo_mcp_call "$TOKEN" "present_structured_data" "$(
  cat <<JSON
{
  "workbench_slug": "$WORKBENCH_SLUG",
  "view_type": "table",
  "data_payload": {
    "columns": [{"key":"deal","label":"Deal"},{"key":"stage","label":"Stage"}],
    "rows": [{"deal":"Acme","stage":"Negotiation"},{"deal":"Globex","stage":"Discovery"}]
  },
  "title": "Pipeline"
}
JSON
)")
SNAP_ID_B=$(echo "$RESPONSE_B" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["snapshot_id"] ?? "";')
echo "   snapshot_id=$SNAP_ID_B"

say "publish the report with two embeds (REQ-M5-003 pins current revisions)"
REPORT_RESPONSE=$(demo_mcp_call "$TOKEN" "present_structured_data" "$(
  cat <<JSON
{
  "workbench_slug": "$WORKBENCH_SLUG",
  "view_type": "report",
  "data_payload": {
    "blocks": [
      {"type":"markdown","body":"# Weekly Narrative\\n\\nShort prose, live data."},
      {"type":"markdown","body":"## Q4 Summary"},
      {"type":"embed","snapshot_id": $SNAP_ID_A},
      {"type":"markdown","body":"## Pipeline"},
      {"type":"embed","snapshot_id": $SNAP_ID_B}
    ]
  },
  "title": "Weekly Narrative"
}
JSON
)")
REPORT_URL=$(echo "$REPORT_RESPONSE" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["workbench_url"] ?? "";')
ok "report ready → $REPORT_URL"

say "bump table #1 — report should keep pinned revision but flag is_stale"
demo_mcp_call "$TOKEN" "present_structured_data" "$(
  cat <<JSON
{
  "workbench_slug": "$WORKBENCH_SLUG",
  "view_type": "table",
  "snapshot_id": $SNAP_ID_A,
  "data_payload": {
    "columns": [{"key":"region","label":"Region"},{"key":"rev","label":"Revenue"}],
    "rows": [{"region":"NA","rev":"\$135k"},{"region":"EU","rev":"\$92k"},{"region":"APAC","rev":"\$41k"}]
  }
}
JSON
)" > /dev/null

ok "open the report in your browser — the Q4 Sales embed should show v1 + 'current v2' badge."
echo
cat <<EOF
Manual probe (REQ-M5-004 transitive sharing):
  1. Sign in as another user, then have the demo owner share the report
     with them via the M4 UI.
  2. That second user opens $REPORT_URL — both embeds render.
  3. Direct-navigate to the embed snapshots — still accessible (transitive).
  4. Revoke the share; a refresh 403s on all three URLs.
EOF
