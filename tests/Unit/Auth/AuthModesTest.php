<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Auth;

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\Callback\CallbackVerifier;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Signing\Signer;
use Didww\Verification\Tests\Support\MockApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthModesTest extends TestCase
{
    public function testPublicAuthorization(): void
    {
        self::assertSame('Application '.MockApi::KEY, (new PublicAuth(MockApi::KEY))->authorization());
    }

    public function testBasicAuthorizationEncodesTheRawSecret(): void
    {
        self::assertSame(
            'Basic '.base64_encode(MockApi::KEY.':'.MockApi::SECRET),
            (new BasicAuth(MockApi::KEY, MockApi::SECRET))->authorization(),
        );
    }

    public function testApplicationAuthorizationCarriesKeyAndSignature(): void
    {
        $auth = new ApplicationAuth(MockApi::KEY, MockApi::SECRET);
        $expected = Signer::sign(
            (string) base64_decode('31m+CahIKK3u6hpRgs4+MGRtTm9/znlUWAzMNA/9/2s=', true),
            Signer::stringToSign('GET', '/api/v1/verifications/abc', '', null, 1767225600),
        );

        self::assertSame(
            'Application '.MockApi::KEY.':'.$expected,
            $auth->authorization('GET', '/api/v1/verifications/abc', '', null, 1767225600),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'colon' => ['a:b'];
        yield 'space' => ['a b'];
        yield 'newline' => ["a\n"];
        yield 'tab' => ["a\tb"];
        yield 'delete' => ["a\x7F"];
        yield 'non-ascii' => ["cl\u{00E9}"];
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsInvalidKey(string $key): void
    {
        foreach ([
            static fn () => new PublicAuth($key),
            static fn () => new BasicAuth($key, MockApi::SECRET),
            static fn () => new ApplicationAuth($key, MockApi::SECRET),
        ] as $build) {
            try {
                $build();
                self::fail('expected a ConfigurationException');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSecretModesValidateTheSecret(): void
    {
        foreach ([
            static fn () => new BasicAuth(MockApi::KEY, MockApi::SECRET.' '),
            static fn () => new ApplicationAuth(MockApi::KEY, ''),
        ] as $build) {
            try {
                $build();
                self::fail('expected a ConfigurationException');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSecretBytesAreNotInObjectProperties(): void
    {
        $key = (string) base64_decode('31m+CahIKK3u6hpRgs4+MGRtTm9/znlUWAzMNA/9/2s=', true);
        $objects = [
            new BasicAuth(MockApi::KEY, MockApi::SECRET),
            new ApplicationAuth(MockApi::KEY, MockApi::SECRET),
            new CallbackVerifier(MockApi::SECRET, 'https://example.com/callbacks'),
        ];

        foreach ($objects as $object) {
            ob_start();
            var_dump($object, (array) $object);
            $dumps = [
                (string) ob_get_clean(),
                var_export($object, true),
                var_export((array) $object, true),
                print_r($object, true),
                print_r((array) $object, true),
            ];

            foreach ($dumps as $dump) {
                self::assertStringNotContainsString(MockApi::SECRET, $dump, $object::class);
                self::assertStringNotContainsString(base64_encode($key), $dump, $object::class);
                self::assertStringNotContainsString(rtrim(base64_encode($key), '='), $dump, $object::class);
                self::assertStringNotContainsString($key, $dump, $object::class);
            }
        }
    }

    public function testSecretModesCannotBeSerialized(): void
    {
        foreach ([new BasicAuth(MockApi::KEY, MockApi::SECRET), new ApplicationAuth(MockApi::KEY, MockApi::SECRET)] as $auth) {
            try {
                serialize($auth);
                self::fail('expected a LogicException');
            } catch (\LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSecretParametersAreSensitive(): void
    {
        foreach ([BasicAuth::class, ApplicationAuth::class] as $class) {
            $parameter = (new \ReflectionMethod($class, '__construct'))->getParameters()[1];

            self::assertSame('secret', $parameter->getName());
            self::assertNotEmpty($parameter->getAttributes(\SensitiveParameter::class), $class);
        }
    }

    public function testSecretIsHiddenFromStackTraces(): void
    {
        try {
            new ApplicationAuth('', MockApi::SECRET);
            self::fail('expected a ConfigurationException');
        } catch (ConfigurationException $e) {
            self::assertStringNotContainsString(MockApi::SECRET, $e->getTraceAsString());
            self::assertStringNotContainsString(MockApi::SECRET, print_r($e->getTrace(), true));
        }
    }
}
