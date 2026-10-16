<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use InvalidArgumentException;

/** Identity and action context captured from the authenticated server request. */
final readonly class PreflightContext
{
    public function __construct(
        public string $actorType,
        public string $actorId,
        public string $guard,
        public string $actionKey,
        public ?string $tenantType = null,
        public ?string $tenantId = null,
        public ?string $panelId = null,
    ) {
        foreach ([$this->actorType, $this->actorId, $this->guard, $this->actionKey] as $required) {
            if (trim($required) === '') {
                throw new InvalidArgumentException('A preflight requires an authenticated actor and action.');
            }
        }

        if (($this->tenantType === null) !== ($this->tenantId === null)) {
            throw new InvalidArgumentException('A tenant must include both its type and identifier.');
        }
    }
}
