<?php

declare(strict_types=1);

namespace Didww\Verification\Internal;

/**
 * Bodies kept on exceptions are cut short: they can carry the destination number, and
 * exceptions tend to end up in logs.
 *
 * @internal
 */
final class BodySnippet
{
    public const LIMIT = 512;

    public static function of(string $body): string
    {
        if (\strlen($body) <= self::LIMIT) {
            return $body;
        }
        $cut = substr($body, 0, self::LIMIT);

        // Drop a UTF-8 character the cut split, so loggers that encode JSON don't choke.
        return 1 === preg_match('//u', $cut) ? $cut : (preg_replace('/[\xC0-\xFF][\x80-\xBF]*\z/', '', $cut) ?? $cut);
    }
}
