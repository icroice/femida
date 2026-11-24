<?php

namespace App\Support\Access;

/**
 * An MCP tool a module declares (spec §7): tool names exposed by the MCP
 * server when the module is available. Reserved for the aggregator task
 * (013) — descriptors start with none.
 */
final class McpTool
{
    public function __construct(
        public readonly string $name,
        public readonly string $title,
    ) {}
}
