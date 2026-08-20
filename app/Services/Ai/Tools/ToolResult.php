<?php

namespace App\Services\Ai\Tools;

use JsonSerializable;

/**
 * The value returned to the model after a tool call. `success=false` lets the
 * agent apologize and offer alternatives rather than failing hard.
 */
final class ToolResult implements JsonSerializable
{
    public function __construct(
        public mixed $data,
        public bool $success = true,
        public ?string $error = null,
        public bool $escalate = false,
        public ?string $escalateReason = null,
    ) {
    }

    public static function ok(mixed $data): self
    {
        return new self(data: $data);
    }

    public static function fail(string $error, bool $escalate = false, ?string $escalateReason = null): self
    {
        return new self(data: null, success: false, error: $error, escalate: $escalate, escalateReason: $escalateReason);
    }

    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
