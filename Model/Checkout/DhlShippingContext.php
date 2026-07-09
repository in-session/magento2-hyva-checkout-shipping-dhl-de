<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

/**
 * Immutable checkout context for DHL Paket shipping option decisions.
 */
final class DhlShippingContext
{
    public function __construct(
        private readonly ?string $shippingMethod,
        private readonly ?string $countryId,
        private readonly bool $isDhlPaket,
        private readonly bool $isDomesticGermany
    ) {
    }

    public function isDhlPaket(): bool
    {
        return $this->isDhlPaket;
    }

    public function isDomesticGermany(): bool
    {
        return $this->isDomesticGermany;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getShippingMethod(): ?string
    {
        return $this->shippingMethod;
    }
}
