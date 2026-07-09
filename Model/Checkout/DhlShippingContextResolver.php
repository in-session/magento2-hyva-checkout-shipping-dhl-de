<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Resolves the current DHL checkout context from the active quote shipping address.
 */
class DhlShippingContextResolver
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function resolve(): DhlShippingContext
    {
        $shippingMethod = null;
        $countryId = null;

        try {
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            if ($shippingAddress) {
                $shippingMethod = $shippingAddress->getShippingMethod() ?: null;
                $countryId = $shippingAddress->getCountryId() ?: null;
            }
        } catch (\Exception $exception) {
            return new DhlShippingContext(null, null, false, false);
        }

        $isDhlPaket = is_string($shippingMethod) && str_starts_with($shippingMethod, 'dhlpaket_');
        $isDomesticGermany = $countryId === 'DE';
        return new DhlShippingContext(
            $shippingMethod,
            $countryId,
            $isDhlPaket,
            $isDomesticGermany
        );
    }
}
