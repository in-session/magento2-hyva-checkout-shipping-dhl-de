<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Parcel Packstation" (delivery location) option.
 */
class ParcelPackstation extends ShippingOptions implements EvaluationInterface
{
    public const SERVICE_CODE = CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION;

    /** @var bool Whether the Packstation modal is opened */
    public bool $modalOpened = false;

    /** @var array Shipping address information */
    public array $shippingAddress = [];

    /** @var string Error message for the postnumber field */
    public string $postnumberError = '';

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /**
     * @var array<string, mixed> Delivery location data (state fields)
     */
    public array $deliveryLocation = [
        'enabled'              => false,
        'customerPostnumber'   => '',
        'type'                 => '',
        'id'                   => '',
        'number'               => '',
        'displayName'          => '',
        'company'              => '',
        'countryCode'          => '',
        'postalCode'           => '',
        'city'                 => '',
        'street'               => '',
    ];

    /** @var array Magewire event listeners */
    protected $listeners = [
        'shipping_address_saved'       => 'refreshShippingAddress',
        'guest_shipping_address_saved' => 'refreshShippingAddress',
        'shipping_address_activated'   => 'refreshShippingAddress',
        'parcel_packstation_saved'     => 'setPackstation',
        'parcel_packstation_removed'   => 'clearPackstation',
        'dhlPostnumberUpdated'         => 'updatedDeliveryLocationCustomerPostnumber',
        'updateState'                  => 'onUpdateState',
    ];

    /**
     * Magewire lifecycle hook.
     * Loads the delivery location data from the database and sets up the component state.
     *
     * @return void
     */
    public function mount(): void
    {
        // Load shipping address directly from quote before anything else,
        // so the template does not render "Please provide address" on page load/reload.
        // onUpdateState() sets $shippingAddress too, but fires asynchronously after mount().
        $this->shippingAddress = $this->getShippingAddressFromQuote();

        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if ($quoteSelections) {
            $map = [
                'customerPostnumber' => DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
                'type'               => 'type',
                'id'                 => 'id',
                'number'             => 'number',
                'displayName'        => 'displayName',
                'company'            => 'company',
                'countryCode'        => 'countryCode',
                'postalCode'         => 'postalCode',
                'city'               => 'city',
                'street'             => 'street',
                'enabled'            => 'enabled',
            ];
            foreach ($map as $key => $inputCode) {
                if (isset($quoteSelections[$inputCode])) {
                    $value = $quoteSelections[$inputCode]->getInputValue();
                    $this->deliveryLocation[$key] = ($key === 'enabled') ? (bool)$value : (string)$value;
                }
            }
        }

        if ($this->deliveryLocation['enabled'] && !empty($this->deliveryLocation['id'])) {
            $this->validatePostnumber();
            $this->emitUp('requestExclusive', self::SERVICE_CODE);
        }

        $this->checkAndSetShippingAddress();

        // Request initial state from parent
        $this->emitUp('requestStateBroadcast');
    }

    /**
     * Called when a shipping address is saved or activated.
     * Refreshes $shippingAddress from the quote so the component re-renders correctly.
     *
     * @return void
     */
    public function refreshShippingAddress(): void
    {
        $this->shippingAddress = $this->getShippingAddressFromQuote();
    }

