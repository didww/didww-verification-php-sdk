<?php

declare(strict_types=1);

use Didww\Verification\Environment;

// Sandbox unless asked otherwise, so running an example never sends live traffic by accident.
return match (getenv('DIDWW_VERIFICATION_ENV') ?: 'sandbox') {
    'sandbox' => Environment::Sandbox,
    'production' => Environment::Production,
    default => throw new InvalidArgumentException('DIDWW_VERIFICATION_ENV must be "sandbox" or "production".'),
};
