<?php
class Unipagos_Wallet_Block_Form_Standard extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
       //echo  Mage::helper('wallet/Wallet_api')->getPaymentGatewayUrl();
        $this->setTemplate('wallet/form/wallet_upgrade.phtml');
    }


   

}