<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
class amwalpayPayment
{

    static function prepare($param, $return = false)
    {
        $log = Configuration::get('enable_log');
        $logger = null;
        $loggerFunc = null;

        $file = (version_compare(_PS_VERSION_, '1.7.0', '>')) ? _PS_ROOT_DIR_ . "/var/logs/amwalpay.log" : _PS_ROOT_DIR_ . "/log/amwalpay.log";

        if ($log == true) {
            $logger = new FileLogger(0);
            $logger->setFilename($file);
            $loggerFunc = 'logDebug';
        }
    }

}
