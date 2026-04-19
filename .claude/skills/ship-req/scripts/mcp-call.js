#!/usr/bin/env node
// mcp-call.js — minimal JSON-RPC 2.0 client for a Nexus MCP HTTPS endpoint.
//
// Usage:
//   node mcp-call.js \
//     --url https://<preview>/ai/mcp/nexus \
//     --token-file storage/skill-ship-req/<pr>.json \
//     --tool present_structured_data \
//     --args-file .claude/skills/ship-req/fixtures/REQ-M3-001.json
//
// Emits a single JSON object to stdout:
//   {"http_status":200,"tool":"...","result":{...},"error":null}
// Exits non-zero on HTTP >= 400 or JSON-RPC error.

'use strict';

const fs = require('node:fs');

function parseArgs(argv) {
    const out = {};

    for (let i = 2; i < argv.length; i += 2) {
        const key = argv[i].replace(/^--/, '');
        out[key] = argv[i + 1];
    }

    return out;
}

async function main() {
    const args = parseArgs(process.argv);

    if (!args.url || !args.tool) {
        console.error('usage: mcp-call.js --url <u> --tool <t> [--token <t>|--token-file <f>] [--args-file <f>]');
        process.exit(2);
    }

    let token = args.token;

    if (!token && args['token-file']) {
        token = JSON.parse(fs.readFileSync(args['token-file'], 'utf8')).token;
    }

    const toolArgs = args['args-file']
        ? JSON.parse(fs.readFileSync(args['args-file'], 'utf8'))
        : {};

    const body = {
        jsonrpc: '2.0',
        id: 1,
        method: 'tools/call',
        params: { name: args.tool, arguments: toolArgs },
    };

    let response;

    try {
        response = await fetch(args.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: JSON.stringify(body),
        });
    } catch (err) {
        console.log(JSON.stringify({ http_status: 0, tool: args.tool, result: null, error: String(err) }));
        process.exit(1);
    }

    const text = await response.text();
    let parsed = null;

    try {
 parsed = JSON.parse(text); 
} catch { /* non-JSON error body */ }

    const payload = {
        http_status: response.status,
        tool: args.tool,
        result: parsed?.result ?? null,
        error: parsed?.error ?? (response.ok ? null : text.slice(0, 500)),
    };

    console.log(JSON.stringify(payload));
    process.exit(response.ok && !parsed?.error ? 0 : 1);
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
