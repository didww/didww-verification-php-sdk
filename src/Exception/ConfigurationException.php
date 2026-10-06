<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

/**
 * The SDK was given an unusable value: a malformed secret, a bad URL, a phone number without digits.
 */
final class ConfigurationException extends \InvalidArgumentException implements VerificationException
{
}
