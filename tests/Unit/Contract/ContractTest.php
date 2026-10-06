<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Contract;

use Didww\Verification\Environment;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\ErrorCode;
use Didww\Verification\Model\VerificationStatus;
use PHPUnit\Framework\TestCase;

/**
 * The exported vocabularies must equal the contract snapshot. This catches an enum edit that
 * forgets the snapshot; it cannot catch both being stale, which only a re-capture can.
 */
final class ContractTest extends TestCase
{
    /** @var array<mixed> */
    private static array $contract;

    public static function setUpBeforeClass(): void
    {
        $json = file_get_contents(__DIR__.'/../../../contract/wire_contract.json');
        self::assertIsString($json);
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::$contract = $decoded;
    }

    public function testErrorCodesEqualTheEnvelopeAndOutcomeCodes(): void
    {
        $expected = [...self::strings('envelopeErrorCodes'), ...self::strings('verificationErrorCodes')];

        self::assertSameSet($expected, array_column(ErrorCode::cases(), 'value'));
    }

    public function testOutcomeCodesEqualTheContract(): void
    {
        self::assertSameSet(self::strings('verificationErrorCodes'), array_column(ErrorCode::outcomeCodes(), 'value'));
    }

    public function testStatusesEqualTheContract(): void
    {
        self::assertSameSet(self::strings('statuses'), array_column(VerificationStatus::cases(), 'value'));
    }

    public function testDeliveryMethodsEqualTheContract(): void
    {
        self::assertSameSet(self::strings('deliveryMethods'), array_column(DeliveryMethod::cases(), 'value'));
    }

    public function testEnvironmentsEqualTheContractBaseUrls(): void
    {
        $baseUrls = self::$contract['baseUrls'];
        self::assertIsArray($baseUrls);

        self::assertSame($baseUrls['production'] ?? null, Environment::Production->value);
        self::assertSame($baseUrls['sandbox'] ?? null, Environment::Sandbox->value);
    }

    /**
     * @return list<string>
     */
    private static function strings(string $key): array
    {
        $values = self::$contract[$key] ?? null;
        self::assertIsArray($values);

        return array_values(array_map(static fn (mixed $v): string => \is_string($v) ? $v : self::fail("{$key} holds a non-string"), $values));
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private static function assertSameSet(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }
}
