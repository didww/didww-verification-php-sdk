<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

use Didww\Verification\Internal\BodySnippet;

/**
 * A non-2xx response. A status without a dedicated subclass arrives as this class.
 *
 * One response can carry several errors (a validation failure returns one per field), so
 * check codes() or hasCode() rather than the first item.
 */
class ApiException extends \RuntimeException implements VerificationException
{
    /**
     * The response body, cut to its first 512 bytes.
     */
    public readonly string $body;

    /**
     * @internal
     *
     * @param list<ErrorItem> $errors every item of the error envelope, empty when the body was not one
     */
    public function __construct(
        public readonly int $status,
        public readonly array $errors,
        string $body,
    ) {
        $this->body = BodySnippet::of($body);
        $details = array_values(array_filter(array_map(static fn (ErrorItem $e): ?string => $e->detail, $errors), static fn (?string $d): bool => null !== $d && '' !== $d));
        parent::__construct([] === $details ? 'HTTP '.$status : implode(', ', $details), $status);
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_filter(array_map(static fn (ErrorItem $e): ?string => $e->code, $this->errors), static fn (?string $c): bool => null !== $c));
    }

    public function hasCode(string $code): bool
    {
        return \in_array($code, $this->codes(), true);
    }
}
