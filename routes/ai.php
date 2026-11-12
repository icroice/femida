<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Mcp\Servers\FemidaServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Femida's MCP server — same domain, same project Bearer key
|--------------------------------------------------------------------------
*/

Mcp::web('/mcp', FemidaServer::class)->middleware(['throttle:api', AuthenticateApiKey::class]);
