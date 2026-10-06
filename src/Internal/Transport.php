<?php

declare(strict_types=1);

namespace Didww\Verification\Internal;

use Didww\Verification\ClientOptions;
use Didww\Verification\Exception\TransportException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Owns the Guzzle client. No default headers and no redirects: either would let a request
 * differ from what was signed, and a followed redirect would repeat a write.
 *
 * @internal
 */
final class Transport
{
    private readonly Client $client;

    public function __construct(ClientOptions $options)
    {
        $handler = $options->handler;
        if (null !== $handler && !$handler instanceof HandlerStack) {
            $handler = HandlerStack::create($handler);
        }

        $config = [
            'handler' => $handler ?? HandlerStack::create(),
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::TIMEOUT => $options->timeout,
            RequestOptions::CONNECT_TIMEOUT => $options->connectTimeout,
            RequestOptions::VERIFY => $options->verify,
        ];
        if (null !== $options->proxy) {
            $config[RequestOptions::PROXY] = $options->proxy;
        }

        $this->client = new Client($config);
    }

    public function send(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->send($request);
        } catch (\Throwable $e) {
            // No previous: a Guzzle exception holds the request, and with it the Authorization header.
            throw new TransportException(self::redact($e->getMessage(), $request), 0);
        }
    }

    private static function redact(string $message, RequestInterface $request): string
    {
        $authorization = $request->getHeaderLine('Authorization');
        $credentials = substr($authorization, (int) strpos($authorization, ' ') + 1);

        $message = str_replace(array_filter([$authorization, $credentials]), '[redacted]', $message);

        // Guzzle appends the URI, and a by_number path carries the destination number.
        return (string) preg_replace('#/by_number/[^\s/?]+#', '/by_number/[redacted]', $message);
    }
}
