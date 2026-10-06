# Security

To report a vulnerability in this SDK, email <support@didww.com> rather than opening a
public issue.

Please include the SDK version, a minimal reproduction, and the impact you believe it has.

## Handling secrets

Application secrets are kept out of the `var_dump`, `print_r` and `var_export` output and the
`(array)` casts of every object in this SDK, marked `#[\SensitiveParameter]` so they stay out
of stack traces, and the objects that hold them cannot be serialized. They can still reach a
log through your own code: do not log the values or configuration you construct them from.

A `TransportException` does not keep the underlying HTTP exception, which holds the signed
request.

`Verification::$raw` holds the decoded response, including the destination number, for as
long as the object lives.

## Credentials and phone numbers in logs

This SDK does not log, and neither does Guzzle on its own. If you add logging middleware
through the `handler` option, it sees each request after signing:

- Logging request headers exposes credentials. Redact `Authorization` (and `x-timestamp`)
  in any logging middleware; under `BasicAuth` that header carries the secret itself.
- The by-number request URLs contain the destination number: redact the path or skip those
  requests.
