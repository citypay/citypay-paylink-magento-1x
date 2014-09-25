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

class CityPay_PayLink_ProcessingController extends Mage_Core_Controller_Front_Action
{
	protected $_successBlockType = 'paylink/success';
	protected $_failureBlockType = 'paylink/failure';
	protected $_cancelBlockType = 'paylink/cancel';

	protected $_order = NULL;
	protected $_paymentInst = NULL;

	protected function _getCheckout() {
		return Mage::getSingleton('checkout/session');
	}

	protected function _getPendingPaymentStatus() {
		return Mage::helper('paylink')->getPendingPaymentStatus();
	}

	protected function loadOrder($order_id) {
		$this->_order = Mage::getModel('sales/order');
		$this->_order->loadByIncrementId($order_id);
		if (!$this->_order->getId()) {
			Mage::throwException('Order not found');
		}
		$this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
		if (strcasecmp(get_class($this->_paymentInst),'CityPay_PayLink_Model_Cc')!=0) {
			Mage::throwException('Order is not connected to CityPay');
		}
	}

	public function textlog($text) {
		$text=trim($text);
		$log_fh=fopen('/var/www/log.txt','a');
		fprintf($log_fh,"%s %s\n",date('d/m/Y H:i:s '),$text);
		fclose($log_fh);
	}

