<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

/**
 * The request produced no HTTP response: connect failure, timeout, TLS error, or any other
 * failure in the handler stack. The underlying exception is not kept as previous, because it
 * holds the request and its Authorization header.
 */
final class TransportException extends \RuntimeException implements VerificationException
{
}
