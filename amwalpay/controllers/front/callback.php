<?php

/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2021 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
class AmwalpayCallbackModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {

        list($cartId, ) = explode('_', AmwalPayPs::sanitizeVar('merchantReference'));
        $orderId = Order::getOrderByCartId($cartId);
        $order = new Order((int) $orderId);

        if (empty($order)) {
            $msg = 'Ops. you are accessing wrong order.';
            $this->redirectWithError($msg);
        }

        $debug = Configuration::get('amwalpay_enable_debug');
        $loggerFile = (version_compare(_PS_VERSION_, '1.7.0', '>')) ? _PS_ROOT_DIR_ . "/var/logs/amwalpay.log" : _PS_ROOT_DIR_ . "/log/amwalpay.log";


        $isPaymentApproved = false;

        $integrityParameters = [
            "amount" => AmwalPayPs::sanitizeVar('amount'),
            "currencyId" => AmwalPayPs::sanitizeVar('currencyId'),
            "customerId" => AmwalPayPs::sanitizeVar('customerId'),
            "customerTokenId" => AmwalPayPs::sanitizeVar('customerTokenId'),
            "merchantId" => Configuration::get('amwalpay_merchant_id'),
            "merchantReference" => AmwalPayPs::sanitizeVar('merchantReference'),
            "responseCode" => AmwalPayPs::sanitizeVar('responseCode'),
            "terminalId" => Configuration::get('amwalpay_terminal_id'),
            "transactionId" => AmwalPayPs::sanitizeVar('transactionId'),
            "transactionTime" => AmwalPayPs::sanitizeVar('transactionTime')
        ];
        $this->saveCardToken(AmwalPayPs::sanitizeVar('customerId'),$debug, $loggerFile);
        $secureHashValue = AmwalPayPs::generateStringForFilter($integrityParameters, Configuration::get('amwalpay_secret_key'));
        $integrityParameters['secureHashValue'] = $secureHashValue;
        $integrityParameters['secureHashValueOld'] = AmwalPayPs::sanitizeVar('secureHashValue');

        AmwalPayPs::addLogs($debug, $loggerFile, 'Callback Response: ', print_r($integrityParameters, 1));
        if (AmwalPayPs::sanitizeVar('responseCode') === '00' && $secureHashValue == AmwalPayPs::sanitizeVar('secureHashValue')) {
            $isPaymentApproved = true;
        }

        if ($isPaymentApproved) {
            $note = 'Amwalpay : Payment Approved';
            $msg = 'In callback action, for order #' . $orderId . ' ' . $note;

            AmwalPayPs::addLogs($debug, $loggerFile, $msg);
            $this->context->cart->delete();
            $moduleId = $this->module->id;
            $address = new Address((int) ($order->id_address_delivery));
            $customer = new Customer((int) ($address->id_customer));
            $secureKey = $customer->secure_key;
            $this->updateOrderStatus($order, Configuration::get('PS_OS_PAYMENT'));
            // echo'<pre>';print_r('index.php?controller=order-confirmation&id_cart=' .'oidref-'.$cartId . '&id_module=' . $moduleId . '&id_order=' . $orderId . '&key=' . $secureKey);exit;
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $moduleId . '&id_order=' . $orderId . '&key=' . $secureKey);

        } else {
            $note = 'Amwalpay : Payment is not completed';
            $msg = 'In callback action, for order #' . $orderId . ' ' . $note;
            AmwalPayPs::addLogs($debug, $loggerFile, $msg);
            $this->updateOrderStatus($order, Configuration::get('PS_OS_ERROR'));
            $this->redirectWithError($msg);
        }

    }
    public function saveCardToken($customerTokenId, $debug, $loggerFile)
    {
        if (!$this->context->customer->isLogged() || empty($customerTokenId) || $customerTokenId === 'null') {
            return;
        }

        $userId = (int) $this->context->customer->id;
        $userEmail = $this->context->customer->email;
        AmwalPayPs::addLogs($debug, $loggerFile, 'Customer save Card Token for user -- ' . $userEmail, $customerTokenId);
        $table = _DB_PREFIX_ . 'amwalpay_cards_token';
        $existing = Db::getInstance()->getRow(
            'SELECT * FROM `' . pSQL($table) . '` WHERE `user_id` = ' . (int) $userId
        );

        if (!$existing) {
            Db::getInstance()->execute('INSERT INTO ' . $table . '
				(`user_id`, `token`) VALUES
				("' . $userId . '", "' . pSQL($customerTokenId) . '")');

        } else {
            Db::getInstance()->update($table, [
                'token' => pSQL($customerTokenId)
            ], 'user_id = ' . (int) $userId);
        }
    }
    public function redirectWithError($message)
    {
        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            $this->errors[] = $message;
            $this->redirectWithNotifications('index.php?controller=order');
        } else {
            array_push($this->errors, $message);
            $this->context->smarty->assign('errors', array($message));
            return $this->setTemplate('error.tpl');
        }
    }

    public function updateOrderStatus($order, $status)
    {
        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($status, (int) ($order->id));
        $history->save();
    }
}
