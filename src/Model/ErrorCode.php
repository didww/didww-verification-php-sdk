<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * Every error code this release knows: the codes of a non-2xx error envelope and the codes a
 * finished verification carries in its error_code. Match on the raw string for codes added later.
 */
enum ErrorCode: string
{
    case DestinationBlank = 'destination_blank';
    case DestinationInvalid = 'destination_invalid';
    case DeliveryMethodBlank = 'delivery_method_blank';
    case DeliveryMethodInclusion = 'delivery_method_inclusion';
    case DeliveryMethodInvalid = 'delivery_method_invalid';
    case LanguagesInvalid = 'languages_invalid';
    case AutofillInvalid = 'autofill_invalid';
    case AppHashInvalid = 'app_hash_invalid';
    case CustomTooLong = 'custom_too_long';
    case TemplateGroupNotFound = 'template_group_not_found';
    case SmsDomainNotConfigured = 'sms_domain_not_configured';
    case CodeBlank = 'code_blank';
    case DestinationNotSupportedForChannel = 'destination_not_supported_for_channel';
    case CodeInvalid = 'code_invalid';
    case AlreadyVerified = 'already_verified';
    case NotReadyToReport = 'not_ready_to_report';
    case ParameterMissing = 'parameter_missing';
    case NotFound = 'not_found';
    case Unauthorized = 'unauthorized';
    case BalanceInsufficient = 'balance_insufficient';
    case DestinationInCooldown = 'destination_in_cooldown';
    case ValidationFailed = 'validation_failed';
    case InternalError = 'internal_error';
    case RateLimitPerIp = 'rate_limit_per_ip';
    case RateLimitFailedAuth = 'rate_limit_failed_auth';
    case RateLimitPerAccount = 'rate_limit_per_account';
    case RateLimitPublicPerAccount = 'rate_limit_public_per_account';
    case VolumeLimitReached = 'volume_limit_reached';
    case DispatchFailed = 'dispatch_failed';
    case Expired = 'expired';
    case TooManyAttempts = 'too_many_attempts';
    /**
     * @deprecated the service no longer sends it and reports these verifications as dispatch_failed;
     *             removed in the next major version
     */
    case StaleDispatch = 'stale_dispatch';
    case ApplicationDeleted = 'application_deleted';
    case Superseded = 'superseded';
    case DeniedMissingCallbackUrl = 'denied_missing_callback_url';
    case DeniedByCallback = 'denied_by_callback';
    case DeniedInvalidCallbackResponse = 'denied_invalid_callback_response';
    case ApplicationPaused = 'application_paused';
    case DestinationCountryDisabled = 'destination_country_disabled';

    /**
     * The codes a finished verification carries in its error_code. The rest arrive only in an
     * error envelope. destination_not_supported_for_channel is no longer sent as an error and
     * stays in the envelope vocabulary for compatibility.
     *
     * @return list<self>
     */
    public static function outcomeCodes(): array
    {
        return [
            self::DispatchFailed,
            self::Expired,
            self::TooManyAttempts,
            self::StaleDispatch,
            self::ApplicationDeleted,
            self::Superseded,
            self::DeniedMissingCallbackUrl,
            self::DeniedByCallback,
            self::DeniedInvalidCallbackResponse,
            self::ApplicationPaused,
            self::DestinationCountryDisabled,
            self::DestinationNotSupportedForChannel,
        ];
    }
}
