<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Parcel Announcement" shipping option.
 *
 * Handles enabling/disabling the option and persisting its state.
 */
class ParcelAnnouncement extends ShippingOptions
{
    /** 
     * Indicates whether the "Parcel Announcement" service is enabled.
     * 
     * @var bool
     */
    public ?bool $parcelAnnouncement = false;

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /** @var array Magewire event listeners */
    protected $listeners = [
        'updateState' => 'onUpdateState',
    ];

    /**
     * Lifecycle method called when the component is mounted.
     * Loads the selection state for the "Parcel Announcement" option.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->parcelAnnouncement = (bool)$quoteSelections['enabled']->getInputValue();
        }
    }

    /**
     * Handles state updates from the parent component.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @return void
     */
    public function onUpdateState(array $activeServiceCodes, bool $isDisabled, bool $isHidden): void
    {
        if (($isHidden || $isDisabled) && $this->parcelAnnouncement) {
            $this->clearValue();
        }

        $this->disabled = $isDisabled;
        $this->hidden = $isHidden;
    }

    /**
     * Clears the selected parcel announcement value from the quote.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->parcelAnnouncement = false;
        $this->persistFieldUpdate('enabled', false, DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);
    }

    /**
     * Handler for when the parcelAnnouncement property is updated.
     * Persists the new value for the "Parcel Announcement" shipping option.
     *
     * @param bool $value The new enabled/disabled state.
     * @return mixed Result of the field persistence operation.
     */
    public function updatedParcelAnnouncement(?bool $value): mixed { 
		$this->parcelAnnouncement = (bool) $value; 
		
		return $this->persistFieldUpdate( 
			'enabled', 
			$this->parcelAnnouncement, 
			DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT 
		); 
	}
	
	public function toggleParcelAnnouncement(): mixed
	{
		return $this->updatedParcelAnnouncement(!(bool) $this->parcelAnnouncement);
	}
}
