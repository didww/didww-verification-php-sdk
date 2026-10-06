# Examples

Runnable scripts. Run `composer install` in the repository root first, then pass your
application's credentials as environment variables:

```sh
export DIDWW_VERIFICATION_KEY=...
export DIDWW_VERIFICATION_SECRET=...
export DIDWW_VERIFICATION_ENV=production  # optional; the default is sandbox
```

The scripts call the sandbox (`https://verification-sandbox.didww.com`) unless
`DIDWW_VERIFICATION_ENV=production` selects production (`https://verification.didww.com`).
Sandbox and production credentials are separate: use the key and secret of an application
in the environment you select.

| Script | What it shows |
| --- | --- |
| `quickstart.php <phone number>` | start an SMS verification, read the code from stdin, report it |
| `auth_modes.php` | the three authentication schemes and when to use each |
| `polling.php <verification id>` | poll until `isFinished()`, treating the outcome as data |
| `callback_endpoint.php` | a plain-PHP callback endpoint that verifies the signature and answers allow or deny |

Run the callback endpoint with PHP's built-in server:

```sh
DIDWW_VERIFICATION_CALLBACK_URL=https://example.com/callbacks/didww \
    php -S localhost:8000 examples/callback_endpoint.php
```

`DIDWW_VERIFICATION_CALLBACK_URL` must be the callback URL registered with DIDWW,
verbatim.
