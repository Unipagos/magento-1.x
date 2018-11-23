<?php

class Unipagos_Wallet_Model_Method_Standard extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'wallet_upgrade';
    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canUseCheckout          = true;
    protected $_formBlockType = 'wallet/form_standard';
    protected $_infoBlockType = 'wallet/info_standard';

    public function assignData($data)
    {
        $info = $this->getInfoInstance();
        if ($data->getMobileNumber())
        {
            $info->setMobileNumber($data->getMobileNumber());
        }
        return $this;
    }

    public function validate()
    {   $errorMsg='';
        parent::validate();
        $info = $this->getInfoInstance();

        if (!$info->getMobileNumber())
        {
            $errorCode = 'invalid_data';
            $errorMsg = $this->_getHelper()->__("Mobile Number is a required field.\n");
        }

        if ($errorMsg)
        {
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }


    /**
     * Return Order place redirect url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('wallet/standard/payment', array('_secure' => true));
    }

}