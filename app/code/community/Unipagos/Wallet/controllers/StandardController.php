<?php

class Unipagos_Wallet_StandardController extends Mage_Core_Controller_Front_Action
{
    protected $unipagosOrderInfo;

    public function paymentAction()
    {   
        /*
         *check last order session and compare with Unipagos payment response 
         */

        $this->initUnipagosPaymentStep();
        $this->loadLayout();   
        $this->getLayout()->getBlock("head")->setTitle($this->__("Unipagos Payment"));
        $this->renderLayout(); 
    }

    public function paymentpostAction()
    {   
        $this->initUnipagosPaymentStep();
        if ($this->getRequest()->isPost()) {
            $paymentData = $this->getRequest()->getPost('payment');
            if(!empty($paymentData['cc_owner']) && !empty($paymentData['cc_type']) && !empty($paymentData['cc_number']) && !empty($paymentData['cc_exp_month']) && !empty($paymentData['cc_exp_year']) && !empty($paymentData['cc_cid']) && !empty($paymentData['cc_otp']) )
            {
                
                $unipagosOrderInfo = $this->getUnipagosOrderInfo();
                $unipagosInfo = $unipagosOrderInfo->getPayment()->getAdditionalInformation();
                $response = Mage::helper('wallet/Wallet_api')->createCardToken($paymentData);

                if($response[ '_resultCode']=='000'){

                    $token = $response[ 'token'];
                    $unipagosInfo['payment_token'] = $token;
                    $cvn       = trim($paymentData['cc_cid']);
                    $auth_code = trim($paymentData['cc_otp']);
                    $environment = Mage::helper('wallet/Wallet_api')->getApiEnvironment();
                    if($environment=='sandbox' && isset($unipagosInfo['otp']) && !empty($unipagosInfo['otp']) && $auth_code==$unipagosInfo['otp'] ) {
                        $auth_code=$unipagosInfo['otp'];
                    }

                    if(!empty($token)){
                        $response_payment = Mage::helper('wallet/Wallet_api')->complete_payment( $unipagosOrderInfo, $token, $cvn, $auth_code );
                        if($response_payment[ '_resultCode']=='000'){
                            $transactionId = $response_payment[ 'receipt' ][ 'id' ];
                            $unipagosInfo['payment_receipt'] = $response_payment;
                            try 
                            {   
                                $unipagosOrderInfo->getPayment()->setAdditionalInformation($unipagosInfo);
                                Mage::helper('wallet/Wallet_api')->send_receipt($unipagosOrderInfo, $transactionId);
                                $this->updatePaymentStatusCompleted($unipagosOrderInfo, $transactionId);
                                $unipagosOrderInfo->sendNewOrderEmail()->save();
                                $this->_redirect('checkout/onepage/success');
                                return;
                            } catch (\Exception $e) {
                                Mage::log($e->getMessage());
                                $unipagosOrderInfo->addStatusHistoryComment($e->getMessage(),true);
                                $unipagosOrderInfo->save();
                            }
                        }
                        else {
                            if(!empty($response_payment[ '_message' ])){
                                $transactionMesage = "Unipagos Transaction Failed: ". $response_payment[ '_message' ];
                                $unipagosOrderInfo->addStatusHistoryComment($transactionMesage,true);
                                $unipagosOrderInfo->save();
                            }
                            $mesage=$this->getUnipagosErrorMessage('creditcarderror_declined');
                            Mage::getSingleton('core/session')->addError($mesage);

                        }

                    }

                }
                else{
                    if(!empty($response[ '_message' ])){
                        $transactionMesage = "Unipagos Transaction Failed: ". $response[ '_message' ];
                        $unipagosOrderInfo->addStatusHistoryComment($transactionMesage,true);
                        $unipagosOrderInfo->save();
                    }
                    $mesage=$this->getUnipagosErrorMessage('creditcarderror_declined');
                    Mage::getSingleton('core/session')->addError($mesage);
                }
            }
            else
            {
                $mesage=$this->getUnipagosErrorMessage('creditcarderror_generic');
                Mage::getSingleton('core/session')->addError($mesage);
            }
            $this->_redirect('*/*/payment');
            return;
        }
        $this->_redirect('*/*/payment');
        return;
       
    }

    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function setUnipagosOrderInfo($info)
    {
        $this->unipagosOrderInfo =  $info;
    }

    public function getUnipagosOrderInfo()
    {
        return $this->unipagosOrderInfo;
    }

    /*
     *check last order session and compare with Unipagos payment response 
     */
    public function initUnipagosPaymentStep()
    {
        $session = $this->getOnepage()->getCheckout();
        if (!$session->getLastSuccessQuoteId()) {
            $this->_redirect('/');
            return;
        }

        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        $last_real_order_id = $session->getLastRealOrderId();
        if (!$lastQuoteId || (!$lastOrderId) || (!$last_real_order_id)) {
            $this->_redirect('/');
            return;
        }

        $order = Mage::getModel('sales/order')->load($lastOrderId);
        $orderIncrementId = $order->getIncrementId();
        $unipagosPaymentInfo = $order->getPayment()->getAdditionalInformation();
        $unipagosPaymentOrderId = $unipagosPaymentInfo['order_increment_id'];
        if(($last_real_order_id!=$orderIncrementId) ||  ($order->getStatus()!='pending') || ($order->getPayment()->getMethod()!="wallet_upgrade") || ($last_real_order_id!=$unipagosPaymentOrderId))
        {
            $this->_redirect('/');
            return;
        }
        $this->setUnipagosOrderInfo($order);
    }

    public function updatePaymentStatusCompleted($order, $transactionId)
    {
        $payment = $order->getPayment();
        $comment =  Mage::helper('wallet')->__('The transaction completed successfully.');
        $payment->setTransactionId($transactionId)
            ->setPreparedMessage($comment)
            ->setCurrencyCode($payment->getOrder()->getBaseCurrencyCode())
            ->setIsTransactionApproved(true)
            ->setIsTransactionClosed(true)
            ->registerCaptureNotification($order->getTotalDue(), true)
            ->save();
        $order->save();
    }

    public function updatePaymentStatusRejected($order, $message)
    {  
        $payment = $order->getPayment();
        $comment = Mage::helper('wallet')->__($message);
        $payment->setPreparedMessage($comment)->save();
        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $comment)->save();
    }


    public function getUnipagosErrorMessage($msgType){
        return Mage::helper('wallet/Wallet_api')->getUnipagosErrorMessage($msgType);
    }
}
?>