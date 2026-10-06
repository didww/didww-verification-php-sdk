# Contributing

```sh
composer install
```

Before opening a pull request:

```sh
composer ci
```

It runs three checks, all of which must pass:

- `composer check-style`: php-cs-fixer (`@Symfony`) in dry-run mode; `composer fix-style`
  applies the fixes.
- `composer analyse`: PHPStan at level `max`.
- `composer test`: PHPUnit.

## Adding an endpoint

Three local edits plus tests. Nothing in the signer or the error mapping should need to
change; if it does, say so in the pull request.

1. **A builder** in `src/Internal/RequestFactory.php` returning an unsigned PSR-7 request:
   the method, the path under the API prefix and, for a write, the `data` body. The client
   signs it later, reading everything signed back from the finished request.
2. **A decoder** in `src/Internal/ResponseDecoder.php`. Non-2xx responses already map to the
   typed exceptions there; decode the 2xx body fail-open, keeping an unrecognised enum value
   as its raw string.
3. **A method on `VerificationClient`** that sends the request through the client's retry
   loop. Retry applies to `GET` automatically and must stay off for anything that charges,
   supersedes or consumes an attempt.

Tests go under `tests/Unit/`; `tests/Support/MockApi.php` wires a client to Guzzle's
`MockHandler` and records the requests it receives.

## What tests are for here

This SDK signs its requests, and a signing bug is self-consistent: the client and the signer
agree with each other and disagree only with the service. A test that compares them therefore
passes while every real request fails.

So the signing tests use vectors produced by the service's own signing code, and the client
tests assert on the request as sent (its headers, its raw path, its bytes) rather than on a
round trip. When you add a test in that area, make sure it could actually fail.
