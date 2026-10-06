<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\Environment;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Tests\Support\MockApi;
use Didww\Verification\VerificationClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ClientOptionsTest extends TestCase
{
    public function testDefaultsToProduction(): void
    {
        $options = new ClientOptions();

        self::assertSame('https://verification.didww.com', $options->baseUrl);
        self::assertSame(30.0, $options->timeout);
        self::assertSame(10.0, $options->connectTimeout);
        self::assertNull($options->proxy);
        self::assertTrue($options->verify);
        self::assertNull($options->handler);
        self::assertNull($options->clock);
    }

    public function testSandbox(): void
    {
        self::assertSame('https://verification-sandbox.didww.com', (new ClientOptions(Environment::Sandbox))->baseUrl);
    }

    public function testBaseUrlOverride(): void
    {
        self::assertSame('http://localhost:3000', (new ClientOptions(baseUrl: 'http://localhost:3000/'))->baseUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUrls(): iterable
    {
        yield 'relative' => ['/api'];
        yield 'with path' => ['https://example.com/api/v1'];
        yield 'with query' => ['https://example.com?a=1'];
        yield 'with credentials' => ['https://user:pass@example.com'];
        yield 'not http' => ['ftp://example.com'];
    }

    #[DataProvider('invalidBaseUrls')]
    public function testRejectsBaseUrlThatIsNotAnOrigin(string $url): void
    {
        $this->expectException(ConfigurationException::class);

        new ClientOptions(baseUrl: $url);
    }

    public function testBareHandlerRunsBehindTheDefaultStack(): void
    {
        $seen = [];
        $mock = new MockHandler([MockApi::verification()]);
        $handler = static function (RequestInterface $request, array $options) use ($mock, &$seen): PromiseInterface {
            $seen[] = [$request, $options];

            return $mock($request, $options);
        };
        $client = new VerificationClient(new PublicAuth(MockApi::KEY), new ClientOptions(handler: $handler, timeout: 5.0, connectTimeout: 2.0, proxy: 'http://proxy.example.com:3128', verify: false));

        $client->startVerification('+15555550100', DeliveryMethod::Sms);

        self::assertCount(1, $seen);
        [$request, $options] = $seen[0];
        // Only the default stack's prepare_body middleware sets this; a bare handler would not.
        self::assertSame((string) $request->getBody()->getSize(), $request->getHeaderLine('Content-Length'));
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
        self::assertSame(5.0, $options['timeout']);
        self::assertSame(2.0, $options['connect_timeout']);
        self::assertSame('http://proxy.example.com:3128', $options['proxy']);
        self::assertFalse($options['verify']);
    }
}
