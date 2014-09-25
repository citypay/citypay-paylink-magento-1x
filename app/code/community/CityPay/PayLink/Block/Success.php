<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	CityPay
 * @package	CityPay_PayLink
 * @copyright	Copyright (c) 2014 CityPay (http://www.citypay.com/)
 */

class CityPay_PayLink_Block_Success extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $successUrl = Mage::getUrl('*/*/success', array('_nosid' => true));

        $html	= '<html>'
        		. '<meta http-equiv="refresh" content="0; URL='.$successUrl.'">'
        		. '<body>'
        		. '<p>' . $this->__('Your payment has been successfully processed.') . '</p>'
        		. '<p>' . $this->__('Please click <a href="%s">here</a> if you are not redirected automatically.', $successUrl) . '</p>'
        		. '</body></html>';

        return $html;
    }
}
