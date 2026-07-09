<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\CompatibilityInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\ValidationRuleInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;

/**
 * Validates DHL checkout option selections against local fallbacks and vendor rules.
 */
final class DhlCheckoutOptionValidator
{
    private const DEFAULT_POSTNUMBER_MIN_LENGTH = 6;
    private const DEFAULT_POSTNUMBER_MAX_LENGTH = 10;
    private const DEFAULT_DROPOFF_DETAILS_MAX_LENGTH = 40;
    private const DROPOFF_SPECIAL_CHARS = ['<', '>', "\n", "\r", '\\', "'", '"', ';', '+'];
    private const DROPOFF_PICKUP_ADDRESS_TERMS = [
        'paketbox',
        'packstation',
        'paketshop',
        'postfach',
        'postfiliale',
        'filiale',
        'paketkasten',
        'dhlpaketstation',
        'parcelshop',
        'pakcstation',
        'paackstation',
        'pakstation',
        'backstation',
        'bakstation',
        'wunschfiliale',
        'deutsche post',
    ];

    public function __construct(
        private readonly DhlOptionSelectionManager $dhlOptionSelectionManager,
        private readonly DhlCheckoutDataProvider $dhlCheckoutDataProvider
    ) {
    }

    public function validate(DhlShippingContext $context): DhlCheckoutValidationResult
    {
        if (!$context->isDhlPaket()) {
            return new DhlCheckoutValidationResult([]);
        }

        if ($this->dhlOptionSelectionManager->getAddressId() === null) {
            return new DhlCheckoutValidationResult(['address' => __('No shipping address selected')]);
        }

        $errors = array_merge(
            $this->validatePreferredNeighbor(
                $this->dhlOptionSelectionManager->loadByOptionCode(DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY)
            ),
            $this->validateDeliveryLocation(
                $this->dhlOptionSelectionManager->loadByOptionCode(CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION)
            ),
            $this->validateDropoffDelivery(
                $this->dhlOptionSelectionManager->loadByOptionCode(DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY)
            )
        );

        return new DhlCheckoutValidationResult($errors);
    }

