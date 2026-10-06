# Changelog

Notable changes to the DIDWW Verification SDK for PHP.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/didww/didww-verification-php-sdk/releases/tag/1.0.0
