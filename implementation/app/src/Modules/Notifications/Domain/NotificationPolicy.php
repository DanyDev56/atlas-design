<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Domain;

final class NotificationPolicy
{
    public const VERSION = '1.1.0';

    public const TOPIC_ADVISOR_PRIORITY = 'AdvisorPrimaryRecommendation';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_RESOLVED = 'Resolved';

    public const STATUS_SUPERSEDED = 'Superseded';

    public const STATUS_EXPIRED = 'Expired';

    public const READ_UNREAD = 'Unread';

    public const READ_READ = 'Read';

    public const CHANNEL_IN_APP = 'InApp';

    public const CHANNEL_EMAIL = 'Email';

    public const IN_APP_ENABLED = 'Enabled';

    public const IN_APP_DISABLED = 'Disabled';

    public const EMAIL_DISABLED = 'Disabled';

    public const EMAIL_IMPORTANT_ONLY = 'ImportantOnly';

    public const AUDIENCE_AUTHORIZED = 'Authorized';

    public const AUDIENCE_REVOKED = 'Revoked';

    public const DELIVERY_SUPPRESSED = 'SuppressedByPreference';

    public const DELIVERY_ACCEPTED = 'Accepted';

    public const DELIVERY_CANCELLED = 'Cancelled';
}
