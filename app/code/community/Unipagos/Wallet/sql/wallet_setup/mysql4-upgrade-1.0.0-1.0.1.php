<?php 

$installer = $this;
$installer->startSetup();

$creditcarderror_declined_content = 'Your payment was declined, please check and ensure that you have entered the correct address and credit card payment information and try again. If you continue to recieve an error please contact our customer support at (888) 502-7511';
//if you want one block for each store view, get the store collection


$block = Mage::getModel('cms/block');
$block->setTitle('creditcard declined error');
$block->setIdentifier('creditcarderror_declined');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent($creditcarderror_declined_content);
$block->save();

$creditcarderror_generic_content = 'An error occurred when submitting your payment, please check your payment information and submit again - if you continue to recieve an error please contact our customer support at (888) 502-7511';
//if you want one block for each store view, get the store collection


$block = Mage::getModel('cms/block');
$block->setTitle('creditcard generic error');
$block->setIdentifier('creditcarderror_generic');
$block->setStores(array(0));
$block->setIsActive(1);
$block->setContent($creditcarderror_generic_content);
$block->save();

$installer->endSetup();