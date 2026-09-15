<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ReadonlySpecificCountry extends Field
{
    /**
     * Render form element row as disabled and read-only.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->setDisabled(true);
        $element->setReadonly(true);
        $element->setIsDisableInheritance(true);
        $element->setData('can_be_empty', false);

        return parent::render($element);
    }

    /**
     * Render element HTML markup as disabled and read-only without submittable inputs.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setDisabled(true);
        $element->setReadonly(true);
        $element->setData('can_be_empty', false);

        $html = $element->getElementHtml();
        // Remove any hidden inputs that could submit empty values in POST
        $html = preg_replace('/<input\s+type="hidden"[^>]*>/i', '', $html);
        // Remove the name attribute from the select so the browser never submits it
        $html = preg_replace('/(\sname=")[^"]*(")/i', '', (string)$html);

        return (string)$html;
    }
}
