<?php

declare(strict_types=1);

namespace Didww\Verification\Internal;

use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\BalanceInsufficientException;
use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Exception\ErrorItem;
use Didww\Verification\Exception\NotFoundException;
use Didww\Verification\Exception\RateLimitedException;
use Didww\Verification\Exception\ServerException;
use Didww\Verification\Exception\UnauthorizedException;
use Didww\Verification\Exception\ValidationException;
use Didww\Verification\Model\CalloutInfo;
use Didww\Verification\Model\SmsInfo;
use Didww\Verification\Model\Verification;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns a response into a Verification or an exception. A verification that ended failed,
 * expired or denied is a 200 and decodes normally.
 *
 * @internal
 */
final class ResponseDecoder
{
    private const TIMESTAMP = '/\A(\d{4})-(\d{2})-(\d{2})[Tt ](\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,9}))?(?:([Zz])|([+-])(\d{2}):?(\d{2}))?\z/';

    public static function decode(ResponseInterface $response): Verification
    {
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status > 299) {
            throw self::apiException($status, $body, $response->getHeaderLine('Retry-After'));
        }

        try {
            $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new DecodingException('Response body is not JSON: '.$e->getMessage(), $body, $e);
        }
        if (!\is_array($payload) || !isset($payload['data']) || !\is_array($payload['data']) || array_is_list($payload['data'])) {
            throw new DecodingException('Response has no "data" object.', $body);
        }
        /** @var array<string, mixed> $data */
        $data = $payload['data'];

        try {
            return new Verification(
                id: self::requiredString($data, 'id'),
                destination: self::requiredString($data, 'destination'),
                deliveryMethod: self::requiredString($data, 'delivery_method'),
                fee: self::fee($data['fee'] ?? null, $body),
                status: self::requiredString($data, 'status'),
                errorCode: self::optionalString($data, 'error_code'),
                errorDetail: self::optionalString($data, 'error_detail'),
                expiresAt: self::timestamp($data['expires_at'] ?? null),
                sms: self::sms($data['sms'] ?? null),
                callout: self::callout($data['callout'] ?? null),
                raw: $data,
            );
        } catch (DecodingException $e) {
            throw new DecodingException($e->getMessage(), $body);
        }
    }

    private static function apiException(int $status, string $body, string $retryAfter): ApiException
    {
        $errors = self::errorItems($body);

        return match (true) {
            401 === $status => new UnauthorizedException($status, $errors, $body),
            402 === $status => new BalanceInsufficientException($status, $errors, $body),
            404 === $status => new NotFoundException($status, $errors, $body),
            400 === $status, 422 === $status => new ValidationException($status, $errors, $body),
            429 === $status => new RateLimitedException($status, $errors, $body, 1 === preg_match('/\A\d{1,10}\z/', trim($retryAfter)) ? (int) trim($retryAfter) : null),
            $status >= 500 && $status <= 599 => new ServerException($status, $errors, $body),
            default => new ApiException($status, $errors, $body),
        };
    }

    /**
     * A proxy or an unrouted path can answer with HTML, so an unreadable body yields no items.
     *
     * @return list<ErrorItem>
     */
    private static function errorItems(string $body): array
    {
        $payload = json_decode($body, true);
        if (!\is_array($payload) || !isset($payload['errors']) || !\is_array($payload['errors'])) {
            return [];
        }

        $items = [];
        foreach ($payload['errors'] as $entry) {
            if (\is_array($entry)) {
                $items[] = new ErrorItem(
                    isset($entry['code']) && \is_string($entry['code']) ? $entry['code'] : null,
                    isset($entry['detail']) && \is_string($entry['detail']) ? $entry['detail'] : null,
                );
            } else {
                $items[] = new ErrorItem(null, \is_string($entry) ? $entry : (string) json_encode($entry));
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!\is_string($value)) {
            throw new DecodingException(\sprintf('Field "%s" is missing or not a string.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optionalString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new DecodingException(\sprintf('Field "%s" is not a string.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requiredInt(array $data, string $field): int
    {
        $value = $data[$field] ?? null;
        if (!\is_int($value)) {
            throw new DecodingException(\sprintf('Field "%s" is missing or not an integer.', $field));
        }

        return $value;
    }

    private static function fee(mixed $value, string $body): ?string
    {
        if (null === $value || \is_string($value)) {
            if (\is_string($value) && 1 !== preg_match('/\A-?\d+(?:\.\d+)?\z/', $value)) {
                throw new DecodingException('Field "fee" is not a decimal.');
            }

            return $value;
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (!\is_float($value)) {
            throw new DecodingException('Field "fee" is not a decimal.');
        }

        // json_decode has already turned the number into a float; recover its exact digits from the body.
        preg_match_all('/"fee"\s*:\s*(-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?)\s*[,}]/', $body, $matches);
        foreach ($matches[1] as $digits) {
            if ((float) $digits === $value) {
                return $digits;
            }
        }

        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function timestamp(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || 1 !== preg_match(self::TIMESTAMP, $value, $m, \PREG_UNMATCHED_AS_NULL)) {
            throw new DecodingException('Field "expires_at" is not an ISO 8601 timestamp.');
        }

        [, $year, $month, $day, $hour, $minute, $second] = $m;
        $fraction = substr(str_pad($m[7] ?? '', 6, '0'), 0, 6);
        $offset = null !== $m[9] ? $m[9].$m[10].':'.$m[11] : '+00:00';
        if (!checkdate((int) $month, (int) $day, (int) $year) || (int) $hour > 23 || (int) $minute > 59 || (int) $second > 59
            || (int) $m[10] > 23 || (int) $m[11] > 59) {
            throw new DecodingException('Field "expires_at" is not a valid date.');
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s.uP',
            \sprintf('%s-%s-%sT%s:%s:%s.%s%s', $year, $month, $day, $hour, $minute, $second, $fraction, $offset),
        );
        if (false === $parsed) {
            throw new DecodingException('Field "expires_at" is not a valid date.');
        }

        return $parsed->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function sms(mixed $block): ?SmsInfo
    {
        if (null === $block) {
            return null;
        }
        if (!\is_array($block)) {
            throw new DecodingException('Field "sms" is not an object.');
        }
        /** @var array<string, mixed> $fields */
        $fields = $block;

        return new SmsInfo(
            template: self::optionalString($fields, 'template'),
            language: self::optionalString($fields, 'language'),
            interceptionTimeout: self::requiredInt($fields, 'interception_timeout'),
            codeLength: self::requiredInt($fields, 'code_length'),
            appHash: self::optionalString($fields, 'app_hash'),
        );
    }

    private static function callout(mixed $block): ?CalloutInfo
    {
        if (null === $block) {
            return null;
        }
        if (!\is_array($block)) {
            throw new DecodingException('Field "callout" is not an object.');
        }
        /** @var array<string, mixed> $fields */
        $fields = $block;

        return new CalloutInfo(
            language: self::optionalString($fields, 'language'),
            codeLength: self::requiredInt($fields, 'code_length'),
        );
    }
}
