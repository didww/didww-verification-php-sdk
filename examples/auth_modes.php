<?php

declare(strict_types=1);

/*
 * The three authentication schemes, and what each is for.
 *
 *   DIDWW_VERIFICATION_KEY=... DIDWW_VERIFICATION_SECRET=... php examples/auth_modes.php
 */

require __DIR__.'/../vendor/autoload.php';

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\VerificationClient;

$key = getenv('DIDWW_VERIFICATION_KEY');
$secret = getenv('DIDWW_VERIFICATION_SECRET');
if (false === $key || false === $secret) {
    fwrite(\STDERR, "set DIDWW_VERIFICATION_KEY and DIDWW_VERIFICATION_SECRET\n");
    exit(2);
}

$schemes = [
    // No secret: the key identifies, it does not authenticate. Your registered callback
    // URL authorises each start; with none registered, a start here is denied.
    'public' => new PublicAuth($key),
    // Server-to-server. The secret is recoverable from anything that ships it.
    'basic' => new BasicAuth($key, $secret),
    // Signs every request, and the only scheme whose starts skip the outbound callback.
    // A malformed secret throws here, not on the first request.
    'application' => new ApplicationAuth($key, $secret),
];

$options = new ClientOptions(environment: require __DIR__.'/environment.php');

foreach ($schemes as $name => $auth) {
    new VerificationClient($auth, $options);
    echo "{$name}: client ready\n";
}
