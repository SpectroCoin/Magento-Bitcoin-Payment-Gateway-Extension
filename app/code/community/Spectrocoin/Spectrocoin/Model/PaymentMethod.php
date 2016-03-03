<?php

class Spectrocoin_Spectrocoin_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'Spectrocoin';

    /**
    * Is this payment method a gateway (online auth/charge) ?
    */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture              = false;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;

    /**
     * Can refund online?
     */
    protected $_canRefund               = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    /**
     * Converts units to BTC
     */
    private function unitConversion($amount, $currencyFrom, $currencyTo)
    {
		$amount = urlencode($amount);
		$currencyFrom = urlencode($currencyFrom);
		$currencyTo = urlencode($currencyTo);
		$get = file_get_contents("https://www.google.com/finance/converter?a=$amount&from=$currencyFrom&to=$currencyTo");
		$get = explode("<span class=bld>",$get);
		$get = explode("</span>",$get[1]);  
		return round(preg_replace("/[^0-9\.]/", null, $get[0]), 2);
    }

    private function scError($message = '') {
        Mage::throwException(Mage::helper('payment')->__('Spectrocoin is not fully configured. Please select different payment. Message: ' . $message));
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        require_once Mage::getModuleDir('', 'Spectrocoin_Spectrocoin').DS.'lib'.DS.'SCMerchantClient'.DS.'SCMerchantClient.php';
        $privateKeyFilePath = Mage::getBaseDir('media') . '/spectrocoin/keys/' . Mage::getStoreConfig('payment/Spectrocoin/private_key_file');
        $receiveCurrency = Mage::getStoreConfig('payment/Spectrocoin/receive_currency');
        $merchantId = Mage::getStoreConfig('payment/Spectrocoin/merchant_id');
        $appId = Mage::getStoreConfig('payment/Spectrocoin/app_id');
        if (!file_exists($privateKeyFilePath) || !is_file($privateKeyFilePath)
            || !$merchantId || !$appId) {
            $this->scError('Check admin panel');
        }
        $order = $payment->getOrder();
        $currency = $order->getBaseCurrencyCode();

        if ($currency != $receiveCurrency) {
            $receiveAmount = $this->unitConversion($amount, $currency, $receiveCurrency);
        } else {
            $receiveAmount = $amount;
        }

        if (!$receiveAmount || $receiveAmount < 0) {
            $this->scError('Unit conversion failed');
        }
        $orderDescription = "Order #{$order->getId()}";
        $callbackUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/callback');
        $successUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/success?order=' . $order->getId());
        $cancelUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/cancel?order=' . $order->getId());

        $client = new SCMerchantClient($privateKeyFilePath, '', $merchantId, $appId);
        $orderRequest = new CreateOrderRequest($order->getId(), 0, $receiveAmount, $orderDescription, "en", $callbackUrl, $successUrl, $cancelUrl);
        $response = $client->createOrder($orderRequest);

        if ($response instanceof ApiError) {
            Mage::throwException(Mage::helper('payment')->__('Spectrocoin error. Error code: ' . $response->getCode() . '. Message: ' . $response->getMessage()));
        } else {
            if ($response->getReceiveCurrency() != $receiveCurrency) {
                $this->scError('Currencies does not match');
                exit;
            } else {
                $redirectUrl = $response->getRedirectUrl();
                $payment->setIsTransactionPending(true);
                Mage::getSingleton('customer/session')->setRedirectUrl($redirectUrl);
            }
        }
        return $this;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getSingleton('customer/session')->getRedirectUrl();
    }
}