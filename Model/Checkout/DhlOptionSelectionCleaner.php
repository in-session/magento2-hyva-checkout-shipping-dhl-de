<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

/**
 * Removes managed DHL option selections that are no longer valid for the current shipping context.
 */
final class DhlOptionSelectionCleaner
{
    public function __construct(
        private readonly DhlOptionAvailabilityResolver $dhlOptionAvailabilityResolver,
        private readonly DhlOptionSelectionManager $dhlOptionSelectionManager,
        private readonly DhlOptionComponentRegistry $componentRegistry
    ) {
    }

    public function cleanForContext(DhlShippingContext $context): DhlOptionCleanupResult
    {
        $allManagedOptionCodes = array_values(array_unique($this->componentRegistry->getAllServiceMap()));
        $availableOptionCodes = array_values(array_unique(
            $this->dhlOptionAvailabilityResolver->getAvailableServiceMap($context)
        ));

        $removedOptionCodes = array_values(array_diff($allManagedOptionCodes, $availableOptionCodes));

        if ($removedOptionCodes !== []) {
            $this->dhlOptionSelectionManager->removeOptions($removedOptionCodes);
        }

        return new DhlOptionCleanupResult($removedOptionCodes);
    }
}
