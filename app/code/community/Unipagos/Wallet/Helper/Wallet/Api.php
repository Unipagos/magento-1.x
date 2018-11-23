<?php
class Unipagos_Wallet_Helper_Wallet_Api extends Mage_Core_Helper_Abstract
{
    const MODULE_NAME          = 'Unipagos_Wallet';

	const TEST_REGHOST         = 'https://mobile.dev.unipagos.com';
    const TEST_PAYHOST         = 'https://pay.dev.unipagos.com';
    const TEST_TOKENIZER       = 'https://tok-write.dev.unipagos.com/tkwriter/cors/1.0/tokenizer/card';

    const PRODUCTION_REGHOST   = 'https://mobile.unipagos.com';
    const PRODUCTION_PAYHOST   = 'https://pay.unipagos.com';
    const PRODUCTION_TOKENIZER = 'https://tok-write.unipagos.com/tkwriter/cors/1.0/tokenizer/card';

    const LOCAL_REGHOST        = 'https://localhost:4444';
    const LOCAL_PAYHOST        = 'https://localhost:7443';
    const LOCAL_TOKENIZER      = self::TEST_TOKENIZER;

    public function getApiConfigData()
    {
    	$data  =array();
        $moduleDir = Mage::getModuleDir('', self::MODULE_NAME);
    	$environment = $this->getApiEnvironment();

    	$data['reghost']      = ( 'production' === $environment ) ? self::PRODUCTION_REGHOST : self::TEST_REGHOST;
        $data['payhost']      = ( 'production' === $environment ) ? self::PRODUCTION_PAYHOST : self::TEST_PAYHOST;
        $data['tokenizer']    = ( 'production' === $environment ) ? self::PRODUCTION_TOKENIZER : self::TEST_TOKENIZER;
        $data['api_key']      = $this->getStoreConfig('payment/wallet_upgrade/api_key');
        $data['api_password'] = Mage::helper('core')->decrypt($this->getStoreConfig('payment/wallet_upgrade/api_password'));
        $data['key_file']     = $moduleDir . '/credentials/' . $environment . '/merchant.key';
        $data['key_pass']     =	Mage::helper('core')->decrypt( $this->getStoreConfig('payment/wallet_upgrade/key_password'));
        $data['cert_file']    = $moduleDir . '/credentials/' . $environment . '/merchant.cer';
        $data['ca_path_file'] = $moduleDir . '/credentials/' . $environment . '/unipagos.cer';
        $data['environment']  = $environment;

        if ( 'local' === $environment ) {
            $data['reghost']   = self::LOCAL_REGHOST;
            $data['payhost']   = self::LOCAL_PAYHOST;
            $data['tokenizer'] = self::LOCAL_TOKENIZER;
        }
        return $data;
    }


	public function getApiEnvironment()
	{	
		$environment = 'sandbox';
		$mode = $this->getStoreConfig('payment/wallet_upgrade/mode');
		if($mode==false){
			$environment = 'production';
		}
		return $environment;

	}

	public function getStoreConfig($path){
		return Mage::getStoreConfig($path, Mage::app()->getStore());
	}


	public function perform_get( $service, $json, $host = null ) {
        return $this->perform_request( FALSE, $service, $json, $host );
    }

    public function perform_post( $service, $json, $host = null ) {
        return $this->perform_request( TRUE, $service, $json, $host );
    }