	/* Redirect the customer from the Checkout page to the CityPay PayLink pages */
	public function redirectAction() {
		try {
			$session = $this->_getCheckout();
			$this->loadOrder($session->getLastRealOrderId());
			if ($this->_order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
				$this->_order->setState(
					Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
					$this->_getPendingPaymentStatus(),
					Mage::helper('paylink')->__('Customer was redirected to CityPay.')
					)->save();
			}
			if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
				$session->setCityPayQuoteId($session->getQuoteId());
				$session->setCityPaySuccessQuoteId($session->getLastSuccessQuoteId());
				$session->setCityPayRealOrderId($session->getLastRealOrderId());
				$session->getQuote()->setIsActive(false)->save();
				$session->clear();
			}
			$this->loadLayout();
			$this->renderLayout();
			return;
		} catch (Mage_Core_Exception $e) {
			$this->_getCheckout()->addError($e->getMessage());
		} catch(Exception $e) {
			Mage::logException($e);
		}
		$this->_redirect('checkout/cart');
	}

	/* Postback handler (not customer return) */
	public function responseAction() {
		try {
			$results = $this->_checkPostbackData();
			if ($results['authorised'] == true) {
				$this->_processSale($results);
			} else {
				// Transaction was not authorised. No action at this time as
				// there could be further attempts. Just pass back a generic 'OK' page
				$this->getResponse()->setBody('<html></html>');
			}
		} catch (Mage_Core_Exception $e) {
			$this->getResponse()->setBody(
				$this->getLayout()
				->createBlock($this->_failureBlockType)
				->setOrder($this->_order)
				->toHtml()
			);
		}
	}

	protected function _checkPostbackData() {
		// Check request type
		if (!$this->getRequest()->isPost()) {
			Mage::throwException('Wrong request type.');
		}
		// Validate postback request is from CityPay IP
		$helper = Mage::helper('core/http');
		if (method_exists($helper, 'getRemoteAddr')) {
			$remoteAddr = $helper->getRemoteAddr();
		} else {
			$request = $this->getRequest()->getServer();
			$remoteAddr = $request['REMOTE_ADDR'];
		}
		$ip_conf=Mage::getStoreConfig('payment/paylink_cc/server_ip');
		if (empty($ip_conf)) {
			$ip_conf="54.246.184.81, 54.246.184.93, 54.246.184.95";
		}
		if (strcasecmp($ip_conf,'Any')!=0) {
			$connection_allowed=false;
			$ip_list=explode(',',$ip_conf);
			foreach ($ip_list as $ip_check) {
				if (strcmp(trim($ip_check),$remoteAddr)==0) {
					$connection_allowed=true;
				}
			}
			if (!$connection_allowed) {
				Mage::throwException('IP '.$remoteAddr.' can\'t be validated as CityPay.');
			}
		}
		// Check response data - need the raw post data, can't use the processed post value as data is
		// in json format and not name/value pairs
		$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : file_get_contents("php://input");
		if (empty($HTTP_RAW_POST_DATA)) {
			Mage::throwException('Request doesn\'t contain postback data.');
		}
		$results = array_change_key_case(json_decode($HTTP_RAW_POST_DATA,true), CASE_LOWER);
		if (empty($results)) {
			Mage::throwException('Request doesn\'t contain valid JSON data.');
		}
		//$this->textlog(print_r($results,true));

		// Check order id
		if (empty($results['identifier']) || strlen($results['identifier']) > 50) {
			Mage::throwException('Missing or invalid order ID');
		}
		// Load order for further validation
		$this->loadOrder($results['identifier']);
		$conf=$this->_paymentInst->getCurrencyConfig($results['currency']);
		if (is_array($conf) && !empty($conf)) {
			$mid	= (int)$conf[0];
			$key	= $conf[1];
		} else {
			Mage::throwException('Order currency not configured');
		}
		$hash_src = $results['authcode'].$results['amount'].$results['errorcode'].$results['merchantid'].
			$results['transno'].$results['identifier'].$key;

		// Check both the sha1 and sha256 hash values to ensure that results have not
		// been tampered with
		$check=base64_encode(sha1($hash_src,true));
		if (strcmp($results['sha1'],$check)!=0) {
			// Hash check does not match. Data may of been tampered with.
			Mage::throwException('Callback data security check failed (sha1)');
		}
		$check=base64_encode(hash('sha256',$hash_src,true));
		if (strcmp($results['sha256'],$check)!=0) {
			// Hash check does not match. Data may of been tampered with.
			Mage::throwException('Callback data security check failed (sha256)');
		}
		// Postback data is valid
		return $results;
	}

	protected function _processSale($results) {
		// save transaction information
		$this->_order->getPayment()
			->setTransactionId($results['transno'])
			->setLastTransId($results['transno'])
			->setCcAvsStatus($results['avsresponse']);

		// Allow transno param to be seen by other parts of the system
		Mage::app()->getRequest()->setParam('transno',$results['transno']);

		$surcharge=floatval($results['surcharge']);
		$additional_data = $this->_order->getPayment()->getAdditionalData();
		$additional_data .= ($additional_data ? "<br/>\n" : '') . 'Card used: '. $results['maskedpan'];
		if ($surcharge>0) {
			// Surcharge was added. Include in the additional data for the transaction, using the
			// text version sent in the results.
			$additional_data .= "<br/>\nSurcharge: ". $results['surcharge'];
		}
		$this->_order->getPayment()->setAdditionalData($additional_data);
		if ($this->_order->canInvoice()) {
			$invoice = $this->_order->prepareInvoice();
			$invoice->register()->capture();
			Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder())
				->save();
		}
		$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('paylink')->__('The transaction has been authorised by CityPay.'));

		$this->_order->sendNewOrderEmail();
		$this->_order->setEmailSent(true);

		$this->_order->save();

		$this->getResponse()->setBody(
			$this->getLayout()
			->createBlock($this->_successBlockType)
			->setOrder($this->_order)
			->toHtml()
		);
	}

	protected function _processCancel($results) {
		// cancel order
		if ($this->_order->canCancel()) {
			$this->_order->cancel();
			$this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('paylink')->__('Payment was canceled'));
			$this->_order->save();
		}

		$this->getResponse()->setBody(
			$this->getLayout()
			->createBlock($this->_cancelBlockType)
			->setOrder($this->_order)
			->toHtml()
		);
	}

	/* Customer return actions (after postback phase has been completed) */
	public function successAction() {
		try {
			$session = $this->_getCheckout();
			$session->unsCityPayRealOrderId();
			$session->setQuoteId($session->getCityPayQuoteId(true));
			$session->setLastSuccessQuoteId($session->getCityPaySuccessQuoteId(true));
			$this->_redirect('checkout/onepage/success');
			return;
		} catch (Mage_Core_Exception $e) {
			$this->_getCheckout()->addError($e->getMessage());
		} catch(Exception $e) {
			Mage::logException($e);
		}
		$this->_redirect('checkout/cart');
	}

	public function cancelAction() {
		// set quote to active
		$session = $this->_getCheckout();
		if ($quoteId = $session->getCityPayQuoteId()) {
			$quote = Mage::getModel('sales/quote')->load($quoteId);
			if ($quote->getId()) {
				$quote->setIsActive(true)->save();
				$session->setQuoteId($quoteId);
			}
		}
		$session->addError(Mage::helper('paylink')->__('The order has been canceled.'));
		$this->_redirect('checkout/cart');
	}
}
