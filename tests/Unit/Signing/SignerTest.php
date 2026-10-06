<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Signing;

use Didww\Verification\Auth\Secret;
use Didww\Verification\Signing\Signer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string, int, ?string, string}>
     */
    public static function vectors(): iterable
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/signing_vectors.json');
        self::assertIsString($json);
        /** @var array{vectors: list<array{name: string, secret: string, method: string, contentType: string, path: string, timestamp: int, body: ?string, signature: string}>} $fixture */
        $fixture = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        foreach ($fixture['vectors'] as $v) {
            yield $v['name'] => [$v['secret'], $v['method'], $v['contentType'], $v['path'], $v['timestamp'], $v['body'], $v['signature']];
        }
    }

    #[DataProvider('vectors')]
    public function testMatchesGoldenVector(string $secret, string $method, string $contentType, string $path, int $timestamp, ?string $body, string $expected): void
    {
        $key = (new Secret($secret))->key();

        self::assertSame($expected, Signer::sign($key, Signer::stringToSign($method, $path, $contentType, $body, $timestamp)));
    }

    public function testFixtureCoversASecretNeedingTheUrlSafeAlphabet(): void
    {
        $secrets = array_map(static fn (array $v): string => $v[0], iterator_to_array(self::vectors()));

        self::assertNotEmpty(array_filter($secrets, static fn (string $s): bool => str_contains($s, '-') && str_contains($s, '_') && 43 === \strlen($s)));
        self::assertGreaterThanOrEqual(5, \count($secrets));
    }

    public function testStringToSignHasFiveLinesAndNoTrailingNewline(): void
    {
        $sts = Signer::stringToSign('get', '/api/v1/verifications/abc', '', null, 1767225600);

        self::assertSame("GET\n\n\nx-timestamp:1767225600\n/api/v1/verifications/abc", $sts);
    }

    public function testContentMd5OfABody(): void
    {
        self::assertSame(base64_encode(md5('{"a":1}', true)), Signer::contentMd5('{"a":1}'));
    }

    public function testWhitespaceOnlyBodySignsLikeNoBody(): void
    {
        $bodyless = Signer::stringToSign('PATCH', '/p', 'application/json', null, 1);

        self::assertSame('', Signer::contentMd5(null));
        self::assertSame('', Signer::contentMd5(''));
        self::assertSame('', Signer::contentMd5(" \t\r\n\x0B\f"));
        self::assertSame($bodyless, Signer::stringToSign('PATCH', '/p', 'application/json', " \n\t ", 1));
    }

    public function testUnicodeWhitespaceBodyIsHashedLikeContent(): void
    {
        foreach (["\u{00A0}", "\u{3000}", "\u{2028}", "\u{0085}", " \u{00A0}\n"] as $body) {
            self::assertSame(base64_encode(md5($body, true)), Signer::contentMd5($body));
        }
    }

    public function testBodyWithContentAroundWhitespaceIsHashed(): void
    {
        self::assertSame(base64_encode(md5(" {} \n", true)), Signer::contentMd5(" {} \n"));
    }

    public function testInvalidUtf8BodyIsHashed(): void
    {
        self::assertSame(base64_encode(md5("\xff", true)), Signer::contentMd5("\xff"));
    }

    public function testSignatureIsBase64OfHmacSha256(): void
    {
        self::assertSame(base64_encode(hash_hmac('sha256', 'message', 'key', true)), Signer::sign('key', 'message'));
    }

    public function testSignMarksTheKeySensitive(): void
    {
        $parameter = (new \ReflectionMethod(Signer::class, 'sign'))->getParameters()[0];

        self::assertNotEmpty($parameter->getAttributes(\SensitiveParameter::class));
    }
}
