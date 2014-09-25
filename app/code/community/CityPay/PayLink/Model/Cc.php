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

class CityPay_PayLink_Model_Cc extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'paylink_cc';

	protected $_isGateway			= true;
	protected $_canAuthorize		= true;
	protected $_canCapture			= true;
	protected $_canCapturePartial		= false;
	protected $_canRefund			= false;
	protected $_canRefundInvoicePartial	= false;
	protected $_canVoid			= false;
	protected $_canUseInternal		= false;
	protected $_canUseCheckout		= true;
	protected $_canUseForMultishipping	= false;
	protected $_canSaveCc			= false;

	protected $_paymentMethod		= 'cc';
	protected $_defaultLocale		= 'en';

	protected $_formBlockType = 'paylink/form';
	protected $_infoBlockType = 'paylink/info';

	protected $_order;

	public function matchCurrencyConfig($currencyCode, $conf_num) {
		$conf_code = trim($this->getConfigData('currency'.$conf_num));
		$conf_mid = trim($this->getConfigData('merchant_id'.$conf_num));
		$conf_key = trim($this->getConfigData('licence_key'.$conf_num));
		if (empty($conf_code)) { return null; }		// Currency code not configured
		if (empty($conf_mid)) { return null; }		// Merchant ID not configured
		if (empty($conf_key)) { return null; }		// Licence key not configured
		if (!ctype_digit($conf_mid)) { return null; }	// Merchant ID is not numeric
		if (strcasecmp($conf_code,$currencyCode)!=0) { return null; }	// Does not match required currency
		return array($conf_mid,$conf_key);		// Matched, return merchant ID and licence key
	}

	public function getCurrencyConfig($currencyCode) {
		for ($conf_num=1;$conf_num<=5;$conf_num++) {
			$conf=$this->matchCurrencyConfig($currencyCode,$conf_num);
			if (is_array($conf) && !empty($conf)) {
				return $conf;
			}
		}
		return null;
	}

	/* Check if this payment method can be used for the current currency */
	public function canUseForCurrency($currencyCode) {
		$conf=$this->getCurrencyConfig($currencyCode);
		if (is_array($conf) && !empty($conf)) {
			return true;
		}
		return false;	// No configured currency matches the required currency
	}

	protected function _getCheckout() {
		return Mage::getSingleton('checkout/session');
	}

	public function getOrder() {
		if (!$this->_order) {
			$this->_order = $this->getInfoInstance()->getOrder();
		}
		return $this->_order;
	}

	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('paylink/processing/redirect');
	}

	public function getPaymentMethodType() {
		return $this->_paymentMethod;
	}

	private function _buildUrl($part) {
		$url= Mage::getUrl($part,array('_nosid' => true));
		$url = trim(str_replace('&amp;', '&', $url));
		$url = str_replace('192.168.0.61', '213.138.224.57', $url);
		return $url;
	}

	public function getFormFields() {
		// Use the same number_format code as the rest of Magento to ensure the amount matches
		// the amount shown in the cart (ensure same rounding methods are used etc).
		// Magento uses a fixed value of 2 decimal places.
		// CityPay system requires amount in minor units, to get this from number_format pass both the
		// decimal and thousands seperator as empty strings and then cast the result to an int.
		$price		= (int)number_format($this->getOrder()->getGrandTotal(),2,'','');
		$currency	= $this->getOrder()->getOrderCurrencyCode();
		$billing	= $this->getOrder()->getBillingAddress();
		$conf		= $this->getCurrencyConfig($currency);

		if (is_array($conf) && !empty($conf)) {
			$mid	= (int)$conf[0];	// Ensure merchant ID is an int and not a string
			$key	= $conf[1];
		} else {
			$message = 'Currency '.$currency.' not configured';
			Mage::throwException($message);
		}
		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale)) {
			$locale = $locale[0];
		} else {
			$locale = $this->getDefaultLocale();
		}
		$order_id = $this->getOrder()->getRealOrderId();

		// Use product description as transaction description if only 1 item has been
		// ordered, otherwise use the generic pre-configured transaction description.
		$items=$this->getOrder()->getAllVisibleItems();
		$tran_desc='';
		if (count($items)==1) {
			foreach ($items as $item) {
				if ($item->getQtyOrdered()==1) {
					$tran_desc=trim($item->getName());
				}
			}
		}
		if (empty($tran_desc)) {
			$tran_desc = trim($this->getConfigData('tran_desc'));
			if (empty($tran_desc) || (strcasecmp($tran_desc,'Your purchase from StoreName')==0)) {
				$tran_desc = 'Your purchase from '.Mage::app()->getStore()->getName();
			}
			$tran_desc = str_replace('{order}', $order_id, $tran_desc);
		}


		$params =	array(
			'merchantid'	=> $mid,
			'licenceKey'	=> $key,
			'identifier'	=> $order_id,
			'amount'	=> $price,
			//'test'	=> 'simulator',
			'test'		=> ($this->getConfigData('transaction_testmode') == '0') ? 'false' : 'true',
			'clientVersion'	=> 'Magento '.Mage::getVersion(),
			'cardholder'	=> array(
				'firstName'	=> Mage::helper('core')->removeAccents($billing->getFirstname()),
				'lastName'	=> Mage::helper('core')->removeAccents($billing->getLastname()),
				'email'		=> $this->getOrder()->getCustomerEmail(),
				//'phone'	=> $billing->getTelephone(),
				'address'	=> array (
					'address1'	=> Mage::helper('core')->removeAccents($billing->getStreet(1)),
					'address2'	=> Mage::helper('core')->removeAccents($billing->getStreet(2)),
					'address3'	=> Mage::helper('core')->removeAccents($billing->getCity()),
					'area'		=> Mage::helper('core')->removeAccents($billing->getRegion()),
					'postcode'	=> $billing->getPostcode(),
					'country'	=> $billing->getCountry())
				),
			'cart'		=> array(
				'productInformation'	=> $tran_desc),
			'config'	=> array(
				'lockParams'	=> array('cardholder'),
				'redirect_params' => false,
				'postback_policy' => 'sync',
				'postback' => $this->_buildUrl('paylink/processing/response'),
				'redirect_success' => $this->_buildUrl('paylink/processing/success'),
				'redirect_failure' => $this->_buildUrl('paylink/processing/cancel'))
		);

		$json= json_encode($params);
		$client = new Varien_Http_Client();
		$result = new Varien_Object();
		$client->setUri('https://secure.citypay.com/paylink3/create')
			->setMethod(Zend_Http_Client::POST)
			->setConfig(array('timeout'=>30));
		$client->setRawData($json, "application/json;charset=UTF-8");

		try {
			$response = $client->request();
			$responseBody = $response->getBody();
		} catch (Exception $e) {
			$result->setResponseCode(-1)
				->setResponseReasonCode($e->getCode())
				->setResponseReasonText($e->getMessage());
			//Mage::log($result->getData());
			Mage::throwException('Error connecting to PayLink');
		}

		$results = json_decode($responseBody,true); 
		if ($results['result']!=1) {
			Mage::throwException('Invalid response from PayLink');
		}
		$paylink_url=$results['url'];
		if (empty($paylink_url)) {
			Mage::throwException('No URL obtained from PayLink');
		}
		return $paylink_url;
	}

	public function capture(Varien_Object $payment, $amount) {
		if (!$this->canCapture()) {
			return $this;
		}

		if (Mage::app()->getRequest()->getParam('transno')) {
			// Capture is being called from inside postback response action
			$payment->setStatus(self::STATUS_APPROVED);
			return $this;
		}
		return $this;
	}

	public function canEdit () {
		return false;
	}

	public function canManageBillingAgreements () {
		return false;
	}

	public function canManageRecurringProfiles () {
		return true;
	}

	public function canRefund () {
		return false;
	}

	public function canRefundInvoicePartial() {
		return false;
	}

	public function canRefundPartialPerInvoice() {
		return $this->canRefundInvoicePartial();
	}

	public function canCapturePartial() {
		return false;
	}
}
