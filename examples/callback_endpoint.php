<?php

declare(strict_types=1);

/*
 * A callback endpoint in plain PHP.
 *
 * Try it locally with the built-in server:
 *
 *   DIDWW_VERIFICATION_SECRET=... \
 *   DIDWW_VERIFICATION_CALLBACK_URL=https://example.com/callbacks/didww \
 *   php -S localhost:8000 examples/callback_endpoint.php
 */

require __DIR__.'/../vendor/autoload.php';

use Didww\Verification\Callback\CallbackRequest;
use Didww\Verification\Callback\CallbackResponse;
use Didww\Verification\Callback\CallbackVerifier;
use Didww\Verification\Exception\DecodingException;

$secret = getenv('DIDWW_VERIFICATION_SECRET');
if (false === $secret) {
    error_log('set DIDWW_VERIFICATION_SECRET');
    http_response_code(500);
    exit;
}

// The URL as registered with DIDWW, verbatim: its path is what the signature covers, not
// the path this request arrived on.
$verifier = new CallbackVerifier(
    $secret,
    getenv('DIDWW_VERIFICATION_CALLBACK_URL') ?: 'https://example.com/callbacks/didww',
);

// The exact bytes received: a re-encoded body does not match the signature.
$body = (string) file_get_contents('php://input');
$headers = array_change_key_case(getallheaders() ?: [], \CASE_LOWER);

$reason = $verifier->check(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['CONTENT_TYPE'] ?? '',
    $body,
    $headers['x-timestamp'] ?? null,
    $headers['authorization'] ?? null,
);
if (null !== $reason) {
    // The reason is for your logs only: echoing it tells a prober which check failed.
    error_log('DIDWW callback rejected: '.$reason->value);
    http_response_code(401);
    exit;
}

try {
    $callback = CallbackRequest::fromJson($body);
    // Your own rule goes here.
    $allow = 'verification_request' === $callback->event;
} catch (DecodingException) {
    $allow = false;
}

header('Content-Type: application/json');
echo $allow ? CallbackResponse::allow() : CallbackResponse::deny();
