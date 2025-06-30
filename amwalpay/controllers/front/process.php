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
class AmwalpayProcessModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        try {
            $loggerFile = (version_compare(_PS_VERSION_, '1.7.0', '>')) ? _PS_ROOT_DIR_ . "/var/logs/amwalpay.log" : _PS_ROOT_DIR_ . "/log/amwalpay.log";
            $authorized = false;
            /**
             * Verify if this payment module is authorized
             */
            foreach (Module::getPaymentModules() as $module) {
                if ($module['name'] == 'amwalpay') {
                    $authorized = true;
                    break;
                }
            }

            if (!$authorized) {
                return $this->displayError($this->l('This payment method is not available.'));
            }

            /**
             * Get current cart object from session
             */
            $theCart = $this->context->cart;

            /** @var CustomerCore $customer */
            $customer = new Customer($theCart->id_customer);

            $cartId = $theCart->id;
            $this->restoreCartItems($cartId);
            Context::getContext()->currency = new Currency((int) Context::getContext()->cart->id_currency);
            Context::getContext()->language = new Language((int) Context::getContext()->customer->id_lang);

            $paymentStatus = Configuration::get('PS_OS_AMWALPAY_PENDING_PAYMENT');

            $moduleName = $this->module->displayName;
            $currencyId = (int) Context::getContext()->currency->id;
            $amount = (float) $theCart->getOrderTotal(true, Cart::BOTH);

            $this->module->validateOrder($cartId, $paymentStatus, $amount, $moduleName, '', array(), $currencyId, false, Context::getContext()->customer->secure_key);
            $orderId = Order::getOrderByCartId($cartId);
            $orderObject = new Order((int) $orderId);
            
            $env = Configuration::get('amwalpay_live');
            $merchant_id = Configuration::get('amwalpay_merchant_id');
            $terminal_id = Configuration::get('amwalpay_terminal_id');
            $secret_key = Configuration::get('amwalpay_secret_key');
            $debugMode = Configuration::get('amwalpay_enable_debug');

            $locale = Context::getContext()->language->locale; // Get the current language locale
            $currentDate = new DateTime();
            $datetime = $currentDate->format('YmdHis');
            $refNumber = $cartId . '_' . $orderObject->reference;
            // if $locale content en make $locale = "en"
            if (strpos($locale, 'en') !== false) {
                $locale = "en";
            } else {
                $locale = "ar";
            }
            $sessionToken = AmwalPayPs::getUserTokens($customer,$loggerFile);
            // Generate secure hash
            $secret_key = AmwalPayPs::generateString(
                $amount,
                512,
                $merchant_id,
                $refNumber
                ,
                $terminal_id,
                $secret_key,
                $datetime,
                $sessionToken
            );

            $data = (object) [
                'AmountTrxn' => "$amount",
                'MerchantReference' => "$refNumber",
                'MID' => $merchant_id,
                'TID' => $terminal_id,
                'CurrencyId' => 512,
                'LanguageId' => $locale,
                'SecureHash' => $secret_key,
                'TrxDateTime' => $datetime,
                'PaymentViewType' => Configuration::get('amwalpay_payment_view') ?? 1,
                'SessionToken' => $sessionToken,
            ];
            AmwalPayPs::addLogs($debugMode, $loggerFile, 'Payment Request: ', print_r($data, 1));
            $jsonData = json_encode($data);
            $url = AmwalPayPs::getApiUrl($env);
            $this->context->smarty->assign(array(
                'url' => $url['smartbox'],
                'jsonData' => $jsonData,
                'callback_url' => $this->context->link->getModuleLink('amwalpay', 'callback', array(), true),
                'base_url' => Context::getContext()->link->getPageLink('order'),
            ));

            $this->setTemplate('module:amwalpay/views/templates/front/process.tpl');

        } catch (Exception $ex) {
            $this->displayError($ex->getMessage());
        }
    }

    public function restoreCartItems($cartId)
    {
        $oldCart = new Cart($cartId);
        $duplication = $oldCart->duplicate();
        $this->context->cookie->id_cart = $duplication['cart']->id;
        $context = $this->context;
        $context->cart = $duplication['cart'];
        CartRule::autoAddToCart($context);
        $this->context->cookie->write();
    }

    public function displayError($msg)
    {
        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            $this->errors[] = $msg;
            $this->redirectWithNotifications('index.php?controller=order');
        } else {
            array_push($this->errors, $msg);
            $this->context->smarty->assign('errors', array($msg));
            return $this->setTemplate('error.tpl');
        }
    }
}
