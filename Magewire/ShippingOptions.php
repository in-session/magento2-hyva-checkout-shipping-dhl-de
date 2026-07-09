<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionAvailabilityResolver;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionCleaner;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionManager;
use Hyva\ShippingDhlDe\Model\Checkout\DhlShippingContextResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magewirephp\Magewire\Component;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionManager;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionRepository;

/**
 * Abstract base class for Magewire DHL shipping option components.
 * Provides helper methods and dependency injection for all DHL shipping option Magewire components.
 */
abstract class ShippingOptions extends Component
{
    /** @var ModuleConfig */
    protected ModuleConfig $moduleConfig;

    /** @var StoreManagerInterface */
    protected StoreManagerInterface $storeManager;

    /** @var QuoteSelectionManager */
    protected QuoteSelectionManager $quoteSelectionManager;

    /** @var ShippingOptionInterface */
    protected ShippingOptionInterface $shippingOption;

    /** @var CheckoutSession */
    protected CheckoutSession $checkoutSession;

    /** @var ScopeConfigInterface */
    protected ScopeConfigInterface $scopeConfig;

    /** @var QuoteSelectionRepository */
    protected QuoteSelectionRepository $quoteSelectionRepository;

    /** @var DhlShippingContextResolver */
    protected DhlShippingContextResolver $dhlShippingContextResolver;

    /** @var DhlOptionSelectionManager */
    protected DhlOptionSelectionManager $dhlOptionSelectionManager;

    /** @var DhlOptionAvailabilityResolver */
    protected DhlOptionAvailabilityResolver $dhlOptionAvailabilityResolver;

    /** @var DhlOptionSelectionCleaner */
    protected DhlOptionSelectionCleaner $dhlOptionSelectionCleaner;

    /**
     * Dependency injection for all required services.
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        StoreManagerInterface $storeManager,
        QuoteSelectionManager $quoteSelectionManager,
        ShippingOptionInterface $shippingOption,
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        QuoteSelectionRepository $quoteSelectionRepository,
        DhlShippingContextResolver $dhlShippingContextResolver,
        DhlOptionSelectionManager $dhlOptionSelectionManager,
        DhlOptionAvailabilityResolver $dhlOptionAvailabilityResolver,
        DhlOptionSelectionCleaner $dhlOptionSelectionCleaner
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->quoteSelectionManager = $quoteSelectionManager;
        $this->shippingOption = $shippingOption;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->quoteSelectionRepository = $quoteSelectionRepository;
        $this->dhlShippingContextResolver = $dhlShippingContextResolver;
        $this->dhlOptionSelectionManager = $dhlOptionSelectionManager;
        $this->dhlOptionAvailabilityResolver = $dhlOptionAvailabilityResolver;
        $this->dhlOptionSelectionCleaner = $dhlOptionSelectionCleaner;
    }

    /**
     * Loads all quote selections for a given service code (e.g., "neighbor", "preferred location", etc.).
     *
     * @param string $code The shipping option code.
     * @return array<string, SelectionInterface>
     */
    protected function loadFromDb(string $code): array
    {
        return $this->dhlOptionSelectionManager->loadByOptionCode($code);
    }

    /**
     * Persists a value for a specific field (input) and shipping option.
     *
     * @param string $field The input field name.
     * @param mixed $value The value to persist.
     * @param string $shippingOptionCode The shipping option code.
     * @return mixed The value that was stored.
     */
    protected function persistFieldUpdate(string $field, mixed $value, string $shippingOptionCode): mixed
    {
        return $this->dhlOptionSelectionManager->saveInput($shippingOptionCode, $field, $value);
    }

    /**
     * Returns true if any of the given selections has a valid (non-empty) value.
     *
     * @param array|null $selections
     * @return bool
     */
    protected function selectionsHaveValue(?array $selections): bool
    {
        if (empty($selections)) {
            return false;
        }
        foreach ($selections as $selection) {
            if (
                $selection instanceof SelectionInterface &&
                $selection->getInputValue() !== null &&
                $selection->getInputValue() !== ''
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ID of the current shipping address, or null if unavailable.
     *
     * @return int|null
     */
    protected function getAddressId(): ?int
    {
        return $this->dhlOptionSelectionManager->getAddressId();
    }

}
