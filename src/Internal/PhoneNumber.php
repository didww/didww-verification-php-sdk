<?php

declare(strict_types=1);

namespace Didww\Verification\Internal;

use Didww\Verification\Exception\ConfigurationException;

/**
 * @internal
 */
final class PhoneNumber
{
    /**
     * Reduces a number to its ASCII digits, the form the by_number paths expect.
     *
     * The service strips non-digits itself, and a dot left in the path would be read as a format
     * suffix and 404 on a number that exists.
     */
    public static function digits(string $number): string
    {
        $digits = (string) preg_replace('/[^0-9]+/', '', $number);
        if ('' === $digits) {
            throw new ConfigurationException('Phone number contains no digits.');
        }

        return $digits;
    }
}
