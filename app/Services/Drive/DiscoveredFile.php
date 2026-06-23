<?php

namespace App\Services\Drive;

/** A signed-evidence file discovered in a lab's Drive area. */
readonly class DiscoveredFile
{
    public function __construct(
        public string $name,
        public ?string $fileId = null,
        public ?string $webLink = null,
    ) {}
}