    /**
     * Loads the shipping address from the current quote and returns it
     * in the format expected by the component and template.
     *
     * @return array
     */
    private function getShippingAddressFromQuote(): array
    {
        try {
            $address = $this->checkoutSession->getQuote()->getShippingAddress();

            if (!$address || !$address->getCity()) {
                return [];
            }

            $street = $address->getStreet();

            return [
                'street'      => is_array($street) ? ($street[0] ?? '') : (string) $street,
                'city'        => (string) $address->getCity(),
                'postalCode'  => (string) $address->getPostcode(),
                'countryCode' => (string) $address->getCountryId(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Handles state updates from the parent component.
     * Resets state if hidden while active, and updates disabled/hidden flags.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @param array $shippingAddress
     * @return void
     */
    public function onUpdateState(
        array $activeServiceCodes,
        bool $isDisabled,
        bool $isHidden,
        array $shippingAddress = []
    ): void {
        if (($isHidden || $isDisabled) && $this->deliveryLocation['enabled']) {
            $this->resetData();
        }
        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
        $this->shippingAddress = $shippingAddress;
    }

    /**
     * Resets all delivery location fields and releases exclusivity.
     *
     * @return void
     */
    public function resetData(): void
    {
        foreach ($this->deliveryLocation as $key => &$value) {
            $value = ($key === 'enabled') ? false : '';
            $inputCode = $key === 'customerPostnumber'
                ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                : $key;
            $this->persistFieldUpdate($inputCode, $value, self::SERVICE_CODE);
        }
        $this->emitUp('releaseExclusive', self::SERVICE_CODE);
    }

    /**
     * Sets delivery location data when a Packstation is selected.
     *
     * @param array $data
     * @return void
     */
    public function setPackstation(array $data): void
    {
        if (isset($data['deliveryLocation'])) {
            $data = $data['deliveryLocation'];
        }
        $data = $this->normalizeDeliveryLocationData($data);
        if (!$this->hasRequiredDeliveryLocationAddress($data)) {
            return;
        }
        $this->closeModal();

        foreach ($this->deliveryLocation as $key => $defaultValue) {
            $value = $data[$key] ?? $defaultValue;
            $this->deliveryLocation[$key] = $value;

            $inputCode = $key === 'customerPostnumber'
                ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                : $key;

            $this->persistFieldUpdate($inputCode, (string)$value, self::SERVICE_CODE);
        }

        $this->deliveryLocation['enabled'] = true;
        $this->persistFieldUpdate('enabled', '1', self::SERVICE_CODE);

        $this->validatePostnumber();

        $this->emitUp('requestExclusive', self::SERVICE_CODE);
    }

    /**
     * Normalizes delivery-location payload aliases without touching the Magento quote shipping address.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeDeliveryLocationData(array $data): array
    {
        $aliases = [
            'countryCode' => ['country_code', 'country_id'],
            'postalCode' => ['postal_code', 'postcode'],
            'displayName' => ['display_name'],
            'number' => ['shop_number'],
            'type' => ['shop_type'],
            'id' => ['shop_id'],
        ];

        foreach ($aliases as $target => $sources) {
            if (!empty($data[$target])) {
                continue;
            }

            foreach ($sources as $source) {
                if (!empty($data[$source])) {
                    $data[$target] = $data[$source];
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasRequiredDeliveryLocationAddress(array $data): bool
    {
        return !empty($data['countryCode'])
            && !empty($data['postalCode'])
            && !empty($data['city'])
            && !empty($data['street']);
    }

    /**
     * Evaluation logic for the Hyva Checkout completion.
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (empty($this->deliveryLocation['enabled']) || empty($this->deliveryLocation['id'])) {
            return $resultFactory->createSuccess();
        }
        return $resultFactory->createValidation('validateDhlPostnumber');
    }

    /**
     * Clears all Packstation (delivery location) data.
     *
     * @return void
     */
    public function clearPackstation(): void
    {
        $this->resetData();
    }

    /**
     * Updates the customer postnumber in the delivery location and validates it.
     *
     * @param string $value
     * @return void
     */
    public function updatedDeliveryLocationCustomerPostnumber(string $value): void
    {
        $this->deliveryLocation['customerPostnumber'] = $value;
        $this->validatePostnumber();

        $this->persistFieldUpdate(
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
            $this->deliveryLocation['customerPostnumber'],
            self::SERVICE_CODE
        );
    }

    /**
     * Validates the DHL postnumber for lockers (Packstation).
     *
     * @return void
     */
    private function validatePostnumber(): void
    {
        $type     = (string)($this->deliveryLocation['type'] ?? '');
        $isLocker = (strtolower($type) === 'locker');
        $account  = trim((string) ($this->deliveryLocation['customerPostnumber'] ?? ''));
        $isValid  = (bool) preg_match('/^\d{6,10}$/', $account);

        if ($isLocker && $account === '') {
            $this->postnumberError = (string)__('DHL post number is required for lockers.');
        } elseif ($account !== '' && !$isValid) {
            $this->postnumberError = (string)__('Please enter a valid DHL post number with 6 to 10 digits.');
        } else {
            $this->postnumberError = '';
        }
    }

    /**
     * Sets and saves the current shipping address, if available and valid.
     *
     * @return bool
     */
    public function checkAndSetShippingAddress(): bool
    {
        $address = $this->shippingAddress;

        if (
            empty($address['street']) ||
            empty($address['postalCode']) ||
            empty($address['city']) ||
            empty($address['countryCode'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * Opens the Packstation selection modal.
     *
     * @return bool
     */
    public function openModal(): bool
    {
        if (!$this->checkAndSetShippingAddress()) { return false; }
        $this->modalOpened = true;
        return true;
    }

    /**
     * Closes the Packstation selection modal.
     *
     * @return bool
     */
    public function closeModal(): bool
    {
        $this->modalOpened = false;
        return false;
    }
}