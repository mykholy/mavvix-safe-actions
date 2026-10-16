<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

final readonly class SelectionInspection
{
    /** @param list<string> $recordKeys */
    public function __construct(
        public int $recordCount,
        public string $selectionDigest,
        public array $recordKeys,
    ) {}
}