    public function perform_request( $post, $service, $json, $host = null ) 
    {	
    	$apiConfig = $this->getApiConfigData();
        $this->debug_message('Request data on '.$service . ' below:');
        $this->debug_message($json );

        if ( !$host ) {
            $host = $apiConfig['reghost'];
        }
        else{
            $host = $apiConfig[$host];
        }

        $headers = array (
            'content-type: application/json',
            'accept: application/json',
            'authorization: Basic ' . base64_encode( 'merchant/' . $apiConfig['api_key'] . ':' . $apiConfig['api_password'] ),
            'accept-language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        );

        if ( $json[ 'sid' ] ) {
            array_push( $headers, 'up-sid: ' . $json[ 'sid' ] );
            unset( $json[ 'sid' ] );
        }

        $options = array(
            CURLOPT_SSLKEY          => $apiConfig['key_file'],
            CURLOPT_SSLKEYPASSWD    => $apiConfig['key_pass'],
            CURLOPT_SSLCERT         => $apiConfig['cert_file'],
            CURLOPT_CAINFO          => $apiConfig['ca_path_file'],
            CURLOPT_SSL_VERIFYPEER  => FALSE, // ( 'local' === $this->environment ) ? FALSE : TRUE,
            CURLOPT_SSL_VERIFYHOST  => FALSE, // ( 'local' === $this->environment ) ? FALSE : TRUE,
            CURLOPT_VERBOSE         => TRUE,
            CURLOPT_URL             => $host . $service . ( $post ? '' : '?' . http_build_query( $json ) ),
            CURLOPT_POST            => $post,
            CURLOPT_RETURNTRANSFER  => TRUE,
            CURLOPT_HTTPHEADER      => $headers,
        );

        if ( $post ) {
            $options[CURLOPT_POSTFIELDS] = json_encode( $json );
        }   

        $ch = curl_init();
        curl_setopt_array( $ch, $options );

        $response = curl_exec( $ch );
        $errno = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );

        if ( FALSE === $response ) {
            $errmsg = curl_error( $ch );
        }

        curl_close( $ch );

        if ( 200 === $errno ) {
            $this->debug_message('Response data on '.$service . ' below: ');
            $this->debug_message($response );

            $response = json_decode( $response, TRUE );

            if ( '000' !== $response[ '_resultCode' ] ) {

                $this->debug_message( $response[ '_resultType' ] . ' ' . $response[ '_resultDesc' ] . ' ' . $response[ '_message' ] );

            }
        } else {
            $this->debug_message($errno . ' ' .  $host . $service  . $errmsg);
            $response[ '_resultCode' ] = "100";
            $response[ '_resultType' ] = "OPERATION_FAILED";
            $response[ '_resultDesc' ] = "Operation Failed";
        }
        
        return $response;
    }


    public function request_access_code( $order, $method = 'ProcessPayment', $trx_type = 'Purchase', $order_total = null ) 
    {
        // set up request object
        $request = array(
            'ipAddress'   => $order->getRemoteIp(),
            'amount'      => number_format($order->getGrandTotal(), 2, '.', '').$order->getOrderCurrencyCode(),
            'firstName'   => $order->getCustomerFirstname(),
            'lastName'    => $order->getCustomerLastname(),
            'companyName' => substr( $order->getQuote()->getShippingAddress()->getCompany(), 0, 50 ),
            'street1'     => $order->getQuote()->getShippingAddress()->getStreet1(),
            'street2'     => $order->getQuote()->getShippingAddress()->getStreet2(),
            'city'        => $order->getQuote()->getShippingAddress()->getCity(),
            'state'       => $order->getQuote()->getShippingAddress()->getRegion(),
            'postalCode'  => $order->getQuote()->getShippingAddress()->getPostcode(),
            'country'     => strtoupper( $order->getQuote()->getShippingAddress()->getCountryId() ),
            'email'       => $order->getCustomerEmail(),
            'phone'       => preg_replace("/[^0-9]/", "", $order->getPayment()->getMobileNumber()),
        );

        if(!empty($order->getCustomerId())){
            $request['Options'][] = array( 'customerID' => $order->getCustomerId());
        }
        
        $result = Mage::helper('wallet/Wallet_api')->perform_post( '/v2/users', $request );
        return $result;

    }


    public function createCardToken($payment)
    {
        $month = str_pad($payment['cc_exp_month'], 2, "0", STR_PAD_LEFT);
        $request = array(
            'cardHolder'=>$payment['cc_owner'],
            'cardNumber'=>trim($payment['cc_number']),
            'expirationDate'=>array(
                'month'=>$month,
                'year'=>substr( $payment['cc_exp_year'], -2)
            )
        );
        
        $result = Mage::helper('wallet/Wallet_api')->perform_post( '', $request, 'tokenizer');
        return $result;
    }


    public function complete_payment( $order, $token, $cvn, $auth_code ) 
    {
        $payer_mdn = $order->getPayment()->getMobileNumber();
        // set up request object
        $request = array(
            'sid'          => '5330d08592aba0f4276236a5',
            'amount'       => $order->getGrandTotal(),
            'securityCode' => $cvn,
            'description'  => 'OrderId:'.$order->getIncrementId(),
            'payerMdn'     => preg_replace("/[^0-9]/", "", $payer_mdn),
            'isOwnCard'    => 'false',
            'otp'          => $auth_code,
            'currencyCode' => $order->getOrderCurrencyCode(),
        );

        $result = Mage::helper('wallet/Wallet_api')->perform_post(
            '/upams/1.0/e-commerce/payment-methods/card-tokens/' . $token . '/card-payments',
            $request,
            'payhost'
        );
        return $result;
    }

