<?php

declare(strict_types=1);

namespace HeyQuarry\ShopifyProfileSync;

final class SyncResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly bool $skipped = false,
        public readonly ?string $shopDomain = null,
        public readonly ?int $status = null,
        public readonly ?string $error = null,
    ) {
    }
}
