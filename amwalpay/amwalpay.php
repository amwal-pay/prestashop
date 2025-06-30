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
include 'library/AmwalPayPs.php';
if (!defined('_PS_VERSION_')) {
    exit;
}

class amwalpay extends PaymentModule {

    public function __construct() {
        $this->name = 'amwalpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = $this->l('AmwalPay Plugin Team');
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Amwal Pay');
        $this->description = $this->l('AmwalPay PrestaShop module for Oman and supports all card and wallet payment.');
        $this->confirmUninstall = $this->l('Are You Sure?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install() {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }
        include(dirname(__FILE__) . '/sql/install.php');
        $this->addAmwalpayOrderState($this->name);
        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            if (!parent::install() || !$this->registerHook('header') || !$this->registerHook('backOfficeHeader') || !$this->registerHook('payment') || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
                return false;
            }
        } else {
            if (!parent::install() || !$this->registerHook('displayHeader') || !$this->registerHook('header') || !$this->registerHook('backOfficeHeader') || !$this->registerHook('payment') || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
                return false;
            }
        }

        return true;
    }

    public function uninstall() {
        Configuration::deleteByName('amwalpay_enable_mode');
        Configuration::deleteByName('amwalpay_enable_debug');
        Configuration::deleteByName('amwalpay_live');
        Configuration::deleteByName('amwalpay_merchant_id');
        Configuration::deleteByName('amwalpay_terminal_id');
        Configuration::deleteByName('amwalpay_secret_key');
        Configuration::deleteByName('amwalpay_payment_view');
        Configuration::deleteByName('amwalpay_checkout_title');
        Configuration::deleteByName('PS_OS_AMWALPAY_PENDING_PAYMENT');
        include(dirname(__FILE__) . '/sql/uninstall.php');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent() {
        /**
         * If values have been submitted in the form, process.
         */
        $error = null;
        if (((bool) Tools::isSubmit('submitamwalpayModule')) == true) {
            $error = $this->saveAdminSetting();
        }

        $this->context->smarty->assign(array('error' => $error, 'amwalpay_dir' => $this->_path));
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm() {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitamwalpayModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getAdminValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getAdminForm()));
    }

