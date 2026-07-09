<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Hyva\ShippingDhlDe\Model\Checkout\DhlCheckoutOptionValidator;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionAvailabilityResolver;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionCleaner;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionManager;
use Hyva\ShippingDhlDe\Model\Checkout\DhlShippingContextResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionManager;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionRepository;

/**
 * Central Magewire component managing DHL delivery options and vendor-derived UI state rules.
 */
class DeliveryOptions extends ShippingOptions
{
    /** @var bool Whether the shipping address is domestic German (DE) for DHL add-ons. */
    public bool $isShippingAddressValid = false;

    /** @var array List of currently active service codes */
    public array $activeServiceCodes = [];

    /** @var array List of current validation errors */
    public array $validationErrors = [];

    /** @var array Current shipping address data for child components */
    public array $shippingAddress = [];

    /** @var DhlCheckoutOptionValidator */
    private DhlCheckoutOptionValidator $dhlCheckoutOptionValidator;

    /**
     * Dependency injection for the delivery options parent component.
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
        DhlOptionSelectionCleaner $dhlOptionSelectionCleaner,
        DhlCheckoutOptionValidator $dhlCheckoutOptionValidator
    ) {
        parent::__construct(
            $moduleConfig,
            $storeManager,
            $quoteSelectionManager,
            $shippingOption,
            $checkoutSession,
            $scopeConfig,
            $quoteSelectionRepository,
            $dhlShippingContextResolver,
            $dhlOptionSelectionManager,
            $dhlOptionAvailabilityResolver,
            $dhlOptionSelectionCleaner
        );

        $this->dhlCheckoutOptionValidator = $dhlCheckoutOptionValidator;
    }

    /** @var array Magewire event listeners */
    protected $listeners = [ 'shipping_address_saved' => 'refreshState', 'guest_shipping_address_saved' => 'refreshState', 'shipping_method_selected' => 'refreshState', 'refresh' => 'refreshState', 'refreshState' => 'refreshState', 'requestExclusive' => 'grantExclusiveAccess', 'releaseExclusive' => 'releaseExclusiveAccess', 'requestStateBroadcast' => 'broadcastStateChange', ];

    /**
     * Magewire lifecycle hook.
     * Initializes the delivery options state.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->refreshState();
        
        if (!empty($this->fetchActiveServiceCodes())) {
            $this->broadcastStateChange();
        }
    }

    /**
     * Refreshes the state of delivery options and broadcasts changes.
     *
     * @return void
     */
    public function refreshState(): void
    {
        $this->shippingAddress = [];
        try {
            $address = $this->checkoutSession->getQuote()->getShippingAddress();
            if ($address) {
                $this->shippingAddress = [
                    'street'      => $address->getStreetLine(1),
                    'city'        => $address->getCity(),
                    'postalCode'  => $address->getPostcode(),
                    'countryCode' => $address->getCountryId(),
                ];
            }
        } catch (\Exception $e) {}
        
        $context = $this->dhlShippingContextResolver->resolve();
        $this->isShippingAddressValid = $context->isDomesticGermany();

        $this->dhlOptionSelectionCleaner->cleanForContext($context);

        if (!$context->isDhlPaket()) {
            $this->activeServiceCodes = [];
            $this->broadcastStateChange();
            $this->validationErrors = [];
            return;
        }

        $this->activeServiceCodes = $this->fetchActiveServiceCodes();
        $this->broadcastStateChange();
        $this->validationErrors = $this->validate();
    }

    /**
     * Returns the currently available DHL service component aliases and option codes.
     *
     * @return array<string, string>
     */
    public function getServiceMap(): array
    {
        return $this->dhlOptionAvailabilityResolver->getAvailableServiceMap(
            $this->dhlShippingContextResolver->resolve()
        );
    }

    /**
     * Returns a list of mutually exclusive (blocking) service codes.
     *
     * @return array
     */
    private function getExclusiveCodes(): array
    {
        return [
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION,
            DhlCodes::SERVICE_OPTION_PREFERRED_DAY,
            DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
            DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY,
            DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY,
            DhlCodes::SERVICE_OPTION_DELIVERY_TYPE,
        ];
    }

    /**
     * Returns compatibility/UI rules for managed DHL options.
     *
     * @return array<string, array<string, string[]>>
     */
    private function getRulesMap(): array
    {
        return $this->dhlOptionAvailabilityResolver->getRulesMap();
    }

    /**
     * Validates delivery options based on compatibility rules.
     *
     * @return array
     */
    public function validate(): array
    {
        return $this->dhlCheckoutOptionValidator
            ->validate($this->dhlShippingContextResolver->resolve())
            ->getErrors();
    }

