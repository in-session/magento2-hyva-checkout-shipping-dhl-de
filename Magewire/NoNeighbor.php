<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Dhl\Paket\Model\Config\ModuleConfig;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "No Neighbor Delivery" option.
 */
class NoNeighbor extends ShippingOptions
{
    public const SERVICE_CODE = DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY;

    /** @var bool Whether the "No Neighbor" option is selected */
    public ?bool $noNeighbor = false;

    /** @var float Fee for "No Neighbor Delivery" */
    public float $fee = 0.0;

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /** @var array Magewire event listeners */
    protected $listeners = [
        'updateState' => 'onUpdateState',
    ];

    /**
     * Magewire lifecycle hook.
     * Loads the current state from the database and initializes the fee.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->noNeighbor = (bool)$quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float)$this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_NO_NEIGHBOR_DELIVERY_CHARGE);
    }

    /**
     * Handles state updates from the parent component (DeliveryOptions).
     * Reacts to changes in active codes, disabled, and hidden state.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @return void
     */
    public function onUpdateState(array $activeServiceCodes, bool $isDisabled, bool $isHidden): void
    {
        // If the component should be hidden while it is still active, reset itself.
        if (($isHidden || $isDisabled) && $this->noNeighbor) {
            $this->clearValue();
        }

        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Resets the selection and releases exclusivity.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->noNeighbor = false;
        $this->persistFieldUpdate('enabled', false, self::SERVICE_CODE);
        $this->emitUp('releaseExclusive', self::SERVICE_CODE);
    }

    /**
     * Handler when the user changes the checkbox state.
     *
     * @param bool $value
     * @return mixed
     */
	public function updatedNoNeighbor(?bool $value): mixed
	{
		$this->noNeighbor = (bool) $value;

		$res = $this->persistFieldUpdate('enabled', $this->noNeighbor, self::SERVICE_CODE);

		$this->emitUp(
			$this->noNeighbor ? 'requestExclusive' : 'releaseExclusive',
			self::SERVICE_CODE
		);

		$this->emitToRefresh('price-summary.total-segments');

		return $res;
	}

	public function toggleNoNeighbor(): mixed
	{
		return $this->updatedNoNeighbor(!(bool) $this->noNeighbor);
	}
}
