<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Model;

use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\ErrorCode;
use Didww\Verification\Model\VerificationStatus;
use Didww\Verification\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VocabularyTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function vocabularies(): iterable
    {
        $envelope = [
            'destination_blank', 'destination_invalid', 'delivery_method_blank', 'delivery_method_inclusion',
            'delivery_method_invalid', 'languages_invalid', 'app_hash_invalid', 'code_blank',
            'destination_not_supported_for_channel', 'code_invalid', 'already_verified', 'not_ready_to_report',
            'parameter_missing', 'not_found', 'unauthorized', 'balance_insufficient', 'destination_in_cooldown',
            'validation_failed', 'internal_error',
        ];
        $outcome = [
            'dispatch_failed', 'expired', 'too_many_attempts', 'stale_dispatch', 'application_deleted', 'superseded',
            'denied_missing_callback_url', 'denied_by_callback', 'denied_invalid_callback_response',
        ];

        yield 'error codes: 19 envelope + 9 outcome' => [[...$envelope, ...$outcome], array_column(ErrorCode::cases(), 'value')];
        yield 'statuses' => [['pending', 'verified', 'failed', 'expired', 'denied'], array_column(VerificationStatus::cases(), 'value')];
        yield 'delivery methods' => [['sms', 'callout'], array_column(DeliveryMethod::cases(), 'value')];
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    #[DataProvider('vocabularies')]
    public function testVocabulary(array $expected, array $actual): void
    {
        self::assertSame($expected, $actual);
    }

    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/\A\d+\.\d+\.\d+\z/', Version::VERSION);
    }
}
