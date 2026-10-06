<?php

declare(strict_types=1);

namespace Didww\Verification;

enum Environment: string
{
    case Production = 'https://verification.didww.com';
    case Sandbox = 'https://verification-sandbox.didww.com';
}
