<?php

class AmwalPayPs{
    public static function HttpRequest( $apiPath, $data = array() ) {
		if ( ! in_array( 'curl', get_loaded_extensions() ) ) {
			throw new Exception( 'Curl extension is not loaded on your server, please check with server admin. Then try again!' );
		}
       
		$agent=self::sanitizeVar('HTTP_USER_AGENT','SERVER');
		ini_set( 'precision', 14 );
		ini_set( 'serialize_precision', -1 );
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $apiPath );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $data ) );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt($curl, CURLOPT_USERAGENT, $agent);

		$response = curl_exec( $curl );

		if ( false === $response ) {
			throw new Exception( 'Curl error: ' . curl_error( $curl ) );
		}
		curl_close( $curl );

		return json_decode( $response, false );
	}
    public static function getUserTokens($customer,$loggerFile)
    {
        $token = '';
        if($customer){
            $userId = (int) $customer->id;
            $table = _DB_PREFIX_ . 'amwalpay_cards_token';
           $results = Db::getInstance()->getRow(
            'SELECT * FROM `' . pSQL($table) . '` WHERE `user_id` = ' . (int) $userId
        );
          
            if ($results) {
                $api_url = self::getApiUrl(Configuration::get('amwalpay_live'));
                $data['customerId'] = $results['token'];
                $data['merchantId'] = Configuration::get('amwalpay_merchant_id');
                $data['secureHashValue'] = self::generateStringForFilter($data, Configuration::get('amwalpay_secret_key'));
  
                $webhook_url = $api_url['webhook'];
                $sessionTokenRes = self::HttpRequest($webhook_url . 'Customer/GetSmartboxDirectCallSessionToken', $data);
                AmwalPayPs::addLogs(Configuration::get('amwalpay_enable_debug'), $loggerFile, 'In api Customer/GetSmartboxDirectCallSessionToken: ', print_r($sessionTokenRes, 1));
                if (isset($sessionTokenRes) && isset($sessionTokenRes->data) && isset($sessionTokenRes->data->sessionToken)) {
                    $token = $sessionTokenRes->data->sessionToken;
                }
            }
            }
        
        return $token;
    }
    public static function getApiUrl($env)
    {
        if ($env == "prod") {
            return ['smartbox' => 'https://checkout.amwalpg.com/js/SmartBox.js?v=1.1', 'webhook' => 'https://webhook.amwalpg.com/'];
        } else if ($env == "uat") {
            return ['smartbox' => 'https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1', 'webhook' => 'https://test.amwalpg.com:14443/'];
        } else if ($env == "sit") {
            return ['smartbox' => 'https://test.amwalpg.com:19443/js/SmartBox.js?v=1.1', 'webhook' => 'https://test.amwalpg.com:24443/'];
        }
    }
    public static function generateString(
        $amount,
        $currencyId,
        $merchantId,
        $merchantReference,
        $terminalId,
        $hmacKey,
        $trxDateTime,
        $sessionToken
    ) {

        $string = "Amount={$amount}&CurrencyId={$currencyId}&MerchantId={$merchantId}&MerchantReference={$merchantReference}&RequestDateTime={$trxDateTime}&SessionToken={$sessionToken}&TerminalId={$terminalId}";

        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }

    public static function encryptWithSHA256($input, $hexKey)
    {
        // Convert the hex key to binary
        $binaryKey = hex2bin($hexKey);
        // Calculate the SHA-256 hash using hash_hmac
        $hash = hash_hmac('sha256', $input, $binaryKey);
        return $hash;
    }
    public static function generateStringForFilter(
        $data,
        $hmacKey

    ) {
        // Convert data array to string key value with and sign
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '=' . ($value === "null" || $value === "undefined" ? '' : $value) . '&';
        }
        $string = rtrim($string, '&');
        // Generate SIGN
        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }
    /**
     * Filter the GLOBAL variables
     *
     * @param string $name The field name the need to be filter.
     * @param string $global value could be (GET, POST, REQUEST, COOKIE, SERVER).
     *
     * @return string|null
     */
    public static function sanitizeVar($name, $global = 'GET') {
        if (isset($GLOBALS["_$global"][$name])) {
            return htmlspecialchars($GLOBALS["_$global"][$name]);
        }
        return null;
    }

    public static function addLogs($debug,$file, $note, $data=false) {
        if (is_bool($data)) {
            ($debug === '1') ? error_log(PHP_EOL . date('d.m.Y h:i:s') . ' - ' . $note, 3, $file) : false;
        } else {
            ($debug === '1') ? error_log(PHP_EOL . date('d.m.Y h:i:s') . ' - ' . $note . ' -- ' . json_encode($data), 3, $file) : false;
        }
    }
}