    /**
     * Returns all currently active service codes for the current quote.
     *
     * @return array
     */
    private function fetchActiveServiceCodes(): array
    {
        $activeCodes = [];
        $addressId = $this->getAddressId();
        if ($addressId === null) {
            return [];
        }
        $selections = $this->quoteSelectionManager->load($addressId);

        foreach ($this->getExclusiveCodes() as $code) {
            $fieldSelections = [];
            foreach ($selections as $sel) {
                if ($sel->getShippingOptionCode() === $code) {
                    $fieldSelections[$sel->getInputCode()] = $sel;
                }
            }

            if ($code === CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION) {
                $enabled = !empty($fieldSelections['enabled'])
                    && (bool) $fieldSelections['enabled']->getInputValue();
                $hasId  = !empty($fieldSelections['id'])
                    && trim((string)$fieldSelections['id']->getInputValue()) !== '';
                if ($enabled && $hasId) {
                    $activeCodes[] = $code;
                    continue;
                }
            } elseif ($code === DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY) {
                $hasName = !empty($fieldSelections['name']) && trim((string)$fieldSelections['name']->getInputValue()) !== '';
                $hasAddr = !empty($fieldSelections['address']) && trim((string)$fieldSelections['address']->getInputValue()) !== '';
                if ($hasName && $hasAddr) { $activeCodes[] = $code; continue; }
            } elseif (
                $code === DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY
                || $code === DhlCodes::SERVICE_OPTION_DELIVERY_TYPE
            ) {
                if (!empty($fieldSelections['details']) && trim((string)$fieldSelections['details']->getInputValue()) !== '') {
                    $activeCodes[] = $code; continue;
                }
            } else {
                if ($this->selectionsHaveValue($fieldSelections)) { $activeCodes[] = $code; }
            }
        }
        return array_values(array_unique($activeCodes));
    }

    /**
     * Activates exclusive access for a service code and broadcasts changes.
     *
     * @param string|array|null $serviceCode
     * @return void
     */
    public function grantExclusiveAccess(string|array|null $serviceCode): void
    {
        $serviceCode = $this->normalizeServiceCode($serviceCode);
        if ($serviceCode === null || !in_array($serviceCode, $this->getExclusiveCodes(), true)) {
            return;
        }
        if (!in_array($serviceCode, $this->activeServiceCodes, true)) {
            $this->activeServiceCodes[] = $serviceCode;
            $this->activeServiceCodes = $this->normalizeActiveCodes($this->activeServiceCodes);
            $this->broadcastStateChange();
        }
    }

    /**
     * Releases exclusive access for a service code and broadcasts changes.
     *
     * @param string|array|null $serviceCode
     * @return void
     */
    public function releaseExclusiveAccess(string|array|null $serviceCode): void
    {
        $serviceCode = $this->normalizeServiceCode($serviceCode);
        if ($serviceCode === null || !in_array($serviceCode, $this->getExclusiveCodes(), true)) {
            return;
        }
        $this->activeServiceCodes = array_filter(
            $this->activeServiceCodes,
            fn($code) => $code !== $serviceCode
        );
        $this->activeServiceCodes = $this->normalizeActiveCodes($this->activeServiceCodes);
        $this->broadcastStateChange();
    }

    /**
     * Normalizes a Magewire service-code event payload.
     *
     * @param string|array|null $serviceCode
     * @return string|null
     */
    private function normalizeServiceCode(string|array|null $serviceCode): ?string
    {
        if (is_array($serviceCode)) {
            $serviceCode = reset($serviceCode) ?: null;
        }

        $serviceCode = is_string($serviceCode) ? trim($serviceCode) : '';

        return $serviceCode !== '' ? $serviceCode : null;
    }

    /**
     * Normalizes the list of active codes by applying compatibility rules.
     *
     * @param array $codes
     * @return array
     */
    private function normalizeActiveCodes(array $codes): array
    {
        $rulesMap = $this->getRulesMap();
        $normalized = [];
        foreach ($codes as $candidate) {
            foreach ($normalized as $idx => $already) {
                $rule = $rulesMap[$candidate] ?? [];
                if (in_array($already, $rule['disable'] ?? [], true)) {
                    unset($normalized[$idx]);
                }
            }
            $blocked = false;
            foreach ($normalized as $already) {
                $rule = $rulesMap[$already] ?? [];
                if (in_array($candidate, $rule['disable'] ?? [], true)) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                $normalized[] = $candidate;
            }
        }
        return array_values($normalized);
    }

    /**
     * Broadcasts the current state to child components.
     *
     * @return void
     */
    public function broadcastStateChange(): void
    {
        $context = $this->dhlShippingContextResolver->resolve();
        $availableServiceMap = $this->dhlOptionAvailabilityResolver->getAvailableServiceMap($context);
        $serviceMap = $this->dhlOptionAvailabilityResolver->getStateBroadcastServiceMap($context);

        $rulesMap    = $this->getRulesMap();
        $activeCodes = $this->activeServiceCodes;
        $parcelPackstationComponent = $this->dhlOptionAvailabilityResolver->getComponentAlias(
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION
        );

        $hideSet = [];
        $disableSet = [];
        foreach ($activeCodes as $active) {
            $rule = $rulesMap[$active] ?? [];
            foreach (($rule['hide'] ?? []) as $c)    { $hideSet[$c]    = true; }
            foreach (($rule['disable'] ?? []) as $c) { $disableSet[$c] = true; }
        }

        foreach ($serviceMap as $componentName => $componentCode) {
            if (in_array($componentCode, $activeCodes, true)) {
                $isHidden = false;
                $isDisabled = false;
            } else {
                $isHidden   = !empty($hideSet[$componentCode]);
                $isDisabled = !empty($disableSet[$componentCode]);
            }
            if ($componentCode === DhlCodes::SERVICE_OPTION_GOGREEN_PLUS && !$this->isShippingAddressValid) {
                $isHidden = true;
            }

            if (!in_array($componentCode, $availableServiceMap, true)) {
                $isHidden = true;
                $isDisabled = true;
            }

            if ($componentName === $parcelPackstationComponent) {
                $this->emitTo($componentName, 'updateState', $activeCodes, $isDisabled, $isHidden, $this->shippingAddress);
            } else {
                $this->emitTo($componentName, 'updateState', $activeCodes, $isDisabled, $isHidden);
            }
        }
    }

}
