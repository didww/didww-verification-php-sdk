# Changelog

Notable changes to the DIDWW Verification SDK for PHP.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10

### Added

- **`custom` on `startVerification`.** Free text of up to 4096 characters, delivered
  unchanged to your callback server and read back as `CallbackRequest::$custom`. It is not
  returned in any response. A longer value is refused with the new `custom_too_long` error
  code.

- **`SmsOptions` `autofill`**, built with `Autofill::appHash($hash)`, `Autofill::domain()`
  or `Autofill::none()`. `SmsInfo::$autofill` reads back the stored marker, `app_hash` or
  `domain`, whose `value` is the hash or the domain. New error codes `autofill_invalid` and
  `sms_domain_not_configured`.

- **`templateGroup` on `SmsOptions` and `CalloutOptions`**, to pick a template group instead
  of the application's default. `SmsInfo` and `CalloutInfo` gain `templateGroup`,
  `templateGroupUsed` and `templateFallbackReason`, the last an open vocabulary with
  `templateFallbackReasonEnum()` and the `TemplateFallbackReason` enum. An unknown group is
  refused with the new `template_group_not_found` error code.

- **`x-signature-previous` support in `CallbackVerifier`.** While an application secret is
  being rotated, callbacks also carry a signature made with the previous secret. `check()`
  and `isValid()` take it as an optional `previousSignature`, and `checkRequest()` reads the
  header. A callback is accepted when either signature matches the configured secret.
  Without the header, behaviour is unchanged.

- **New error codes for 429:** `rate_limit_per_ip` (too many requests from this IP
  address), `rate_limit_failed_auth` (too many failed authentication attempts from this IP
  address), `rate_limit_per_account` (too many requests for this account),
  `rate_limit_public_per_account` (too many public requests for this account) and
  `volume_limit_reached` (the application reached its daily or monthly verification limit).
  All throw `RateLimitedException`.

- **New outcome codes:** `application_paused` and `destination_country_disabled` (status
  `denied`), and `destination_not_supported_for_channel` (status `failed`).
  `ErrorCode::outcomeCodes()` lists all three.

- **Docs:** a Testing section on the sandbox environment, which sends nothing and gives a
  predefined result only for the reserved test numbers.

### Changed

- **An unsupported destination is now a failed verification, not a 422.** A destination
  with no country, no delivery for the channel, or in a blocked range comes back as a 201
  with status `failed` and `errorCode` `destination_not_supported_for_channel`. Code that
  handled the 422 must check the returned verification instead. The code stays in the
  error envelope vocabulary for compatibility.

- The `@internal` constructors of `SmsInfo` and `CalloutInfo` take the new fields, so code
  that constructs them directly, for example in tests, must pass them.

### Deprecated

- **`SmsOptions` `appHash` and `SmsInfo::$appHash`.** Use `autofill`. The option is sent as
  `Autofill::appHash()` and triggers a silenced `E_USER_DEPRECATED`, visible to error
  handlers; passing it together with `autofill` throws `ConfigurationException`.

- **`ErrorCode::StaleDispatch`.** The service no longer sends it and reports these
  verifications as `dispatch_failed`. It stays in the enum so existing matches keep working,
  and will be removed in the next major version.

## [1.0.0] - 2026-10

First release.

### Added

- **Every verification endpoint, addressable two ways.** `getVerification` and
  `reportVerification` take the id `startVerification` returns; `getVerificationByNumber` and
  `reportVerificationByNumber` take a phone number, for when the id was never persisted.

- **Per-channel options.** `SmsOptions` (languages, Android SMS Retriever `appHash`) and
  `CalloutOptions` (languages); only the block matching the delivery method is sent. The
  response reports the language actually used and the generated code's length.

- **All three authentication schemes** as distinct types: `PublicAuth`, `BasicAuth` and
  `ApplicationAuth` (HMAC-signed). A malformed key or secret fails at construction.

- **Outcomes as data.** A failed, expired or denied verification is a successful call:
  `status`, `errorCode` and `errorDetail` say what happened, and `isFinished()` says when to
  stop polling. Statuses, error codes and delivery methods are open vocabularies: a value
  added after this release decodes as a string instead of failing.

- **The coded error envelope as typed exceptions**, one per status, carrying every error in
  the response with `codes()` and `hasCode()`. `RateLimitedException` (429) carries
  `retryAfter`. A non-2xx whose body is not JSON still throws the status-mapped exception.

- **Inbound callback verification** with `CallbackVerifier`, from raw values or a PSR-7
  request, with a five-minute replay window and constant-time comparison.
  `CallbackRequest` parses the body; `CallbackResponse` builds the allow and deny answers.

- **Raw report variants**, `reportVerificationRaw` and `reportVerificationByNumberRaw`, for a
  verification whose delivery method this release does not model.

- **Transport options**: timeout, connect timeout, proxy, TLS verification and a custom
  Guzzle handler.

- **Secrets kept out of dumps.** Secret-holding objects show no secret in `var_dump`,
  `print_r`, `var_export` or an `(array)` cast, cannot be serialized, and mark secret
  parameters `#[\SensitiveParameter]`. `TransportException` does not keep the underlying
  HTTP exception, which holds the signed request.

### Notes

- Requires PHP 8.2 or newer.
- Reads are retried on transport faults and 5xx, re-signed on each attempt. Starts and
  reports never are, and should not be: the API has no idempotency key, a repeated start
  supersedes and charges again, and a repeated report consumes one of three attempts.

[1.1.0]: https://github.com/didww/didww-verification-php-sdk/releases/tag/1.1.0
[1.0.0]: https://github.com/didww/didww-verification-php-sdk/releases/tag/1.0.0
