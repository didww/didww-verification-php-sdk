<?php

declare(strict_types=1);

namespace Didww\Verification;

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Exception\ServerException;
use Didww\Verification\Exception\TransportException;
use Didww\Verification\Internal\RequestFactory;
use Didww\Verification\Internal\ResponseDecoder;
use Didww\Verification\Internal\SystemClock;
use Didww\Verification\Internal\Transport;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\Verification;
use Didww\Verification\Request\CalloutOptions;
use Didww\Verification\Request\SmsOptions;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Every method returns a Verification or throws. A verification that ended failed, expired or
 * denied is not an exception: check its status and errorCode.
 */
final class VerificationClient
{
    private readonly RequestFactory $requests;

    private readonly Transport $transport;

    private readonly RetryPolicy $retry;

    private readonly ClockInterface $clock;

    public function __construct(
        private readonly PublicAuth|BasicAuth|ApplicationAuth $auth,
        ?ClientOptions $options = null,
    ) {
        $options ??= new ClientOptions();
        $this->requests = new RequestFactory($options->baseUrl);
        $this->transport = new Transport($options);
        $this->retry = $options->retry;
        $this->clock = $options->clock ?? new SystemClock();
    }

    /**
     * Starts a verification. Never retried: a repeated start supersedes the live one and charges again.
     * Only the options block matching $method is sent; the other is dropped.
     *
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function startVerification(string $destination, DeliveryMethod $method, ?SmsOptions $sms = null, ?CalloutOptions $callout = null): Verification
    {
        return $this->execute($this->requests->start($destination, $method->value, $sms, $callout));
    }

    /**
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function getVerification(string $id): Verification
    {
        return $this->execute($this->requests->get($id));
    }

    /**
     * Reads the newest verification for a number, finished ones included.
     *
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function getVerificationByNumber(string $number): Verification
    {
        return $this->execute($this->requests->getByNumber($number));
    }

    /**
     * Reports the code the user entered. Never retried: each report consumes an attempt.
     *
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function reportVerification(string $id, DeliveryMethod $method, string $code): Verification
    {
        return $this->execute($this->requests->report($id, $method->value, $code));
    }

    /**
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function reportVerificationByNumber(string $number, DeliveryMethod $method, string $code): Verification
    {
        return $this->execute($this->requests->reportByNumber($number, $method->value, $code));
    }

    /**
     * Reports a code for a verification whose delivery method this release does not model.
     * The method is sent as given, without a client-side check.
     *
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function reportVerificationRaw(string $id, string $deliveryMethod, string $code): Verification
    {
        return $this->execute($this->requests->report($id, $deliveryMethod, $code));
    }

    /**
     * @throws ApiException|TransportException|DecodingException|ConfigurationException
     */
    public function reportVerificationByNumberRaw(string $number, string $deliveryMethod, string $code): Verification
    {
        return $this->execute($this->requests->reportByNumber($number, $deliveryMethod, $code));
    }

    private function execute(RequestInterface $request): Verification
    {
        $attempts = 'GET' === $request->getMethod() ? $this->retry->attempts : 1;

        for ($attempt = 1;; ++$attempt) {
            // Signed per attempt: a retry must carry a fresh timestamp to stay inside the replay window.
            $signed = RequestFactory::authorize($request, $this->auth, $this->clock->now()->getTimestamp());
            try {
                return ResponseDecoder::decode($this->transport->send($signed));
            } catch (TransportException|ServerException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
            $this->retry->wait($attempt);
        }
    }
}
