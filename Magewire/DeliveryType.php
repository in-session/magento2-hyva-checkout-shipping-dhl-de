<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for the DHL "Delivery Type" option (door or drop-off point).
 */
class DeliveryType extends ShippingOptions
{
    /** @var string|null Currently selected delivery type */
    public ?string $selectedType = null;

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /** @var array Magewire event listeners */
    protected $listeners = [
        'updateState' => 'updateState',
    ];
    
    /** @var float Fee for "No Neighbor Delivery" */
    public float $fee = 0.0;
    
    /**
     * Magewire lifecycle hook.
     * Loads the selection from the database on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);

        if ($quoteSelections && isset($quoteSelections['details'])) {
            $this->selectedType = (string)$quoteSelections['details']->getInputValue();
        }
        
        $this->fee = (float)$this->scopeConfig->getValue(DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);
    }

    /**
     * Updates the component state (hidden/disabled/active codes) based on broadcast from parent.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @return void
     */
    public function updateState(array $activeServiceCodes, bool $isDisabled, bool $isHidden): void
    {
        if (($isHidden || $isDisabled) && $this->selectedType !== null) {
            $this->resetFields();
        }
        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Resets the delivery type selection and updates the parent state.
     *
     * @param bool $notifyParent
     * @return void
     */
    public function resetFields(bool $notifyParent = true): void
    {
        $this->selectedType = null;
        $this->persistFieldUpdate('details', '', DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);

        if ($notifyParent) {
            $this->emitUp('releaseExclusive', DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);
        }
    }

    /**
     * Handles changes to the selected delivery type.
     *
     * @param string|null $value
     * @return void
     */
    public function updatedSelectedType(?string $value): void
    {
        $this->selectedType = $value ?? null;
        $isActive = $this->selectedType !== null && $this->selectedType !== '';
        $this->persistFieldUpdate('details', (string)$this->selectedType, DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);
        $this->emitUp($isActive ? 'requestExclusive' : 'releaseExclusive', DhlCodes::SERVICE_OPTION_DELIVERY_TYPE);
    }
}
