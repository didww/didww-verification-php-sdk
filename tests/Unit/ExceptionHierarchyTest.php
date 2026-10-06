<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\BalanceInsufficientException;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Exception\NotFoundException;
use Didww\Verification\Exception\RateLimitedException;
use Didww\Verification\Exception\ServerException;
use Didww\Verification\Exception\TransportException;
use Didww\Verification\Exception\UnauthorizedException;
use Didww\Verification\Exception\ValidationException;
use Didww\Verification\Exception\VerificationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function hierarchy(): iterable
    {
        yield 'configuration' => [ConfigurationException::class, \InvalidArgumentException::class];
        yield 'transport' => [TransportException::class, \RuntimeException::class];
        yield 'decoding' => [DecodingException::class, \RuntimeException::class];
        yield 'api' => [ApiException::class, \RuntimeException::class];
        foreach ([UnauthorizedException::class, BalanceInsufficientException::class, NotFoundException::class, ValidationException::class, RateLimitedException::class, ServerException::class] as $class) {
            yield $class => [$class, ApiException::class];
        }
    }

    /**
     * @param class-string $class
     * @param class-string $parent
     */
    #[DataProvider('hierarchy')]
    public function testHierarchy(string $class, string $parent): void
    {
        $reflection = new \ReflectionClass($class);

        self::assertTrue($reflection->isSubclassOf($parent));
        self::assertTrue($reflection->implementsInterface(VerificationException::class));
    }
}
