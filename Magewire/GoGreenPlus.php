<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL GoGreen Plus option.
 */
class GoGreenPlus extends ShippingOptions
{
    public const SERVICE_CODE = DhlCodes::SERVICE_OPTION_GOGREEN_PLUS;

    /** @var bool|null Whether GoGreen Plus is enabled */
    public ?bool $goGreenPlusEnabled = false;

    /** @var float Additional fee for GoGreen Plus */
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
     * Loads selection from the database and initializes the component.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(self::SERVICE_CODE);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->goGreenPlusEnabled = (bool) $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_GOGREEN_PLUS_CHARGE);
        $this->emitUp('requestStateBroadcast');
    }

    /**
     * Handles state updates from the parent component (DeliveryOptions).
     * Reacts to changes in disabled/hidden state.
     *
     * @param mixed ...$args
     * @return void
     */
    public function onUpdateState(...$args): void
    {
        // Handles both array-based and variadic Magewire event arguments.
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        $activeService = $args[0] ?? null;
        $isDisabled = (bool)($args[1] ?? false);
        $isHidden = (bool)($args[2] ?? false);

        if (($isHidden || $isDisabled) && $this->goGreenPlusEnabled) {
            $this->clearValue();
        }

        $this->disabled = $isDisabled;
        $this->hidden   = $isHidden;
    }

    /**
     * Clears the selected GoGreen Plus value from the quote.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->goGreenPlusEnabled = false;
        $this->persistFieldUpdate('enabled', false, self::SERVICE_CODE);
        $this->emitToRefresh('price-summary.total-segments');
    }

    /**
     * Handles when GoGreen Plus is enabled or disabled.
     *
     * @param bool|null $value
     * @return mixed
     */
    public function updatedGoGreenPlusEnabled(?bool $value): mixed 
	{ 
		$this->goGreenPlusEnabled = (bool) $value; 		
		$this->emitToRefresh('price-summary.total-segments'); 
		
		return $this->persistFieldUpdate('enabled', $this->goGreenPlusEnabled, self::SERVICE_CODE); 
	}
	
	public function toggleGoGreenPlus(): mixed
	{
		return $this->updatedGoGreenPlusEnabled(!(bool) $this->goGreenPlusEnabled);
	}
}
