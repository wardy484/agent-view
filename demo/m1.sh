#!/usr/bin/env bash
# demo/m1.sh — M1 (Universal Table) end-to-end demo.
# Filled in as REQ-M1-* ship. Until then, this script documents what
# the demo WILL do so reviewers know what to expect.

set -euo pipefail
cd "$(dirname "$0")/.."
source "./demo/common.sh"

say "M1 demo: Universal Table"
say "stub — this demo runs once REQ-M1-* are green."
ok  "see docs/nexus-spec.md §M1 for the acceptance list."
