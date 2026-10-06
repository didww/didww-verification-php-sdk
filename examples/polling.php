<?php

declare(strict_types=1);

/*
 * Poll a verification to its conclusion, treating a bad outcome as data rather than an exception.
 *
 *   DIDWW_VERIFICATION_KEY=... DIDWW_VERIFICATION_SECRET=... php examples/polling.php <verification id>
 */

require __DIR__.'/../vendor/autoload.php';

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\VerificationClient;

$key = getenv('DIDWW_VERIFICATION_KEY');
$secret = getenv('DIDWW_VERIFICATION_SECRET');
$id = $argv[1] ?? null;
if (false === $key || false === $secret || null === $id) {
    fwrite(\STDERR, "usage: DIDWW_VERIFICATION_KEY=... DIDWW_VERIFICATION_SECRET=... php examples/polling.php <verification id>\n");
    exit(2);
}

$client = new VerificationClient(
    new ApplicationAuth($key, $secret),
    new ClientOptions(environment: require __DIR__.'/environment.php'),
);

$verification = $client->getVerification($id);
// The code lifetime is configured per application: read it rather than hard-coding it.
$deadline = $verification->expiresAt ?? new DateTimeImmutable();

// Anything but "pending" is terminal. Listing the terminal statuses instead would poll a
// status added after this release forever. The last read lands past the deadline, where
// the service already reports "expired".
while (!$verification->isFinished() && new DateTimeImmutable() < $deadline) {
    sleep(2);
    $verification = $client->getVerification($id);
}

echo $verification->status.': '.($verification->errorDetail ?? 'ok')."\n";
exit($verification->isVerified() ? 0 : 1);
