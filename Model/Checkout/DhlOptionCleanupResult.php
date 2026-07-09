<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

/**
 * Result object describing DHL option selections removed during cleanup.
 */
final class DhlOptionCleanupResult
{
    /**
     * @param string[] $removedOptionCodes
     */
    public function __construct(
        private readonly array $removedOptionCodes
    ) {
    }

    /**
     * @return string[]
     */
    public function getRemovedOptionCodes(): array
    {
        return $this->removedOptionCodes;
    }

    public function hasRemovedOptions(): bool
    {
        return $this->removedOptionCodes !== [];
    }
}
