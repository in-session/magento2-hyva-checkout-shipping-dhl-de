<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\CheckoutManagementInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelection;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionFactory;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionManager;

/**
 * Encapsulates Netresearch quote selection access for DHL checkout options.
 */
final class DhlOptionSelectionManager
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutManagementInterface $checkoutManagement,
        private readonly QuoteSelectionFactory $quoteSelectionFactory,
        private readonly QuoteSelectionManager $quoteSelectionManager
    ) {
    }

    /**
     * Loads quote selections for one shipping option code, grouped by input code.
     *
     * @param string $optionCode
     * @return array<string, SelectionInterface>
     */
    public function loadByOptionCode(string $optionCode): array
    {
        $addressId = $this->getAddressId();
        if ($addressId === null) {
            return [];
        }

        $result = [];
        $quoteSelections = $this->quoteSelectionManager->load($addressId);
        foreach ($quoteSelections as $quoteSelection) {
            if ($quoteSelection->getShippingOptionCode() === $optionCode) {
                $result[$quoteSelection->getInputCode()] = $quoteSelection;
            }
        }

        return $result;
    }

    /**
     * Saves one input value for a shipping option and removes empty values as before.
     *
     * @param string $optionCode
     * @param string $inputCode
     * @param mixed $value
     * @return mixed
     */
    public function saveInput(string $optionCode, string $inputCode, mixed $value): mixed
    {
        $quoteId = (int) $this->checkoutSession->getQuote()->getId();
        $addressId = $this->getAddressId();
        if ($addressId === null) {
            return $value;
        }

        /** @var QuoteSelection $newSelection */
        $newSelection = $this->quoteSelectionFactory->create();
        $newSelection->setData([
            SelectionInterface::SHIPPING_OPTION_CODE => $optionCode,
            SelectionInterface::INPUT_CODE => $inputCode,
            SelectionInterface::INPUT_VALUE => $value
        ]);

        $allSelections = $this->quoteSelectionManager->load($addressId);
        $updatedSelections = [];
        foreach ($allSelections as $selection) {
            if (
                $selection->getShippingOptionCode() === $optionCode
                && $selection->getInputCode() === $inputCode
            ) {
                continue;
            }
            $updatedSelections[] = $selection;
        }

        if ($value !== null && $value !== '' && $value !== false) {
            $updatedSelections[] = $newSelection;
        }

        $this->checkoutManagement->updateShippingOptionSelections($quoteId, $updatedSelections);

        return $value;
    }

    /**
     * Removes all selections for the given shipping option codes.
     *
     * @param string[] $optionCodes
     * @return void
     */
    public function removeOptions(array $optionCodes): void
    {
        $addressId = $this->getAddressId();
        if ($addressId === null || $optionCodes === []) {
            return;
        }

        $quoteId = (int) $this->checkoutSession->getQuote()->getId();
        $allSelections = $this->quoteSelectionManager->load($addressId);
        $updatedSelections = [];

        foreach ($allSelections as $selection) {
            if (in_array($selection->getShippingOptionCode(), $optionCodes, true)) {
                continue;
            }
            $updatedSelections[] = $selection;
        }

        $this->checkoutManagement->updateShippingOptionSelections($quoteId, $updatedSelections);
    }

    public function getAddressId(): ?int
    {
        try {
            $address = $this->checkoutSession->getQuote()->getShippingAddress();
            return $address && $address->getId() ? (int) $address->getId() : null;
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }
}
