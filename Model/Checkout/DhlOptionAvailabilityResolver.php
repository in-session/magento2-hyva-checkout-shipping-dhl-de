<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\CompatibilityInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;

/**
 * Resolves DHL option availability and compatibility rules for checkout UI state.
 */
final class DhlOptionAvailabilityResolver
{
    private const SUPPORTED_COMPATIBILITY_ACTIONS = [
        CompatibilityInterface::ACTION_HIDE,
        CompatibilityInterface::ACTION_DISABLE,
    ];

    public function __construct(
        private readonly DhlOptionComponentRegistry $componentRegistry,
        private readonly DhlCheckoutDataProvider $dhlCheckoutDataProvider
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableServiceMap(DhlShippingContext $context): array
    {
        if (!$context->isDhlPaket()) {
            return [];
        }

        $serviceMap = [];
        foreach ($this->dhlCheckoutDataProvider->getDhlServiceOptionCodes() as $optionCode) {
            $componentAlias = $this->componentRegistry->getComponentAlias($optionCode);
            if ($componentAlias === null) {
                continue;
            }

            $serviceMap[$componentAlias] = $optionCode;
        }

        return $serviceMap;
    }

    /**
     * @return array<string, string>
     */
    public function getStateBroadcastServiceMap(DhlShippingContext $context): array
    {
        return $this->componentRegistry->getAllServiceMap();
    }


    public function getComponentAlias(string $optionCode): ?string
    {
        return $this->componentRegistry->getComponentAlias($optionCode);
    }

    /**
     * @return array<string, array<string, string[]>>
     */
    public function getRulesMap(): array
    {
        $vendorRulesMap = $this->buildRulesMapFromVendorCompatibilityData();

        return $vendorRulesMap !== [] ? $vendorRulesMap : $this->getFallbackRulesMap();
    }

    /**
     * @return array<string, array<string, string[]>>
     */
    private function buildRulesMapFromVendorCompatibilityData(): array
    {
        $rulesMap = [];
        foreach ($this->dhlCheckoutDataProvider->getDhlCompatibilityData() as $compatibility) {
            $action = $compatibility->getAction();
            if (!in_array($action, self::SUPPORTED_COMPATIBILITY_ACTIONS, true)) {
                continue;
            }

            $masters = $this->normalizeCompatibilityCodes($compatibility->getMasters());
            $subjects = $this->normalizeCompatibilityCodes($compatibility->getSubjects());
            if ($masters === [] || $subjects === []) {
                continue;
            }

            foreach ($masters as $master) {
                foreach ($subjects as $subject) {
                    if ($master === $subject) {
                        continue;
                    }

                    $rulesMap[$master][$action][] = $subject;
                }
            }
        }

        return $this->normalizeRulesMap($rulesMap);
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    private function normalizeCompatibilityCodes(array $codes): array
    {
        $managedOptionCodes = array_flip(array_values($this->componentRegistry->getAllServiceMap()));
        $normalizedCodes = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }

            $optionCode = explode('.', $code, 2)[0];
            if (!isset($managedOptionCodes[$optionCode])) {
                continue;
            }

            $normalizedCodes[] = $optionCode;
        }

        return array_values(array_unique($normalizedCodes));
    }

    /**
     * @param array<string, array<string, string[]>> $rulesMap
     * @return array<string, array<string, string[]>>
     */
    private function normalizeRulesMap(array $rulesMap): array
    {
        $normalizedRulesMap = [];
        foreach ($rulesMap as $master => $actions) {
            foreach (self::SUPPORTED_COMPATIBILITY_ACTIONS as $action) {
                $subjects = array_values(array_unique($actions[$action] ?? []));
                if ($subjects === []) {
                    continue;
                }

                sort($subjects);
                $normalizedRulesMap[$master][$action] = $subjects;
            }
        }

        ksort($normalizedRulesMap);

        return $normalizedRulesMap;
    }

    /**
     * @return array<string, array<string, string[]>>
     */
    private function getFallbackRulesMap(): array
    {
        return [
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION => [
                'hide' => [
                    DhlCodes::SERVICE_OPTION_PREFERRED_DAY,
                    DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
                    DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY,
                    DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY,
                ],
            ],
            DhlCodes::SERVICE_OPTION_PREFERRED_DAY => [
                'hide' => [CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION],
            ],
            DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY => [
                'hide'    => [CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION],
                'disable' => [DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY],
            ],
            DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY => [
                'hide'    => [CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION],
                'disable' => [
                    DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
                    DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY,
                ],
            ],
            DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY => [
                'disable' => [DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY],
            ],
            DhlCodes::SERVICE_OPTION_DELIVERY_TYPE => [],
        ];
    }
}
