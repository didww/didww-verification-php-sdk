<?php

declare(strict_types=1);

/*
 * Start a verification, then report the code the user entered.
 *
 *   DIDWW_VERIFICATION_KEY=... DIDWW_VERIFICATION_SECRET=... php examples/quickstart.php +15555550100
 */

require __DIR__.'/../vendor/autoload.php';

use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\Exception\RateLimitedException;
use Didww\Verification\Exception\ValidationException;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Request\SmsOptions;
use Didww\Verification\VerificationClient;

$key = getenv('DIDWW_VERIFICATION_KEY');
$secret = getenv('DIDWW_VERIFICATION_SECRET');
$destination = $argv[1] ?? null;
if (false === $key || false === $secret || null === $destination) {
    fwrite(\STDERR, "usage: DIDWW_VERIFICATION_KEY=... DIDWW_VERIFICATION_SECRET=... php examples/quickstart.php <phone number>\n");
    exit(2);
}

$client = new VerificationClient(
    new BasicAuth($key, $secret),
    new ClientOptions(environment: require __DIR__.'/environment.php'),
);

try {
    $verification = $client->startVerification(
        $destination,
        DeliveryMethod::Sms,
        sms: new SmsOptions(languages: ['en-US']),
    );
} catch (RateLimitedException $e) {
    $wait = null === $e->retryAfter ? 'a bit' : $e->retryAfter.'s';
    echo "too soon after the last start; wait {$wait} and try again\n";
    exit(1);
}

echo "started {$verification->id}, expires at {$verification->expiresAt?->format(\DATE_ATOM)}\n";
if (null !== $verification->sms) {
    echo "rendered in {$verification->sms->language}\n";
}

echo 'code from the SMS: ';
$code = trim((string) fgets(\STDIN));

// One attempt is consumed whatever the outcome, so never loop on this.
try {
    $verification = $client->reportVerification($verification->id, DeliveryMethod::Sms, $code);
} catch (ValidationException $e) {
    if (!$e->hasCode('code_invalid')) {
        throw $e;
    }
    echo "wrong code; the verification is still pending\n";
    exit(1);
}

if ($verification->isVerified()) {
    echo "verified\n";
    exit(0);
}
echo $verification->status.': '.($verification->errorDetail ?? $verification->errorCode)."\n";
exit(1);
