<?php

declare(strict_types=1);

namespace Didww\Verification\Callback;

/**
 * Why a callback failed verification. For your logs, never for your response.
 */
enum RejectionReason: string
{
    case MissingSignature = 'missing_signature';
    case MissingTimestamp = 'missing_timestamp';
    case MalformedTimestamp = 'malformed_timestamp';
    case StaleTimestamp = 'stale_timestamp';
    case BadSignature = 'bad_signature';
}
