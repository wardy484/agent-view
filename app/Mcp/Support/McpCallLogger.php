<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\McpCallLog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;

/**
 * REQ-M3-006: every MCP call writes a `mcp_call_logs` row with tool_name,
 * duration_ms, status, payload_bytes.
 *
 * Tools wrap their handle() body through {@see self::record()}. The logger
 * captures timing + payload size regardless of success/failure and guarantees
 * a row is written even when the underlying tool throws.
 */
final class McpCallLogger
{
    /**
     * @param  callable(): (ResponseFactory|Response)  $callback
     */
    public static function record(string $toolName, Request $request, callable $callback): ResponseFactory|Response
    {
        $start = microtime(true);
        $payloadBytes = self::payloadBytes($request);
        $status = 'ok';
        $workbenchId = null;

        try {
            /** @var ResponseFactory|Response $result */
            $result = $callback();

            if (self::isErrorResponse($result)) {
                $status = 'error';
            }

            return $result;
        } catch (Throwable $exception) {
            $status = 'error';
            throw $exception;
        } finally {
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            try {
                McpCallLog::query()->create([
                    'tool_name' => $toolName,
                    'duration_ms' => $durationMs,
                    'status' => $status,
                    'payload_bytes' => $payloadBytes,
                    'user_id' => null,
                    'workbench_id' => $workbenchId,
                ]);
            } catch (Throwable) {
                // Logging must never break the tool call.
            }
        }
    }

    private static function payloadBytes(Request $request): int
    {
        try {
            $all = $request->all();
            $encoded = json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? 0 : strlen($encoded);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function isErrorResponse(ResponseFactory|Response $result): bool
    {
        try {
            if ($result instanceof Response) {
                return $result->isError();
            }

            // ResponseFactory wraps a collection of Response objects.
            $reflector = new \ReflectionProperty($result, 'responses');
            $responses = $reflector->getValue($result);

            foreach ($responses as $response) {
                if ($response instanceof Response && $response->isError()) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
