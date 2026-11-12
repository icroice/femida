<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Screenshot bytes failed to reach the disk (storage full or unavailable).
 * Deliberately loud: a silent failure here would create a DB record pointing
 * at a nonexistent file — a silent loss of evidence. Transports translate it
 * explicitly: REST answers 500 with the message, the MCP tool reports it via
 * fail() so the text reaches the agent instead of a generic "internal error".
 */
class ScreenshotStorageException extends RuntimeException {}
