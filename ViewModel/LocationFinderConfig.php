<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\ViewModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Netresearch\ShippingUi\Model\LocationFinderConfigProvider as VendorLocationFinderConfigProvider;

/**
 * Provides storefront-safe configuration values for the DHL location finder.
 */
final class LocationFinderConfig implements ArgumentInterface
{
    private const DHLPAKET_CARRIER_CODE = 'dhlpaket';
    private const LOCATION_SEARCH_PATH = '/V1/nrshipping/delivery-locations/%s/search';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $locationFinderConfig = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly VendorLocationFinderConfigProvider $locationFinderConfigProvider,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getLocationSearchUrl(): string
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException) {
            return '';
        }

        $baseUrl = rtrim($this->urlBuilder->getBaseUrl(), '/');

        if ($baseUrl === '') {
            $baseUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/');
        }
        $storeCode = trim((string) $store->getCode(), '/');
        if ($baseUrl === '' || $storeCode === '') {
            return '';
        }

        return sprintf(
            '%s/rest/%s%s',
            $baseUrl,
            rawurlencode($storeCode),
            sprintf(self::LOCATION_SEARCH_PATH, self::DHLPAKET_CARRIER_CODE)
        );
    }

    public function getMapboxApiToken(): string
    {
        return $this->getLocationFinderConfigValue('maptileApiToken');
    }

    public function getMapTileUrl(): string
    {
        return $this->getLocationFinderConfigValue('maptileUrl');
    }

    public function getMapAttribution(): string
    {
        return $this->getLocationFinderConfigValue('mapAttribution');
    }

    private function getLocationFinderConfigValue(string $key): string
    {
        return (string) ($this->getLocationFinderConfig()[$key] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocationFinderConfig(): array
    {
        if ($this->locationFinderConfig !== null) {
            return $this->locationFinderConfig;
        }

        try {
            $config = $this->locationFinderConfigProvider->getConfig();
        } catch (\Throwable) {
            $this->locationFinderConfig = [];

            return $this->locationFinderConfig;
        }

        $locationFinderConfig = $config['locationFinder'] ?? [];
        $this->locationFinderConfig = is_array($locationFinderConfig) ? $locationFinderConfig : [];

        return $this->locationFinderConfig;
    }
}
