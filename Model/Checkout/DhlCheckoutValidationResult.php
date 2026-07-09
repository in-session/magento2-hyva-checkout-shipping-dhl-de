<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\Checkout;

/**
 * Immutable result object for DHL checkout option validation.
 */
final class DhlCheckoutValidationResult
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        private readonly array $errors
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
