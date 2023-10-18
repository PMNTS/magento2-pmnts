<?php

namespace PMNTS\Gateway\Block\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use PMNTS\Gateway\Helper\Data;

/**
 * @api
 * @since 100.1.0
 */
class CardRenderer extends AbstractCardRenderer
{
    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     * @return bool
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === Data::METHOD_CODE;
    }

    /**
     * Get Number Last 4 Digits
     *
     * @return string
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['maskedCC'] ?? '';
    }

    /**
     * Get expire data
     *
     * @return string
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['expirationDate'] ?? '';
    }

    /**
     * Get Icon url
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['url'] ?? '';
    }

    /**
     * Get Icon height
     *
     * @return int
     */
    public function getIconHeight(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['height'] ?? 0;
    }

    /**
     * Get Icon width
     *
     * @return int
     */
    public function getIconWidth(): int
    {
        return $this->getIconForType($this->getTokenDetails()['type'])['width'] ?? 0;
    }
}
