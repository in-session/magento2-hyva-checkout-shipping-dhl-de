<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Preferred Location" (Drop-off Delivery) option.
 */
class PreferredLocation extends ShippingOptions
{
    /** @var string Constant for the service code */
    public const SERVICE_CODE = DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY;

    public const MAX_LENGTH = 40;

    /** @var string|null User's entered preferred location or null */
    public ?string $preferredLocation = null;

    /** @var bool Whether the option is disabled */
    public bool $disabled = false;

    /** @var bool Whether the option is hidden */
    public bool $hidden = false;

    /** @var array Magewire event listeners */
    protected $listeners = ['updateState' => 'onUpdateState'];

    /**
     * Magewire lifecycle hook.
     * Loads the preferred location from the database on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if ($quoteSelections && isset($quoteSelections['details'])) {
            $value = trim((string)$quoteSelections['details']->getInputValue());
            $this->preferredLocation = $value !== '' ? mb_substr($value, 0, self::MAX_LENGTH) : null;
        }
    }

    /**
     * Handles state updates from the parent component.
     * If hidden while set, resets the value. Updates disabled/hidden state.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @return void
     */
    public function onUpdateState(array $activeServiceCodes, bool $isDisabled, bool $isHidden): void
    {
        if (($isHidden || $isDisabled) && trim((string)$this->preferredLocation) !== '') {
            $this->clearValue();
        }
        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Clears the preferred location value and notifies the parent.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->preferredLocation = null;
        $this->persistFieldUpdate('details', '', self::SERVICE_CODE);
        $this->emitUp('releaseExclusive', self::SERVICE_CODE);
    }

    /**
     * Handles updates when the preferred location field changes.
     *
     * @param string|null $value
     * @return string|null The updated value
     */
    public function updatedPreferredLocation(?string $value): ?string
    {
        $this->preferredLocation = $value ? mb_substr(trim($value), 0, self::MAX_LENGTH) : null;

        $this->persistFieldUpdate('details', (string)$this->preferredLocation, self::SERVICE_CODE);
        $this->emitUp(
            $this->preferredLocation !== null && $this->preferredLocation !== ''
                ? 'requestExclusive'
                : 'releaseExclusive',
            self::SERVICE_CODE
        );

        return $this->preferredLocation;
    }
}
