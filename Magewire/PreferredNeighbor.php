<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Preferred Neighbor" delivery option.
 */
class PreferredNeighbor extends ShippingOptions
{
    /** @var string Constant for the service code */
    public const SERVICE_CODE = DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY;

    /** @var string|null Name of the preferred neighbor */
    public ?string $preferredNeighborName = null;

    /** @var string|null Address of the preferred neighbor */
    public ?string $preferredNeighborAddress = null;

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
     * Loads neighbor details from the database on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if (isset($quoteSelections['name'])) {
            $value = (string) $quoteSelections['name']->getInputValue();
            $this->preferredNeighborName = $value !== '' ? $value : null;
        }

        if (isset($quoteSelections['address'])) {
            $value = (string) $quoteSelections['address']->getInputValue();
            $this->preferredNeighborAddress = $value !== '' ? $value : null;
        }
    }

    /**
     * Handles state updates from the parent component.
     * If hidden while set, resets the neighbor fields.
     *
     * @param array $activeServiceCodes
     * @param bool $isDisabled
     * @param bool $isHidden
     * @return void
     */
    public function onUpdateState(array $activeServiceCodes, bool $isDisabled, bool $isHidden): void
    {
        if (
            ($isHidden || $isDisabled)
            && (trim((string)$this->preferredNeighborName) !== '' || trim((string)$this->preferredNeighborAddress) !== '')
        ) {
            $this->resetFields();
        }
        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Resets neighbor name and address and notifies the parent if needed.
     *
     * @param bool $notifyParent
     * @return void
     */
    public function resetFields(bool $notifyParent = true): void
    {
        $this->preferredNeighborName = null;
        $this->preferredNeighborAddress = null;

        $this->persistFieldUpdate('name', '', self::SERVICE_CODE);
        $this->persistFieldUpdate('address', '', self::SERVICE_CODE);

        if ($notifyParent) {
            $this->emitUp('releaseExclusive', self::SERVICE_CODE);
        }
    }

    /**
     * Handles updates when the neighbor name is changed by the user.
     *
     * @param string|null $value
     * @return string|null The updated name
     */
    public function updatedPreferredNeighborName(?string $value): ?string
    {
        $this->preferredNeighborName = $value ? trim($value) : null;
        $active = (
            ($this->preferredNeighborName !== null && $this->preferredNeighborName !== '') ||
            ($this->preferredNeighborAddress !== null && $this->preferredNeighborAddress !== '')
        );
        $this->persistFieldUpdate('name', (string)$this->preferredNeighborName, self::SERVICE_CODE);
        $this->emitUp($active ? 'requestExclusive' : 'releaseExclusive', self::SERVICE_CODE);

        return $this->preferredNeighborName;
    }

    /**
     * Handles updates when the neighbor address is changed by the user.
     *
     * @param string|null $value
     * @return string|null The updated address
     */
    public function updatedPreferredNeighborAddress(?string $value): ?string
    {
        $this->preferredNeighborAddress = $value ? trim($value) : null;
        $active = (
            ($this->preferredNeighborName !== null && $this->preferredNeighborName !== '') ||
            ($this->preferredNeighborAddress !== null && $this->preferredNeighborAddress !== '')
        );
        $this->persistFieldUpdate('address', (string)$this->preferredNeighborAddress, self::SERVICE_CODE);
        $this->emitUp($active ? 'requestExclusive' : 'releaseExclusive', self::SERVICE_CODE);

        return $this->preferredNeighborAddress;
    }
}
