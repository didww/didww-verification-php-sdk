<?php

declare(strict_types=1);

namespace Didww\Verification\Internal;

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Request\CalloutOptions;
use Didww\Verification\Request\SmsOptions;
use Didww\Verification\Version;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

/**
 * Builds unsigned requests and signs finished ones. Performs no I/O.
 *
 * @internal
 */
final class RequestFactory
{
    private const PREFIX = '/api/v1/verifications';

    public function __construct(private readonly string $baseUrl)
    {
    }

    public function start(string $destination, string $deliveryMethod, ?SmsOptions $sms, ?CalloutOptions $callout): RequestInterface
    {
        $data = ['destination' => $destination, 'delivery_method' => $deliveryMethod];
        if (null !== $sms && 'sms' === $deliveryMethod) {
            $data['sms'] = (object) $sms->toArray();
        }
        if (null !== $callout && 'callout' === $deliveryMethod) {
            $data['callout'] = (object) $callout->toArray();
        }

        return $this->request('POST', self::PREFIX, $data);
    }

    public function get(string $id): RequestInterface
    {
        return $this->request('GET', self::PREFIX.'/'.self::id($id));
    }

    public function getByNumber(string $number): RequestInterface
    {
        return $this->request('GET', self::PREFIX.'/by_number/'.PhoneNumber::digits($number));
    }

    public function report(string $id, string $deliveryMethod, string $code): RequestInterface
    {
        return $this->request('PATCH', self::PREFIX.'/'.self::id($id), ['delivery_method' => $deliveryMethod, 'code' => $code]);
    }

    public function reportByNumber(string $number, string $deliveryMethod, string $code): RequestInterface
    {
        return $this->request('PATCH', self::PREFIX.'/by_number/'.PhoneNumber::digits($number), ['delivery_method' => $deliveryMethod, 'code' => $code]);
    }

    /**
     * Adds the auth headers. Everything signed is read back from the request itself, so the
     * signature covers exactly what is sent.
     */
    public static function authorize(RequestInterface $request, PublicAuth|BasicAuth|ApplicationAuth $auth, int $timestamp): RequestInterface
    {
        if (!$auth instanceof ApplicationAuth) {
            return $request->withHeader('Authorization', $auth->authorization());
        }

        $body = (string) $request->getBody();
        $authorization = $auth->authorization(
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getHeaderLine('Content-Type'),
            '' === $body ? null : $body,
            $timestamp,
        );

        return $request
            ->withHeader('Authorization', $authorization)
            ->withHeader('x-timestamp', (string) $timestamp);
    }

    private static function id(string $id): string
    {
        // An empty id would address the collection path instead of a verification.
        if ('' === $id) {
            throw new ConfigurationException('Verification id must not be empty.');
        }

        return rawurlencode($id);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function request(string $method, string $path, ?array $data = null): RequestInterface
    {
        $headers = ['User-Agent' => 'didww-verification-php/'.Version::VERSION, 'Accept' => 'application/json'];
        if (null === $data) {
            return new Request($method, $this->baseUrl.$path, $headers);
        }

        // The server signs the raw header, so it must be exactly this string, without a charset.
        $headers['Content-Type'] = 'application/json';

        try {
            $body = json_encode(['data' => $data], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new ConfigurationException('Request parameters cannot be encoded as JSON: '.$e->getMessage(), 0, $e);
        }

        return new Request($method, $this->baseUrl.$path, $headers, $body);
    }
}
