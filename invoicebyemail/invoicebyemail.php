<?php
/*
 * -----------------------------------------------------------------------------
 * invoicebyemail
 * Automatically sends the invoice in PDF format by email
 * Customization : You can choose the status and email template
 *
 * Copyright (C) 2012 http://www.berenger-vidal.com
 * You can freely use this script in your Web pages.
 * You may adapt this script for your own needs, provided these opening credit
 * lines are kept intact.
 *
 * This module script is distributed free
 * For updates, please visit: http://www.berenger-vidal.com/
 * https://github.com/Berenger/invoiceByEmail
 *
 * Questions & comments please send to berenger.vidal (at) gmail.com
 *  -----------------------------------------------------------------------------
 */
if (!defined('_PS_VERSION_'))
  exit;

class invoicebyemail extends Module
{
    public function __construct()
    {
        $this->name = 'invoicebyemail';
        $this->tab = 'emailing';
        $this->version = '1.0.1';
        $this->author = 'Bérenger VIDAL';
        $this->need_instance = 0;

	    $this->_directory = dirname(__FILE__);
        parent::__construct();

        $this->displayName = $this->l('Facture par e-mail Beta');
        $this->description = $this->l('Envoie automatiquement la facture au format pdf par e-mail.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
   }

    // this also works, and is more future-proof
    public function install()
    {
        if (!parent::install()
              || !Configuration::updateValue('invoicebyemail_Template', 'payment')
              || !Configuration::updateValue('invoicebyemail_Statut', 2)
              || !Configuration::updateValue('invoicebyemail_Entete', 'Votre facture')
              || !$this->registerHook('actionOrderStatusUpdate')
        )
            return false;
        else
            return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('invoicebyemail_Template');
        Configuration::deleteByName('invoicebyemail_Statut');
        Configuration::deleteByName('invoicebyemail_Entete');
		$this->unregisterHook('actionOrderStatusUpdate');
        return parent::uninstall();
    }

    /**
     * Envoie d'un mail contenant la facture
     */
    public function hookActionOrderStatusUpdate($param)
    {
        $order = new Order($param['id_order']);

        if($param['newOrderStatus']->id == Configuration::get('invoicebyemail_Statut')) {
            $orderInvoice = new OrderInvoice($order->invoice_number);
            $customer =  $order->getCustomer();
            $pdf = new PDF($orderInvoice, PDF::TEMPLATE_INVOICE, Context::getContext()->smarty);
            $mail=new Mail();

            $pj = array();
            $pj['content'] = $pdf->render(false);
            $pj['name'] =  Configuration::get('PS_INVOICE_PREFIX').sprintf('%06d', $order->invoice_number).'.pdf';
            $pj['mime'] = 'application/pdf';

            $var = array();

            $var['{firstname}'] = $customer->firstname;
            $var['{lastname}'] = $customer->lastname;
            $var['{order_name}'] = $order->reference;
            $entete =  Configuration::get('invoicebyemail_Entete');

            $mail->Send($order->id_lang, Configuration::get('invoicebyemail_Template'), $entete, $var, $customer->email,$customer->firstname, null, null, $pj);
        }
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {

            $my_module_name = strval(Tools::getValue('invoicebyemail_Statut'));
            if (!$my_module_name  || empty($my_module_name) || !Validate::isGenericName($my_module_name))
                $output .= $this->displayError( $this->l('Erreur : Statut invalide') );
            else
            {
                Configuration::updateValue('invoicebyemail_Statut', $my_module_name);
            }

            $my_module_name = strval(Tools::getValue('invoicebyemail_Template'));
            if (!$my_module_name  || empty($my_module_name) || !Validate::isGenericName($my_module_name))
                $output .= $this->displayError( $this->l('Erreur : Template invalide') );
            else
            {
                Configuration::updateValue('invoicebyemail_Template', $my_module_name);
            }

            $my_module_name = strval(Tools::getValue('invoicebyemail_Entete'));
            if (!$my_module_name  || empty($my_module_name) || !Validate::isGenericName($my_module_name))
                $output .= $this->displayError( $this->l('Erreur : Template invalide') );
            else
            {
                Configuration::updateValue('invoicebyemail_Entete', $my_module_name);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }


        }
        return $output.$this->displayForm();
    }

    public function displayForm()
    {

        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form = array();

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),

            'input' => array(
                 array(
                    'type'       => 'select',
                    'required'   => true,
                    'label'      => $this->l('Statut de la commande:'),
                    'name'       => 'invoicebyemail_Statut',
                    'options'    => array(
                        'query'    => OrderState::getOrderStates($this->context->language->id),
                        'id'       => 'id_order_state',
                        'name'     => 'name',
                    ),
                ),
                array(
                    'type'      => 'select',
                    'required'  => true,
                    'label'     => $this->l('Template de l\'email:'),
                    'name'      => 'invoicebyemail_Template',
                    'options'   => array(
                        'query' => $this->getTemplates($this->context->language->iso_code),
                        'id'    => 'id',
                        'name' => 'name',
                    ),
                ),
                array(
                    'type'      => 'text',
                    'required'  => true,
                    'label'     => $this->l('Entête de l\'email:'),
                    'name'      => 'invoicebyemail_Entete',
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, t    oken and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['invoicebyemail_Statut'] = Configuration::get('invoicebyemail_Statut');
        $helper->fields_value['invoicebyemail_Template'] = Configuration::get('invoicebyemail_Template');
        $helper->fields_value['invoicebyemail_Entete'] = Configuration::get('invoicebyemail_Entete');
        return $helper->generateForm($fields_form);
    }

    protected function getTemplates($iso_code) {
        $array = array();
        if (!file_exists(_PS_ADMIN_DIR_ . '/../mails/' . $iso_code))
            return false;
        $templates = scandir(_PS_ADMIN_DIR_ . '/../mails/' . $iso_code);
        foreach ($templates as $key => $template)
            if (!strncmp(strrev($template), 'lmth.', 5))
                $array[] = array(
                    'id' => substr($template, 0, -5),
                    'name' => substr($template, 0, -5)
                );

        return $array;
    }
}