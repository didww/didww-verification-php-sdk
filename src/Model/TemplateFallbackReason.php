<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * Why the requested or default template group's template was not used. Null when no group
 * applies at all; group_missing means the group no longer exists, with templateGroup null.
 */
enum TemplateFallbackReason: string
{
    case LanguageMissing = 'language_missing';
    case VersionPending = 'version_pending';
    case VersionRejected = 'version_rejected';
    case AudioNotReady = 'audio_not_ready';
    case GroupMissing = 'group_missing';
}
