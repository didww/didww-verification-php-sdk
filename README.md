# DIDWW Verification SDK for PHP

PHP client for the [DIDWW](https://www.didww.com/) Verification API: start a phone
verification, report the code the user entered, and read the outcome. Includes a
callback verifier for the requests DIDWW sends to your application.

- Built on Guzzle 7
- [Verification API documentation](https://doc.didww.com/otp-verification/index.html)

## Installation

```sh
composer require didww/didww-verification-php-sdk
```

Requires PHP 8.2 or newer and the `json` extension.

## Quick start

```php
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\VerificationClient;

$client = new VerificationClient(new BasicAuth($key, $secret));

$verification = $client->startVerification('+15555550100', DeliveryMethod::Sms);

// The code arrives by SMS; ask the user for it, then report it.
// A wrong code throws: see "Reporting a code".
$verification = $client->reportVerification($verification->id, DeliveryMethod::Sms, '123456');

echo $verification->status; // "verified", "failed", ...
```

Every client method returns a `Didww\Verification\Model\Verification` or throws.

## Outcomes are data, not exceptions

A verification that ends `failed`, `expired` or `denied` is a **successful** API
call. Read the result rather than catching something:

```php
$verification = $client->getVerification($verificationId);

if ($verification->isVerified()) {
    grantAccess();
} elseif ($verification->isFinished()) {
    // errorCode says why: too_many_attempts, expired, superseded, ...
    show($verification->errorDetail);
} else {
    keepPolling();
}
```

`isFinished()` is the signal to stop polling. `status`, `errorCode` and
`deliveryMethod` are plain strings, so a value added after this release decodes
instead of failing. The `statusEnum()`, `errorCodeEnum()` and `deliveryMethodEnum()`
helpers return the matching `VerificationStatus`, `ErrorCode` or `DeliveryMethod`
case, or `null` for a value this release does not know. An unknown status counts as
finished. `ErrorCode::outcomeCodes()` lists the codes a finished verification can carry.
`raw` holds the decoded response for debugging; its shape is not covered by semantic
versioning.

Only transport faults, non-2xx responses and unreadable bodies throw; see
[Errors](#errors). A wrong code is one of those non-2xx responses.

## Reporting a code

Each report consumes one of three attempts. While attempts remain, a wrong code is
rejected with 422 and `code_invalid`, and the verification stays `pending`, so the user
can try again. Once all three are used, the next report is answered with a normal 200
whose status is `failed` and whose `errorCode` is `too_many_attempts`.

```php
use Didww\Verification\Exception\ValidationException;

try {
    $verification = $client->reportVerification($verification->id, DeliveryMethod::Sms, $entered);
} catch (ValidationException $e) {
    if ($e->hasCode('code_invalid')) {
        askAgain(); // still pending
    } elseif ($e->hasCode('not_ready_to_report')) {
        retryShortly(); // the challenge is still being sent
    } else {
        throw $e;
    }
}
```

## Addressing a verification by phone number

When the id was never persisted, every read and report has a by-number twin:

```php
$client->getVerificationByNumber('+15555550100');
$client->reportVerificationByNumber('+15555550100', DeliveryMethod::Sms, '123456');
```

The SDK sends only the digits, so `+1 555-555-0100` and `15555550100` address the
same verification. A number with no digits throws `ConfigurationException` before
anything is sent.

"By number" resolves to the **newest** verification for that number, finished ones
included. A start that is itself denied does not supersede an earlier live verification,
so a by-number read can return the denied one while the live one is reachable only by its
id. Keep the id from the start response when you can.

## Per-channel options

Options travel in a block named after the channel. Only the block matching the
delivery method is sent: options for the other channel are dropped.

```php
use Didww\Verification\Request\CalloutOptions;
use Didww\Verification\Request\SmsOptions;

$client->startVerification(
    '+15555550100',
    DeliveryMethod::Sms,
    sms: new SmsOptions(languages: ['pl-PL', 'en-US'], appHash: 'AbCdEfGhIjK'),
);

$client->startVerification(
    '+15555550100',
    DeliveryMethod::Callout,
    callout: new CalloutOptions(languages: ['de-DE']),
);
```

Languages are BCP 47 tags, tried in order, falling back to `en-US`. **Send the region
subtag.** A bare primary subtag like `pl` passes validation and then silently falls
back, because the catalogue is matched on the exact canonical tag.

`appHash` is the Android SMS Retriever app hash, added to the message so the app can
read the code without the SMS permission. A malformed hash rejects the whole start
with 422 and `app_hash_invalid`.

The response reports what was actually used, so a fallback is detected rather than
guessed at:

```php
$verification->sms?->language;            // the tag the message was rendered in
$verification->sms?->appHash;             // echoed back when a hash was stored
$verification->sms?->interceptionTimeout; // seconds the SMS Retriever stays armed
$verification->callout?->language;        // the tag the announcement is played in
```

The two catalogues are separate: a tag with an SMS template may still have no
recording. Both blocks also carry `codeLength`, the generated code's length.

## Environments

```php
use Didww\Verification\ClientOptions;
use Didww\Verification\Environment;

new VerificationClient($auth); // production: https://verification.didww.com
new VerificationClient($auth, new ClientOptions(environment: Environment::Sandbox)); // https://verification-sandbox.didww.com
new VerificationClient($auth, new ClientOptions(baseUrl: 'http://localhost:3000'));
```

`baseUrl` overrides `environment` and must be an `http` or `https` origin: the SDK
adds its own `/api/v1` path, and a URL with another scheme, a path, a query or
credentials throws `ConfigurationException`.

## Authentication

Three schemes, ranked `public < basic < application`. Each application has a minimum;
anything below it is rejected with 401.

```php
use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;

new PublicAuth($key);               // Authorization: Application <key>
new BasicAuth($key, $secret);       // Authorization: Basic base64(key:secret)
new ApplicationAuth($key, $secret); // HMAC-signed, plus an x-timestamp header
```

- **`PublicAuth`** carries no secret. The key identifies rather than authenticates, so
  it is safe in a client users can read. What authorises a start is your registered
  callback URL: with none registered, a start under this scheme is denied outright.
- **`BasicAuth`** is server-to-server only; the secret is recoverable from anything
  that ships it.
- **`ApplicationAuth`** signs every request. It is the only scheme whose starts skip
  the outbound callback, since a signed caller is already trusted. Use it whenever
  the secret stays on your server.

A malformed key or secret throws `ConfigurationException` at construction rather
than failing on the first request. Pass the secret exactly as shown in the DIDWW
panel.

Every authentication failure (unknown key, wrong secret, bad signature, stale
timestamp, too weak a scheme) answers 401 with no further detail, by design.

## Verifying inbound callbacks

Before creating a verification, the API can call your registered callback URL and wait
for you to allow or deny it. There is **one request and no retry**: whatever you
answer decides the verification.

```php
use Didww\Verification\Callback\CallbackVerifier;

$verifier = new CallbackVerifier(
    $applicationSecret,
    'https://example.com/callbacks/didww', // as registered, verbatim
);
```

The callback URL must be the URL **registered with DIDWW**, not the path the request
arrives on: an ingress that rewrites, or an app mounted under a prefix, makes these
differ. Two consequences:

- A registered URL with no path, `https://example.com`, signs the **empty string**,
  not `/`. A verifier that used the received path would compute a valid signature
  over `/` and then deny every verification, with correct code on both sides.
- `https://example.com` and `https://example.com/` are different signatures. Do not
  normalise the trailing slash.

The query string is never signed. `$verifier->signedPath()` shows the path in use.
Requests older or newer than `toleranceSeconds` (third constructor argument, default
300) are rejected.

The body passed to the verifier must be the **received bytes**: re-encoding parsed
parameters changes them and the signature will not match.

### Plain PHP

```php
use Didww\Verification\Callback\CallbackRequest;
use Didww\Verification\Callback\CallbackResponse;

$body = (string) file_get_contents('php://input');
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

$reason = $verifier->check(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['CONTENT_TYPE'] ?? '',
    $body,
    $headers['x-timestamp'] ?? null,
    $headers['authorization'] ?? null,
);
if (null !== $reason) {
    error_log('DIDWW callback rejected: '.$reason->value);
    http_response_code(401);
    exit;
}

$callback = CallbackRequest::fromJson($body);
header('Content-Type: application/json');
echo isExpected($callback) ? CallbackResponse::allow() : CallbackResponse::deny();
```

Where `getallheaders()` is unavailable, read `$_SERVER['HTTP_X_TIMESTAMP']` and
`$_SERVER['HTTP_AUTHORIZATION']`. Some web server setups drop `Authorization` before
it reaches PHP; make sure yours passes it through.

### PSR-7

```php
$reason = $verifier->checkRequest($serverRequest); // or isValidRequest()
```

A seekable body is rewound before and after reading, so it may already have been
read. A non-seekable body that was already consumed cannot be verified.

### Laravel

```php
use Didww\Verification\Callback\CallbackRequest;
use Didww\Verification\Callback\CallbackResponse;
use Illuminate\Http\Request;

Route::post('/callbacks/didww', function (Request $request) use ($verifier) {
    $valid = $verifier->isValid(
        $request->method(),
        (string) $request->header('Content-Type', ''),
        $request->getContent(),
        $request->header('x-timestamp'),
        $request->header('Authorization'),
    );
    if (!$valid) {
        return response('', 401);
    }

    $callback = CallbackRequest::fromJson($request->getContent());
    $body = isExpected($callback) ? CallbackResponse::allow() : CallbackResponse::deny();

    return response($body, 200, ['Content-Type' => 'application/json']);
});
```

Register the route in `routes/api.php`, or exclude it from CSRF protection: the request
comes from DIDWW, not from a form, and carries its own signature.

### Symfony

```php
use Didww\Verification\Callback\CallbackRequest;
use Didww\Verification\Callback\CallbackResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

public function didwwCallback(Request $request): Response
{
    $valid = $this->verifier->isValid(
        $request->getMethod(),
        (string) $request->headers->get('Content-Type', ''),
        $request->getContent(),
        $request->headers->get('x-timestamp'),
        $request->headers->get('Authorization'),
    );
    if (!$valid) {
        return new Response('', 401);
    }

    $callback = CallbackRequest::fromJson($request->getContent());
    $body = isExpected($callback) ? CallbackResponse::allow() : CallbackResponse::deny();

    return new Response($body, 200, ['Content-Type' => 'application/json']);
}
```

### Answering

Answer a verified callback with `CallbackResponse::allow()` or
`CallbackResponse::deny()` as `application/json`, and nothing else. `check()` returns
a `RejectionReason` (`MissingSignature`, `MissingTimestamp`, `MalformedTimestamp`,
`StaleTimestamp`, `BadSignature`) for your logs. Never echo it in the response: it
tells a prober which check failed.

`CallbackRequest::fromJson()` exposes `event`, `id`, `destination` and
`deliveryMethod`, and throws `DecodingException` for a body it cannot read. Verify the
request before trusting any of them.

## Errors

```php
use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\ValidationException;

try {
    $client->startVerification($number, DeliveryMethod::Sms);
} catch (ValidationException $e) {
    if ($e->hasCode('destination_invalid')) {
        // ...
    }
} catch (ApiException $e) {
    $logger->warning('didww: '.$e->status.' '.implode(',', $e->codes()));
}
```

All classes live in `Didww\Verification\Exception`.

| Exception | When |
| --- | --- |
| `UnauthorizedException` | 401 |
| `BalanceInsufficientException` | 402 |
| `NotFoundException` | 404 |
| `ValidationException` | 400, 422 |
| `RateLimitedException` | 429 |
| `ServerException` | 5xx |
| `ApiException` | any other non-2xx; base class of the above |
| `TransportException` | no response: connect failure, timeout, TLS, any other handler failure |
| `DecodingException` | a 2xx body, or a callback body, this SDK could not read |
| `ConfigurationException` | a bad key or secret, an unusable base URL, an empty id, a phone number without digits |

Every one implements `VerificationException`, so `catch (VerificationException $e)`
catches anything the SDK throws. An exception raised inside the handler stack, your
own middleware included, arrives as `TransportException`. It does not keep the
original as `previous`, because that one holds the request and its `Authorization`
header.

`ApiException` carries `status`, `body` (the first 512 bytes, since a body can hold the
destination number), and `errors`, a list of `ErrorItem`
with a `code` and a `detail` (`codeEnum()` maps the code to an `ErrorCode` case). One
response can carry several errors (a validation failure returns one per field), so use
`codes()` and `hasCode()`, not `errors[0]`. `code` is a stable slug to switch on;
`detail` is fixed prose to display, never to parse.

A non-2xx whose body is not JSON still throws the status-mapped exception with empty
`errors`, so an error page from a proxy surfaces as the server error it is.

A start too soon after a non-denied one for the same destination is refused with 429
and `destination_in_cooldown`, as `RateLimitedException`. The SDK never retries it:
wait `retryAfter` seconds (`null` when the response carried no usable `Retry-After`
header), then start again yourself.

```php
use Didww\Verification\Exception\RateLimitedException;

try {
    $client->startVerification($number, DeliveryMethod::Sms);
} catch (RateLimitedException $e) {
    tellUserToWait($e->retryAfter);
}
```

## Retries

Only reads (`getVerification`, `getVerificationByNumber`) are retried, on
`TransportException` and 5xx, with exponential backoff and full jitter. The default
is 2 attempts in total (one retry) with a 0.2 second base delay. Each attempt is
signed again with a fresh timestamp.

```php
use Didww\Verification\RetryPolicy;

new ClientOptions(retry: new RetryPolicy(attempts: 3, baseDelay: 0.5));
new ClientOptions(retry: new RetryPolicy(attempts: 1)); // off
```

Starts and reports are **never** retried, and you should not add it. The API has no
idempotency key: a repeated start supersedes the live verification and charges again,
and a repeated report consumes one of three attempts.

## Transport options

The SDK builds and owns its Guzzle client. `ClientOptions` exposes:

| Option | Default | |
| --- | --- | --- |
| `timeout` | `30.0` | total request timeout, seconds |
| `connectTimeout` | `10.0` | connect timeout, seconds |
| `proxy` | `null` | as Guzzle's `proxy` request option |
| `verify` | `true` | as Guzzle's `verify` request option: a bool or a CA bundle path |
| `handler` | `null` | a Guzzle handler or `HandlerStack` |

```php
new ClientOptions(
    timeout: 10.0,
    connectTimeout: 3.0,
    proxy: 'http://proxy.example.com:3128',
);
```

Redirects are never followed and no default headers can be set: either would let a
request differ from what was signed, and a followed redirect would repeat a write.

A bare handler is wrapped with `HandlerStack::create()`; a `HandlerStack` is used as
given. Build custom stacks with `HandlerStack::create()` so Guzzle's standard
middleware stays in place:

```php
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push($yourMiddleware);

new ClientOptions(handler: $stack);
```

> **Warning:** handler middleware runs **after** the request is signed. Middleware
> that retries, or that changes the method, the path, the signed headers
> (`Authorization`, `x-timestamp`, `Content-Type`) or the body, is unsupported: a
> changed request fails its signature check with 401, and a retried start or report
> charges again or consumes an attempt. Use the SDK's own `retry` option instead.
> Middleware that only observes (logging, metrics, tracing) is fine.

## Logging

The SDK logs nothing, and neither does Guzzle on its own. If you add logging
middleware (for example `GuzzleHttp\Middleware::log()`), it sees the request after
signing:

- Logging request headers exposes credentials. Redact `Authorization` (and
  `x-timestamp`) in any logging middleware; `BasicAuth` sends the secret itself.
- The by-number URLs contain the phone number: redact the path or skip those
  requests.

## A channel this release does not model

`deliveryMethod` is an open vocabulary on read. If a verification you can read reports
a channel this release does not model, `deliveryMethodEnum()` returns `null` and the
raw string is kept, for example `future_channel`. Report it with the raw variants,
which send the method as given without a client-side check:

```php
if (null === $verification->deliveryMethodEnum()) {
    $verification = $client->reportVerificationRaw($verification->id, $verification->deliveryMethod, $code);
}

$client->reportVerificationByNumberRaw('+15555550100', 'future_channel', $code);
```

Starting a verification still takes a `DeliveryMethod` case.

## Development

```sh
composer install
composer ci   # check-style, analyse (PHPStan), test (PHPUnit)
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

Versions follow semantic versioning. Classes and members marked `@internal`, and the
`Didww\Verification\Internal` namespace, are not covered by that promise.

## License

MIT. See [LICENSE](LICENSE).
