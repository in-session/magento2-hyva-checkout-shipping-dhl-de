<?php
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes 2022-present. All rights reserved.
 * This product is licensed per Magento install
 * See https://hyva.io/license
 */

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Phrase;

class Tooltip implements ArgumentInterface
{
    /**
     * @var \Magento\Framework\View\LayoutInterface
     */
    protected LayoutInterface $layout;

    /**
     * @param \Magento\Framework\View\LayoutInterface $layout
     */
    public function __construct(
        LayoutInterface $layout
    ) {
        $this->layout = $layout;
    }

    /**
     * @param string $text
     * @return string
     */
    public function render(Phrase $text): string
    {
        $block = $this->layout->createBlock(Template::class);
        $block->setTemplate('Hyva_ShippingDhlDe::tooltip.phtml')->setText($text);
        
        return $block->toHtml();
    }
}