    /**
     * Create the structure of admin form.
     */
    protected function getAdminForm() {
        $loggerFile = (version_compare(_PS_VERSION_, '1.7.0', '>')) ? _PS_ROOT_DIR_ . "/var/logs/amwalpay.log" : _PS_ROOT_DIR_ . "/log/amwalpay.log";
        $callbackURl = $this->context->link->getModuleLink($this->name, 'callback', array(), true);
        $logoPath = Configuration::get('amwalpay_logo');
        if (empty($logoPath)) {
            $Selected_image = "";
        } else {
            $Selected_image = "<b>Selected Logo: </b>" . $logoPath;
        }
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Module'),
                        'name' => 'amwalpay_enable_mode',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'mode_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'mode_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Payment Method Title'),
                        'name' => 'amwalpay_checkout_title',
                        'default' => 'Amwal Pay',
                    ),
                    array(
                        'type' => 'select',
                        'desc' => $this->l('Choose the environment you want to use for transactions.'),
                        'name' => 'amwalpay_live',
                        'label' => $this->l('Environment'),
                        'required' => true,
                        'default' => 'uat',
                        'options' => array(
                            'query' => array(
                                array('id' => 'prod', 'name' => 'Production'),
                                array('id' => 'uat', 'name' => 'UAT'),
                                array('id' => 'sit', 'name' => 'SIT'),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Please enter the merchant ID'),
                        'name' => 'amwalpay_merchant_id',
                        'label' => $this->l('Merchant id'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Please enter the Terminal ID'),
                        'name' => 'amwalpay_terminal_id',
                        'label' => $this->l('Terminal id'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'desc' => $this->l('Please enter the Secret key'),
                        'name' => 'amwalpay_secret_key',
                        'label' => $this->l('Secret key'),
                        'required' => true
                    ),
                     array(
                        'type' => 'select',
                        'desc' => $this->l('Select the preferred payment view.'),
                        'name' => 'amwalpay_payment_view',
                        'label' => $this->l('Payment Page View'),
                        'default' => '1',
                        'options' => array(
                            'query' => array(
                                array('id' => '1', 'name' => 'PopUp'),
                                array('id' => '2', 'name' => 'FullPage'),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Log'),
                        'name' => 'amwalpay_enable_debug',
                        'desc' => $this->l('You can find log file in the path ') . $loggerFile,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'log_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'log_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save Settings'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getAdminValues() {
        return array(
            'amwalpay_enable_mode' => Configuration::get('amwalpay_enable_mode', false),
            'amwalpay_enable_debug' => Configuration::get('amwalpay_enable_debug', false),
            'amwalpay_live' => Configuration::get('amwalpay_live', 'uat'),
            'amwalpay_merchant_id' => Configuration::get('amwalpay_merchant_id', false),
            'amwalpay_terminal_id' => Configuration::get('amwalpay_terminal_id', false),
            'amwalpay_secret_key' => Configuration::get('amwalpay_secret_key', false),
            'amwalpay_payment_view' => Configuration::get('amwalpay_payment_view', '1'),
            'amwalpay_checkout_title' => Configuration::get('amwalpay_checkout_title',  null),
        );
    }

    /**
     * Save form data.
     */
    protected function saveAdminSetting() {
       
       $form_values = $this->getAdminValues();
            foreach (array_keys($form_values) as $key) {
                $value = Tools::getValue($key);
                Configuration::updateValue($key, $value);
            }
            return null;
    }

    /**
     * This method is used to render the payment button in presta 1.7,
     * Take care if the button should be displayed or not.
     */
    public function hookPaymentOptions($params) {
        return $this->amwalpayPayment();
    }

    /**
     * This method is used to render the payment button in presta 1.6,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params) {
        return $this->amwalpayPayment();
    }

    public function amwalpayPayment() {
        if (!$this->active || !Configuration::get('amwalpay_enable_mode')) {
            return;
        }
        $this->logo = $this->_path . 'views/img/logo.webp';
        
        $paymentOption = null;
        $title = !empty(Configuration::get('amwalpay_checkout_title')) ? Configuration::get('amwalpay_checkout_title') : null;
        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($title)
                    ->setAction($this->context->link->getModuleLink($this->name, 'process', array(), true))
                    ->setLogo($this->logo);
            $paymentOption[] = $option;
        } else {
            $this->smarty->assign(array('title' => $title, 'logo' => $this->logo));
            $paymentOption = $this->display(__FILE__, 'amwalpay.tpl');
        }
        return $paymentOption;
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params) {
        if (!$this->active || !Configuration::get('amwalpay_enable_mode')) {
            return;
        }

        $state = null;
        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            $order = $params['order'];
            $state = $order->getCurrentState();
        } else {
            $order = $params['objOrder'];
            if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
                $state = $order->getCurrentOrderState()->id;
            }
        }
        
        if ($state) {
            $this->smarty->assign(
                    array(
                        'shop_name' => $this->context->shop->name,
                        'total' => Tools::displayPrice($order->getOrdersTotalPaid(), new Currency($order->id_currency), false),
                        'paid' => 'ok',
                        'order_reference' => $order->reference,
                    )
            );
        } else {
            $this->smarty->assign(
                    array(
                        'paid' => 'failed',
                    )
            );
        }

        if (version_compare(_PS_VERSION_, '1.7.0', '>')) {
            return $this->fetch('module:amwalpay/views/templates/hook/amwalpay_return.tpl');
        } else {
            return $this->display(__FILE__, 'amwalpay_return.tpl');
        }
    }

    /**
     * Add new order status for Amwalpay.
     */
    public function addAmwalpayOrderState($name) {
        // If the state does not exist, create it.
        if (!Configuration::get('PS_OS_AMWALPAY_PENDING_PAYMENT')) {
            // create new order state
            $orderState = new OrderState();
            $orderState->color = '#453ff9';
            $orderState->send_email = false;
            $orderState->module_name = $name;
            $orderState->unremovable = true;
            $orderState->logable = false;
            $orderState->name = array();
            $languages = Language::getLanguages(false);

            foreach ($languages as $language) {
                $orderState->name[$language['id_lang']] = $this->l('Amwalpay pending payment');
            }

            // Update object
            if ($orderState->add()) {
                $source = dirname(__FILE__) . '/views/img/logo.webp';
                $destination = dirname(__FILE__) . '/../../img/os/' . (int) $orderState->id . '.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('PS_OS_AMWALPAY_PENDING_PAYMENT', (int) $orderState->id);
        }
    }
}
