<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Model;

use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\ErrorCode;
use Didww\Verification\Model\TemplateFallbackReason;
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
            'delivery_method_invalid', 'languages_invalid', 'autofill_invalid', 'app_hash_invalid', 'custom_too_long',
            'template_group_not_found', 'sms_domain_not_configured', 'code_blank',
            'destination_not_supported_for_channel', 'code_invalid', 'already_verified', 'not_ready_to_report',
            'parameter_missing', 'not_found', 'unauthorized', 'balance_insufficient', 'destination_in_cooldown',
            'validation_failed', 'internal_error', 'rate_limit_per_ip', 'rate_limit_failed_auth', 'rate_limit_per_account',
            'rate_limit_public_per_account', 'volume_limit_reached',
        ];
        $outcome = [
            'dispatch_failed', 'expired', 'too_many_attempts', 'stale_dispatch', 'application_deleted', 'superseded',
            'denied_missing_callback_url', 'denied_by_callback', 'denied_invalid_callback_response', 'application_paused',
            'destination_country_disabled',
        ];

        yield 'error codes: 28 envelope + 11 outcome-only' => [[...$envelope, ...$outcome], array_column(ErrorCode::cases(), 'value')];
        yield 'outcome codes: 12, one shared with the envelope' => [
            [...$outcome, 'destination_not_supported_for_channel'], array_column(ErrorCode::outcomeCodes(), 'value'),
        ];
        yield 'template fallback reasons' => [
            ['language_missing', 'version_pending', 'version_rejected', 'audio_not_ready', 'group_missing'],
            array_column(TemplateFallbackReason::cases(), 'value'),
        ];
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
