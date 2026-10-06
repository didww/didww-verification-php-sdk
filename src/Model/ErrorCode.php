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
    case AppHashInvalid = 'app_hash_invalid';
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
    case DispatchFailed = 'dispatch_failed';
    case Expired = 'expired';
    case TooManyAttempts = 'too_many_attempts';
    case StaleDispatch = 'stale_dispatch';
    case ApplicationDeleted = 'application_deleted';
    case Superseded = 'superseded';
    case DeniedMissingCallbackUrl = 'denied_missing_callback_url';
    case DeniedByCallback = 'denied_by_callback';
    case DeniedInvalidCallbackResponse = 'denied_invalid_callback_response';

    /**
     * The codes a finished verification carries in its error_code; the rest arrive only in an
     * error envelope.
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
        ];
    }
}
