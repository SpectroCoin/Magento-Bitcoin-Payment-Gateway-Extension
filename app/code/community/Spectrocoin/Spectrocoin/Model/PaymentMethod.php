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
    public function authorize(Varien_Object $payment, $amount)
    {
        require_once Mage::getModuleDir('', 'Spectrocoin_Spectrocoin').DS.'lib'.DS.'SCMerchantClient'.DS.'SCMerchantClient.php';
        $privateKeyFilePath = Mage::getBaseDir('media') . '/spectrocoin/keys/' . Mage::getStoreConfig('payment/Spectrocoin/private_key_file');
        $merchantId = Mage::getStoreConfig('payment/Spectrocoin/merchant_id');
        $appId = Mage::getStoreConfig('payment/Spectrocoin/app_id');
        if (!file_exists($privateKeyFilePath) || !is_file($privateKeyFilePath)
            || !$merchantId || !$appId) {
            $this->scError('Check admin panel');
        }

        $order = $payment->getOrder();
        $currency = $order->getBaseCurrencyCode();
        $orderDescription = "Order #{$order->getId()}";

        $callbackUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/callback');
        $successUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/success?order=' . $order->getId());
        $cancelUrl = Mage::app()->getStore()->getUrl('spectrocoin/callback/cancel?order=' . $order->getId());
        $merchantApiUrl = 'https://spectrocoin.com/api/merchant/1';
        $client = new SCMerchantClient($merchantApiUrl, $merchantId, $appId);

        $privateKey = file_get_contents($privateKeyFilePath);
        $client->setPrivateMerchantKey($privateKey);
        $orderRequest = new CreateOrderRequest(null, "BTC", null, $currency, $amount, $orderDescription, "en", $callbackUrl, $successUrl, $cancelUrl);
        $response = $client->createOrder($orderRequest);
        if ($response instanceof ApiError) {
            Mage::throwException(Mage::helper('payment')->__('Spectrocoin error. Error code: ' . $response->getCode() . '. Message: ' . $response->getMessage()));
        }
        else {
                $redirectUrl = $response->getRedirectUrl();
                $payment->setIsTransactionPending(true);
                Mage::getSingleton('customer/session')->setRedirectUrl($redirectUrl);
            }
        return $this;
    }
}