<?php

class Spectrocoin_Spectrocoin_CallbackController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var SCMerchantClient
     */
    private $client;
    /**
     * @var OrderCallback/null
     */
    private $callback;

    public function _construct()
    {
        require_once Mage::getModuleDir('', 'Spectrocoin_Spectrocoin').DS.'lib'.DS.'SCMerchantClient'.DS.'SCMerchantClient.php';
        $privateKeyFilePath = Mage::getBaseDir('media') . '/spectrocoin/keys/' . Mage::getStoreConfig('payment/Spectrocoin/private_key_file');
        $userid = Mage::getStoreConfig('payment/Spectrocoin/merchant_id');
        $appId = Mage::getStoreConfig('payment/Spectrocoin/app_id');
        $merchantApiUrl = 'https://spectrocoin.com/api/merchant/1';
        if (!file_exists($privateKeyFilePath) || !is_file($privateKeyFilePath)
                || !$userid || !$appId) {
            exit('No private key file found or wrong your merchant/app(ID)');
       }
        $this->client = new SCMerchantClient($merchantApiUrl, $userid, $appId);
        $this->callback = $this->client->parseCreateOrderCallback($_REQUEST);
    }

    // Route: spectrocoin/callback/callback
    public function callbackAction()
    {
        if (!$this->getRequest()->isPost()) {
            exit;
        }

        if ($this->client->validateCreateOrderCallback($this->callback)) {
            if (Mage::getStoreConfig('payment/Spectrocoin/receive_currency') != $this->callback->getReceiveCurrency()) {
                echo 'Receive currency does not match.';
                exit;
            }
            $orderId = $this->callback->getOrderId();
            $order = Mage::getModel('sales/order')->load($orderId);
            switch ($this->callback->getStatus()) {
                case OrderStatusEnum::$Test:
                    // Testing
                    break;
                case OrderStatusEnum::$New:
                    $order->setData('state', Mage_Sales_Model_Order::STATE_NEW);
                    $order->save();
                    break;
                case OrderStatusEnum::$Pending:
                    $order->setData('state', Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                    $order->save();
                    break;
                case OrderStatusEnum::$Expired:
                    $order->registerCancellation("Order expired")->save();
                    $order->save();
                    break;
                case OrderStatusEnum::$Failed:
                    $order->registerCancellation("Order failed")->save();
                    break;
                case OrderStatusEnum::$Paid:
                    $this->confirmOrder($order);
                    break;
                default:
                    echo 'Unknown order status: '.$this->callback->getStatus();
                    exit;
            }
        }
        echo '*ok*';
    }

    // Route: spectrocoin/callback/success
    public function successAction() {
        $this->_redirect('checkout/onepage/success', array('_secure' => false));
    }

    // Route: spectrocoin/callback/cancel
    public function cancelAction() {
        if (!isset($_GET['order'])) {
            $this->_redirectUrl(Mage::getBaseUrl());
        }
        $orderId = (int) $_GET['order'];
        $order = Mage::getModel('sales/order')->load($orderId);

        if(!$order->isPaymentReview() || $order->hasInvoices()) {
            $msg = "Your order could not be cancelled. Please contact customer support concerning Order ID $orderId.";
        } else {
            $msg = "Your order has been cancelled.";
            $order->registerCancellation("Order was cancelled during checkout.")->save();
        }

        Mage::getSingleton('core/session')->addError($msg);
        $this->_redirectUrl(Mage::getBaseUrl());
    }

    private function confirmOrder($order)
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($order->getId())
            ->setPreparedMessage("Paid with Spectrocoin order {$order->getId()}.")
            ->setShouldCloseParentTransaction(true)
            ->setIsTransactionClosed(0);
        $payment->registerCaptureNotification($order->getGrandTotal());
        $order->save();
    }

} 