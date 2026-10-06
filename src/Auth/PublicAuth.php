<?php

declare(strict_types=1);

namespace Didww\Verification\Auth;

use Didww\Verification\Exception\ConfigurationException;

/**
 * Sends "Authorization: Application <key>". No secret.
 *
 * The key identifies, it does not authenticate, so it is safe in a client users can read.
 */
final class PublicAuth
{
    public readonly string $key;

    public function __construct(string $key)
    {
        $this->key = self::checkKey($key);
    }

    public function authorization(): string
    {
        return 'Application '.$this->key;
    }

    /**
     * @internal
     */
    public static function checkKey(string $key): string
    {
        if (1 !== preg_match('/\A[\x21-\x39\x3B-\x7E]+\z/', $key)) {
            throw new ConfigurationException('Application key must be non-empty printable ASCII with no whitespace or colon.');
        }

        return $key;
    }
}
