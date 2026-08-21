<?php

namespace App\Exceptions;

/**
 * Thrown when a store hits its plan's message limit (free plan = 100 messages)
 * or a feature that requires a paid plan (e.g. the flow builder).
 */
class PlanLimitExceededException extends \RuntimeException
{
    public static function messages(int $limit, int $used): self
    {
        return new self("Message limit reached ({$used}/{$limit}). Upgrade your plan to send more WhatsApp messages.");
    }
}
