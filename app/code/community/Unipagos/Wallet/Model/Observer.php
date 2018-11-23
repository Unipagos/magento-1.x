<?php
class Unipagos_Wallet_Model_Observer
{
	public function beforeSaveOrder(Varien_Event_Observer $observer)
    {	
        $order = $observer->getEvent()->getOrder();
        $methodCode = $order->getPayment()->getMethodInstance()->getCode();
        if($methodCode=='wallet_upgrade' && !empty($order->getPayment()->getMobileNumber()))
        {    
        	$response = Mage::helper('wallet/Wallet_api')->request_access_code($order);
            if($response[ '_resultCode']=='000')
            {       
               //print_r()
                $response['order_increment_id'] = $order->getIncrementId();
                $response['base_grand_total']   = $order->getQuote()->getGrandTotal();
                $response['method_title'] ='Unipagos Wallet';
                $payment = $order->getPayment();
                unset($response['_resultType']);
                unset($response['_resultDesc']);
                $payment->setAdditionalInformation($response);
                if($response['phone']!=$order->getPayment()->getMobileNumber()){
                     $order->getPayment()->getMobileNumber();
                     $payment->setMobileNumber($response['phone']);

                }
                
    		}
    		elseif($response[ '_resultCode']=='100')
            {
    			$mesasge = 'OPERATION_FAILED:Please try again';
    			Mage::throwException($mesasge);
    		}
    		else
            {
    			$mesasge = $response[ '_resultType' ] . ': ' . $response[ '_resultDesc' ] . ':' . $response[ '_message' ];
    			Mage::throwException($mesasge);
    		}
        }
        return $this;
    }

}