<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Auth;

use Didww\Verification\Auth\Secret;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Tests\Support\MockApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretTest extends TestCase
{
    public function testDecodesUrlSafeAlphabetAndRepads(): void
    {
        $expected = base64_decode('31m+CahIKK3u6hpRgs4+MGRtTm9/znlUWAzMNA/9/2s=', true);

        self::assertSame($expected, (new Secret(MockApi::SECRET))->key());
        self::assertSame(32, \strlen((new Secret(MockApi::SECRET))->key()));
    }

    public function testAcceptsExplicitPadding(): void
    {
        self::assertSame((new Secret(MockApi::SECRET))->key(), (new Secret(MockApi::SECRET.'='))->key());
    }

    public function testAcceptsTheStandardAlphabet(): void
    {
        self::assertSame((new Secret(MockApi::SECRET))->key(), (new Secret(strtr(MockApi::SECRET, '-_', '+/')))->key());
    }

    public function testAcceptsANonCanonicalFinalQuantum(): void
    {
        self::assertSame((new Secret('AA'))->key(), (new Secret('AB'))->key());
    }

    public function testKeepsTheValueAsGiven(): void
    {
        self::assertSame(MockApi::SECRET, (new Secret(MockApi::SECRET))->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSecrets(): iterable
    {
        yield 'empty' => [''];
        yield 'padding only' => ['=='];
        yield 'trailing space' => [MockApi::SECRET.' '];
        yield 'trailing newline' => [MockApi::SECRET."\n"];
        yield 'en dash' => [str_replace('-', "\u{2013}", MockApi::SECRET)];
        yield 'impossible length' => ['AAAAA'];
    }

    #[DataProvider('invalidSecrets')]
    public function testRejectsInvalidSecret(string $secret): void
    {
        try {
            new Secret($secret);
            self::fail('expected a ConfigurationException');
        } catch (ConfigurationException $e) {
            self::assertNotSame('', $e->getMessage());
            if ('' !== trim($secret, '=')) {
                self::assertStringNotContainsString(trim($secret), $e->getMessage());
            }
        }
    }

    public function testCopiesMadeOutsideTheConstructorHoldNoSecret(): void
    {
        $copies = [
            clone new Secret(MockApi::SECRET),
            unserialize(\sprintf('O:%d:"%s":0:{}', \strlen(Secret::class), Secret::class)),
        ];

        foreach ($copies as $copy) {
            self::assertInstanceOf(Secret::class, $copy);
            try {
                $copy->key();
                self::fail('expected a LogicException');
            } catch (\LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testConstructorMarksTheSecretSensitive(): void
    {
        $parameter = (new \ReflectionMethod(Secret::class, '__construct'))->getParameters()[0];

        self::assertNotEmpty($parameter->getAttributes(\SensitiveParameter::class));
    }
}
