<?php
class Unipagos_Wallet_Block_Info_Standard extends Mage_Payment_Block_Info
{
    //for displaying mobile number in backend
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('wallet/info/wallet_upgrade.phtml');
    }

    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation)
        {
            return $this->_paymentSpecificInformation;
        }

        $data = array();
        if ($this->getInfo()->getMobileNumber())
        {
            $data[Mage::helper('payment')->__('Mobile Number')] = $this->getInfo()->getMobileNumber();
        }


        $transport = parent::_prepareSpecificInformation($transport);

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}