    public function send_receipt( $order, $transaction_id ) 
    {
        $payer_mdn = $order->getPayment()->getMobileNumber();
        // set up request object
        $request = array(
            'email'        => $order->getCustomerEmail(),
            'mdn'          => $payer_mdn,
        );

        $result = Mage::helper('wallet/Wallet_api')->perform_get( '/v2/receipts/' . $transaction_id, $request );
        return $result;
    }


    public function debug_message($message)
    {
    	if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }
    	Mage::log($message, null, 'unipagos_wallet.log');
    }

    /**
     * Lookup Response / Error messages based on codes
     * @param  string $response_message
     * @return string
     */
    public function response_message_lookup( $response_message ) {
        $messages = array(
            'A2000' => 'Transaction Approved',
            'A2008' => 'Honour With Identification',
            'A2010' => 'Approved For Partial Amount',
            'A2011' => 'Approved, VIP',
            'A2016' => 'Approved, Update Track 3',
            'D4401' => 'Refer to Issuer',
            'D4402' => 'Refer to Issuer, special',
            'D4403' => 'No Merchant',
            'D4404' => 'Pick Up Card',
            'D4405' => 'Do Not Honour',
            'D4406' => 'Error',
            'D4407' => 'Pick Up Card, Special',
            'D4409' => 'Request In Progress',
            'D4412' => 'Invalid Transaction',
            'D4413' => 'Invalid Amount',
            'D4414' => 'Invalid Card Number',
            'D4415' => 'No Issuer',
            'D4419' => 'Re-enter Last Transaction',
            'D4421' => 'No Action Taken',
            'D4422' => 'Suspected Malfunction',
            'D4423' => 'Unacceptable Transaction Fee',
            'D4425' => 'Unable to Locate Record On File',
            'D4430' => 'Format Error',
            'D4431' => 'Bank Not Supported By Switch',
            'D4433' => 'Expired Card, Capture',
            'D4434' => 'Suspected Fraud, Retain Card',
            'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
            'D4436' => 'Restricted Card, Retain Card',
            'D4437' => 'Contact Acquirer Security Department, Retain Card',
            'D4438' => 'PIN Tries Exceeded, Capture',
            'D4439' => 'No Credit Account',
            'D4440' => 'Function Not Supported',
            'D4441' => 'Lost Card',
            'D4442' => 'No Universal Account',
            'D4443' => 'Stolen Card',
            'D4444' => 'No Investment Account',
            'D4451' => 'Insufficient Funds',
            'D4452' => 'No Cheque Account',
            'D4453' => 'No Savings Account',
            'D4454' => 'Expired Card',
            'D4455' => 'Incorrect PIN',
            'D4456' => 'No Card Record',
            'D4457' => 'Function Not Permitted to Cardholder',
            'D4458' => 'Function Not Permitted to Terminal',
            'D4459' => 'Suspected Fraud',
            'D4460' => 'Acceptor Contact Acquirer',
            'D4461' => 'Exceeds Withdrawal Limit',
            'D4462' => 'Restricted Card',
            'D4463' => 'Security Violation',
            'D4464' => 'Original Amount Incorrect',
            'D4466' => 'Acceptor Contact Acquirer, Security',
            'D4467' => 'Capture Card',
            'D4475' => 'PIN Tries Exceeded',
            'D4482' => 'CVV Validation Error',
            'D4490' => 'Cut off In Progress',
            'D4491' => 'Card Issuer Unavailable',
            'D4492' => 'Unable To Route Transaction',
            'D4493' => 'Cannot Complete, Violation Of The Law',
            'D4494' => 'Duplicate Transaction',
            'D4496' => 'System Error',
            'D4497' => 'MasterPass Error',
            'D4498' => 'PayPal Create Transaction Error',
            'S5000' => 'System Error',
            'S5085' => 'Started 3dSecure',
            'S5086' => 'Routed 3dSecure',
            'S5087' => 'Completed 3dSecure',
            'S5088' => 'PayPal Transaction Created',
            'S5099' => 'Incomplete (Access Code in progress/incomplete)',
            'S5010' => 'Unknown error returned by gateway',
            'V6000' => 'Validation error',
            'V6001' => 'Invalid CustomerIP',
            'V6002' => 'Invalid DeviceID',
            'V6003' => 'Invalid Request PartnerID',
            'V6004' => 'Invalid Request Method',
            'V6010' => 'Invalid TransactionType, account not certified for eCome only MOTO or Recurring available',
            'V6011' => 'Invalid Payment TotalAmount',
            'V6012' => 'Invalid Payment InvoiceDescription',
            'V6013' => 'Invalid Payment InvoiceNumber',
            'V6014' => 'Invalid Payment InvoiceReference',
            'V6015' => 'Invalid Payment CurrencyCode',
            'V6016' => 'Payment Required',
            'V6017' => 'Payment CurrencyCode Required',
            'V6018' => 'Unknown Payment CurrencyCode',
            'V6021' => 'UP_CARDHOLDERNAME Required',
            'V6022' => 'UP_CARDNUMBER Required',
            'V6023' => 'UP_CARDCVN Required',
            'V6033' => 'Invalid Expiry Date',
            'V6034' => 'Invalid Issue Number',
            'V6035' => 'Invalid Valid From Date',
            'V6040' => 'Invalid TokenCustomerID',
            'V6041' => 'Customer Required',
            'V6042' => 'Customer FirstName Required',
            'V6043' => 'Customer LastName Required',
            'V6044' => 'Customer CountryCode Required',
            'V6045' => 'Customer Title Required',
            'V6046' => 'TokenCustomerID Required',
            'V6047' => 'RedirectURL Required',
            'V6051' => 'Invalid Customer FirstName',
            'V6052' => 'Invalid Customer LastName',
            'V6053' => 'Invalid Customer CountryCode',
            'V6058' => 'Invalid Customer Title',
            'V6059' => 'Invalid RedirectURL',
            'V6060' => 'Invalid TokenCustomerID',
            'V6061' => 'Invalid Customer Reference',
            'V6062' => 'Invalid Customer CompanyName',
            'V6063' => 'Invalid Customer JobDescription',
            'V6064' => 'Invalid Customer Street1',
            'V6065' => 'Invalid Customer Street2',
            'V6066' => 'Invalid Customer City',
            'V6067' => 'Invalid Customer State',
            'V6068' => 'Invalid Customer PostalCode',
            'V6069' => 'Invalid Customer Email',
            'V6070' => 'Invalid Customer Phone',
            'V6071' => 'Invalid Customer Mobile',
            'V6072' => 'Invalid Customer Comments',
            'V6073' => 'Invalid Customer Fax',
            'V6074' => 'Invalid Customer URL',
            'V6075' => 'Invalid ShippingAddress FirstName',
            'V6076' => 'Invalid ShippingAddress LastName',
            'V6077' => 'Invalid ShippingAddress Street1',
            'V6078' => 'Invalid ShippingAddress Street2',
            'V6079' => 'Invalid ShippingAddress City',
            'V6080' => 'Invalid ShippingAddress State',
            'V6081' => 'Invalid ShippingAddress PostalCode',
            'V6082' => 'Invalid ShippingAddress Email',
            'V6083' => 'Invalid ShippingAddress Phone',
            'V6084' => 'Invalid ShippingAddress Country',
            'V6085' => 'Invalid ShippingAddress ShippingMethod',
            'V6086' => 'Invalid ShippingAddress Fax ',
            'V6091' => 'Unknown Customer CountryCode',
            'V6092' => 'Unknown ShippingAddress CountryCode',
            'V6100' => 'Invalid UP_CARDNAME',
            'V6101' => 'Invalid UP_CARDEXPIRYMONTH',
            'V6102' => 'Invalid UP_CARDEXPIRYYEAR',
            'V6103' => 'Invalid UP_CARDSTARTMONTH',
            'V6104' => 'Invalid UP_CARDSTARTYEAR',
            'V6105' => 'Invalid UP_CARDISSUENUMBER',
            'V6106' => 'Invalid UP_CARDCVN',
            'V6107' => 'Invalid UP_ACCESSCODE',
            'V6108' => 'Invalid CustomerHostAddress',
            'V6109' => 'Invalid UserAgent',
            'V6110' => 'Invalid UP_CARDNUMBER',
            'V6111' => 'Unauthorised API Access, Account Not PCI Certified',
        );
        return isset( $messages[ $response_message ] ) ? $messages[ $response_message ] : $response_message;
    }



    public function getUnipagosErrorMessage($msgType){
        $content='';
        $block_data = Mage::getModel('cms/block')->load($msgType);
        if(!empty($block_data)){
            $content = Mage::helper('cms')->getPageTemplateProcessor()->filter($block_data->getContent());
        }
        return $content;
    }   

}
