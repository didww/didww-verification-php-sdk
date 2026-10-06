<?php

declare(strict_types=1);

namespace Didww\Verification\Callback;

/**
 * The JSON bodies a callback endpoint answers with, sent as application/json.
 */
final class CallbackResponse
{
    private function __construct()
    {
    }

    public static function allow(): string
    {
        return '{"action":"allow"}';
    }

    public static function deny(): string
    {
        return '{"action":"deny"}';
    }
}
