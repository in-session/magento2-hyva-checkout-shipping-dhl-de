<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Dhl\Paket\Model\Config\ModuleConfig;
use Hyva\ShippingDhlDe\Model\Checkout\DhlCheckoutDataProvider;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionAvailabilityResolver;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionCleaner;
use Hyva\ShippingDhlDe\Model\Checkout\DhlOptionSelectionManager;
use Hyva\ShippingDhlDe\Model\Checkout\DhlShippingContextResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\InputInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\OptionInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionManager;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionRepository;

/**
 * Magewire component for managing the DHL "Preferred Day" delivery option.
 *
 * Fully integrated into the central DeliveryOptions parent component.
 */
class PreferredDay extends ShippingOptions
{
    public const SERVICE_CODE = DhlCodes::SERVICE_OPTION_PREFERRED_DAY;

    /** @var string|null Selected preferred day (Y-m-d format or null) */
    public ?string $preferredDay = null;

    /** @var float Additional fee for Preferred Day delivery */
    public float $fee = 0.0;

    /**
     * @var array<int, array{value: string, label: string}>
     */
    public array $availableDays = [];

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /** @var array Magewire event listeners */
    protected $listeners = [
        'updateState' => 'onUpdateState',
        'preferredDaySelected' => 'selectPreferredDay',
    ];

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
        private readonly DhlCheckoutDataProvider $dhlCheckoutDataProvider
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
    }

    /**
     * Magewire lifecycle hook.
     * Loads the preferred day from the database and initializes the fee.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if ($quoteSelections && isset($quoteSelections['date'])) {
            $val = trim((string)$quoteSelections['date']->getInputValue());
            $this->preferredDay = ($val !== '') ? $val : null;
        }

        $this->availableDays = $this->getAvailableDays();
        $this->fee = (float)$this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_PREFERRED_DAY_CHARGE);
    }

    /**
     * Handles updates from the parent component.
     * Resets value if hidden while active, and updates state.
     *
     * @param mixed ...$args
     * @return void
     */
    public function onUpdateState(...$args): void
    {
        if (count($args) === 1 && isset($args[0]) && is_array($args[0]) && array_key_exists(1, $args[0])) {
            $args = $args[0];
        }

        $isDisabled = (bool)($args[1] ?? false);
        $isHidden = (bool)($args[2] ?? false);

        $this->availableDays = $isHidden ? [] : $this->getAvailableDays();

        if (($isHidden || $isDisabled) && $this->hasPreferredDay()) {
            $this->clearValue();
        }

        if ($this->hasPreferredDay() && $this->availableDays !== []) {
            $availableValues = array_map(
                static fn (array $day): string => trim((string)($day['value'] ?? '')),
                $this->availableDays
            );

            if (!in_array(trim((string)$this->preferredDay), $availableValues, true)) {
                $this->clearValue();
            }
        }

        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Clears the selected preferred day and notifies the parent.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->preferredDay = null;
        $this->persistFieldUpdate('date', '', self::SERVICE_CODE);
        $this->emitUp('releaseExclusive', self::SERVICE_CODE);
        $this->emitToRefresh('price-summary.total-segments');
    }

    /**
     * Checks whether a preferred day is currently selected.
     *
     * @return bool
     */
    public function hasPreferredDay(): bool
    {
        return trim((string)$this->preferredDay) !== '';
    }

    /**
     * Selects the preferred day explicitly from the rendered vendor options.
     *
     * @param string|null $value
     * @return void
     */
    public function selectPreferredDay(?string $value = null): void
    {
        if ($value === null) {
            $value = $this->preferredDay;
        }

        $value = trim((string)$value);
        $this->preferredDay = $value !== '' ? $value : null;

        if ($this->hasPreferredDay() && !$this->isAvailablePreferredDay($this->preferredDay)) {
            $this->preferredDay = null;
        }

        $this->persistFieldUpdate('date', $this->preferredDay ?? '', self::SERVICE_CODE);
        $this->emitUp($this->hasPreferredDay() ? 'requestExclusive' : 'releaseExclusive', self::SERVICE_CODE);
        $this->emitToRefresh('price-summary.total-segments');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getAvailableDays(): array
    {
        $serviceOption = $this->dhlCheckoutDataProvider->getServiceOptionByCode(self::SERVICE_CODE);
        if (!$serviceOption) {
            return [];
        }

        foreach ($serviceOption->getInputs() as $input) {
            if (!$input instanceof InputInterface || $input->getCode() !== 'date') {
                continue;
            }

            return array_values(array_filter(array_map(
                static function (OptionInterface $option): ?array {
                    $value = trim((string)$option->getValue());
                    if ($value === '' || $value === 'none') {
                        return null;
                    }

                    $label = trim((string)$option->getLabel());

                    return [
                        'value' => $value,
                        'label' => $label !== '' ? $label : $value,
                    ];
                },
                array_filter(
                    $input->getOptions(),
                    static fn ($option): bool => $option instanceof OptionInterface
                )
            )));
        }

        return [];
    }

    /**
     * Checks whether a preferred day value is present in the current vendor options.
     *
     * @param string|null $value
     * @return bool
     */
    private function isAvailablePreferredDay(?string $value): bool
    {
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }

        $availableValues = array_map(
            static fn (array $day): string => trim((string)($day['value'] ?? '')),
            $this->availableDays
        );

        return in_array($value, $availableValues, true);
    }

    /**
     * Handles updates when the preferred day is changed by the user.
     *
     * @param string|null $value
     * @return void
     */
    public function updatedPreferredDay(?string $value): void
    {
        $this->selectPreferredDay($value);
    }
}
