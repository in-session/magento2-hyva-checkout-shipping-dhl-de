<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\CarrierDataInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\CompatibilityInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\InputInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\ValidationRuleInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\CheckoutManagementInterface;

/**
 * Provides processed Netresearch/DHL checkout data for the current quote address.
 */
final class DhlCheckoutDataProvider
{
    private const DHLPAKET_CARRIER_CODE = 'dhlpaket';

    /**
     * @var array<string, CarrierDataInterface|null>
     */
    private array $carrierDataCache = [];

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutManagementInterface $checkoutManagement
    ) {
    }

    /**
     * Returns processed DHL service options for the current quote shipping address.
     *
     * @return ShippingOptionInterface[]
     */
    public function getDhlServiceOptions(): array
    {
        $carrier = $this->getDhlCarrierData();
        if (!$carrier) {
            return [];
        }

        return array_values(array_filter(
            $carrier->getServiceOptions(),
            static fn ($serviceOption): bool => $serviceOption instanceof ShippingOptionInterface
        ));
    }

    /**
     * Returns processed DHL compatibility data for the current quote shipping address.
     *
     * @return CompatibilityInterface[]
     */
    public function getDhlCompatibilityData(): array
    {
        $carrier = $this->getDhlCarrierData();
        if (!$carrier) {
            return [];
        }

        return array_values(array_filter(
            $carrier->getCompatibilityData(),
            static fn ($compatibility): bool => $compatibility instanceof CompatibilityInterface
        ));
    }

    public function getDhlCarrierData(): ?CarrierDataInterface
    {
        try {
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            if (!$shippingAddress) {
                return null;
            }

            $countryId = (string) ($shippingAddress->getCountryId() ?: '');
            if ($countryId === '') {
                return null;
            }

            $postalCode = (string) ($shippingAddress->getPostcode() ?: '');
            $cacheKey = $countryId . '|' . $postalCode;
            if (array_key_exists($cacheKey, $this->carrierDataCache)) {
                return $this->carrierDataCache[$cacheKey];
            }

            $shippingData = $this->checkoutManagement->getCheckoutData($countryId, $postalCode);
            foreach ($shippingData->getCarriers() as $carrier) {
                if (!$carrier instanceof CarrierDataInterface) {
                    continue;
                }

                if ($carrier->getCode() !== self::DHLPAKET_CARRIER_CODE) {
                    continue;
                }

                return $this->carrierDataCache[$cacheKey] = $carrier;
            }

            return $this->carrierDataCache[$cacheKey] = null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns processed DHL service option codes for the current quote shipping address.
     *
     * @return string[]
     */
    public function getDhlServiceOptionCodes(): array
    {
        $optionCodes = array_map(
            static fn (ShippingOptionInterface $serviceOption): string => $serviceOption->getCode(),
            $this->getDhlServiceOptions()
        );

        return array_values(array_unique($optionCodes));
    }

    public function getServiceOptionByCode(string $optionCode): ?ShippingOptionInterface
    {
        foreach ($this->getDhlServiceOptions() as $serviceOption) {
            if ($serviceOption->getCode() === $optionCode) {
                return $serviceOption;
            }
        }

        return null;
    }

    /**
     * Returns vendor validation rules for one DHL service option input.
     *
     * @return ValidationRuleInterface[]
     */
    public function getInputValidationRules(string $optionCode, string $inputCode): array
    {
        $serviceOption = $this->getServiceOptionByCode($optionCode);
        if (!$serviceOption) {
            return [];
        }

        foreach ($serviceOption->getInputs() as $input) {
            if (!$input instanceof InputInterface || $input->getCode() !== $inputCode) {
                continue;
            }

            return array_values(array_filter(
                $input->getValidationRules(),
                static fn ($rule): bool => $rule instanceof ValidationRuleInterface
            ));
        }

        return [];
    }
}