    /**
     * @param array<string, mixed> $selections
     * @return array<string, mixed>
     */
    private function validatePreferredNeighbor(array $selections): array
    {
        $hasName = !empty($selections['name']) && !empty($selections['name']->getInputValue());
        $hasAddress = !empty($selections['address']) && !empty($selections['address']->getInputValue());

        if (($hasName && !$hasAddress) || (!$hasName && $hasAddress)) {
            return ['neighbor' => __('Please enter both neighbor name and address.')];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $selections
     * @return array<string, mixed>
     */
    private function validateDeliveryLocation(array $selections): array
    {
        $accountNumberCode = DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER;
        $type = !empty($selections['type']) ? (string) $selections['type']->getInputValue() : '';
        $accountNumber = !empty($selections[$accountNumberCode])
            ? (string) $selections[$accountNumberCode]->getInputValue()
            : '';
        $postnumberRules = $this->getPostnumberValidationRules();
        $isRequired = $this->isInputRequiredByCompatibilityRule(
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION,
            $accountNumberCode,
            ['type' => $type]
        );

        if ($isRequired && $accountNumber === '') {
            return ['postnumber' => __('DHL Post number is required for locker delivery.')];
        }

        if ($accountNumber !== '' && !$this->isValidPostnumber($accountNumber, $postnumberRules)) {
            return ['postnumber' => __('Please enter a valid DHL post number with 6 to 10 digits.')];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $selections
     * @return array<string, mixed>
     */
    private function validateDropoffDelivery(array $selections): array
    {
        if (empty($selections['details'])) {
            return [];
        }

        $details = trim((string) $selections['details']->getInputValue());
        if ($details === '') {
            return [];
        }

        $rules = $this->getDropoffDetailsValidationRules();
        if (mb_strlen($details) > $rules['maxLength']) {
            return [
                'preferredLocation' => __('Please enter a drop-off location with no more than %1 characters.', $rules['maxLength']),
            ];
        }

        if ($rules['noHtmlTags'] && $details !== strip_tags($details)) {
            return ['preferredLocation' => __('Please enter a drop-off location without HTML tags.')];
        }

        if ($rules['noSpecialChars'] && $this->containsAny($details, self::DROPOFF_SPECIAL_CHARS)) {
            return ['preferredLocation' => __('Please enter a drop-off location without special characters.')];
        }

        if ($rules['noPickupAddress'] && $this->containsAny($details, self::DROPOFF_PICKUP_ADDRESS_TERMS, true)) {
            return ['preferredLocation' => __('Please do not use a parcel shop, post office, or similar pickup address as drop-off location.')];
        }

        return [];
    }

    /**
     * @return array{maxLength: int, noHtmlTags: bool, noSpecialChars: bool, noPickupAddress: bool}
     */
    private function getDropoffDetailsValidationRules(): array
    {
        $rules = [
            'maxLength' => self::DEFAULT_DROPOFF_DETAILS_MAX_LENGTH,
            'noHtmlTags' => false,
            'noSpecialChars' => false,
            'noPickupAddress' => false,
        ];

        $vendorRules = $this->dhlCheckoutDataProvider->getInputValidationRules(
            DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
            'details'
        );
        if ($vendorRules === []) {
            return $rules;
        }

        foreach ($vendorRules as $rule) {
            if (!$rule instanceof ValidationRuleInterface) {
                continue;
            }

            if ($rule->getName() === 'maxLength' && is_numeric($rule->getParam())) {
                $rules['maxLength'] = (int) $rule->getParam();
                continue;
            }

            if ($rule->getName() === 'validate-no-html-tags') {
                $rules['noHtmlTags'] = true;
                continue;
            }

            if ($rule->getName() === 'nrshipping-validate-no-special-chars') {
                $rules['noSpecialChars'] = true;
                continue;
            }

            if ($rule->getName() === 'nrshipping-validate-no-pickup-address') {
                $rules['noPickupAddress'] = true;
            }
        }

        return $rules;
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $value, array $needles, bool $caseInsensitive = false): bool
    {
        $haystack = $caseInsensitive ? mb_strtolower($value) : $value;

        foreach ($needles as $needle) {
            $needle = $caseInsensitive ? mb_strtolower($needle) : $needle;
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{numeric: bool, minLength: int, maxLength: int}
     */
    private function getPostnumberValidationRules(): array
    {
        $rules = [
            'numeric' => true,
            'minLength' => self::DEFAULT_POSTNUMBER_MIN_LENGTH,
            'maxLength' => self::DEFAULT_POSTNUMBER_MAX_LENGTH,
        ];

        $vendorRules = $this->dhlCheckoutDataProvider->getInputValidationRules(
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION,
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
        );
        if ($vendorRules === []) {
            return $rules;
        }

        foreach ($vendorRules as $rule) {
            if (!$rule instanceof ValidationRuleInterface) {
                continue;
            }

            if ($rule->getName() === 'validate-number') {
                $rules['numeric'] = true;
                continue;
            }

            if ($rule->getName() === 'minLength' && is_numeric($rule->getParam())) {
                $rules['minLength'] = (int) $rule->getParam();
                continue;
            }

            if ($rule->getName() === 'maxLength' && is_numeric($rule->getParam())) {
                $rules['maxLength'] = (int) $rule->getParam();
            }
        }

        return $rules;
    }

    /**
     * @param array{numeric: bool, minLength: int, maxLength: int} $rules
     */
    private function isValidPostnumber(string $postnumber, array $rules): bool
    {
        $length = mb_strlen($postnumber);
        if ($length < $rules['minLength'] || $length > $rules['maxLength']) {
            return false;
        }

        return !$rules['numeric'] || (bool) preg_match('/^\d+$/', $postnumber);
    }

    /**
     * @param array<string, string> $inputValues
     */
    private function isInputRequiredByCompatibilityRule(
        string $optionCode,
        string $inputCode,
        array $inputValues
    ): bool {
        $compoundInputCode = $optionCode . '.' . $inputCode;

        foreach ($this->dhlCheckoutDataProvider->getDhlCompatibilityData() as $compatibility) {
            if (!$compatibility instanceof CompatibilityInterface
                || $compatibility->getAction() !== CompatibilityInterface::ACTION_REQUIRE
                || !in_array($compoundInputCode, $compatibility->getSubjects(), true)
            ) {
                continue;
            }

            foreach ($compatibility->getMasters() as $master) {
                $masterParts = explode('.', $master, 2);
                if (($masterParts[0] ?? '') !== $optionCode) {
                    continue;
                }

                $masterInputCode = $masterParts[1] ?? '';
                if (($inputValues[$masterInputCode] ?? '') === $compatibility->getTriggerValue()) {
                    return true;
                }
            }
        }

        return ($inputValues['type'] ?? '') === 'locker';
    }
}
