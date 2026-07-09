<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;

/**
 * Central registry for DHL option codes and their Magewire component aliases.
 */
final class DhlOptionComponentRegistry
{
    /**
     * @return array<string, string>
     */
    public function getDomesticServiceMap(): array
    {
        return [
            'checkout.shipping.method.dhlpaket_bestway_preferred_day'       => DhlCodes::SERVICE_OPTION_PREFERRED_DAY,
            'checkout.shipping.method.dhlpaket_bestway_preferred_location'  => DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
            'checkout.shipping.method.dhlpaket_bestway_preferred_neighbor'  => DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY,
            'checkout.shipping.method.dhlpaket_bestway_no_neighbor'         => DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY,
            'checkout.shipping.method.dhlpaket_bestway_parcel_packstation'  => CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION,
            'checkout.shipping.method.gogreen_plus'                         => DhlCodes::SERVICE_OPTION_GOGREEN_PLUS,
            'checkout.shipping.method.dhlpaket_bestway_parcel_announcement' => DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getInternationalServiceMap(): array
    {
        return [
            'checkout.shipping.method.dhlpaket_bestway_delivery_type' => DhlCodes::SERVICE_OPTION_DELIVERY_TYPE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getAllServiceMap(): array
    {
        return array_merge($this->getDomesticServiceMap(), $this->getInternationalServiceMap());
    }

    public function getComponentAlias(string $optionCode): ?string
    {
        $componentAliases = array_flip($this->getAllServiceMap());

        return $componentAliases[$optionCode] ?? null;
    }
}
