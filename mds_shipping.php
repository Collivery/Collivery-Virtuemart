<?php

/**
 * Shipment plugin for general, rules-based shipments, like regular postal services with complex shipping cost structures
 *
 * @version 2.0.0
 * @package MDS Collivery Shipping Module
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2014 MDS Collivery - All rights reserved.
 * @license GNU/GPL version 3 or later: http://www.gnu.org/copyleft/gpl.html
 * @author MDS Collivery
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use VirtueMartModelConfig as VmConfig;

if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS.'/vmpsplugin.php';
}

// Only declare the class once...
if (class_exists('plgVmShipmentMds_Shipping')) {
    return;
}

/**
 * MDS Shipping
 */
class plgVmShipmentMds_Shipping extends vmPSPlugin
{

    // instance of class
    public static $_this = false;
    protected $cache;
    protected $db;
    protected $towns;
    protected $services;
    protected $location_types;
    protected $extension_id;
    protected $app_name;
    protected $app_info;
    protected $collivery;
    protected $password;
    protected $username;
    protected $converter;
    protected $risk_cover;

    /**
     * Constructor
     *
     * @param JDispatcher $subject The object to observe
     * @param array       $config An array that holds the plugin configuration
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = true;
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        
        // Load the database and execute our sql file
        $this->db = Factory::getDbo();

        // Get information of our plugin so we can pass it on to MDS Collivery for Logs
        $sel_query = "SELECT * FROM `#__extensions` WHERE type = 'plugin' AND element = 'mds_shipping' AND folder = 'vmshipment';";
        $this->db->setQuery($sel_query);
        $extension = $this->db->loadObject();
        $this->extension_id = $extension->extension_id;
        $this->app_name = $extension->extension_id;
        $this->app_info = json_decode($extension->manifest_cache);

        // Get our config
        $sel_query = 'SELECT * FROM `#__mds_collivery_config` WHERE id=1;';
        $this->db->setQuery($sel_query);
        $config = $this->db->loadObject();
        $this->password = $config->password;
        $this->username = $config->username;
        $this->risk_cover = $config->risk_cover;

        $version = new \Joomla\CMS\Version();
        require_once preg_replace('|com_installer|i', '', JPATH_COMPONENT_ADMINISTRATOR).'/helpers/config.php';

        $config = [
            'app_name' => $this->app_info->name, // Application Name
            'app_version' => $this->app_info->version, // Application Version
            'app_host' => 'Joomla: '.$version->getShortVersion().' - VirtueMart: '.$this->getVirtueMartVersion(), // Framework/CMS name and version
            'app_url' => Uri::base(), // URL your site is hosted on
            'user_email' => $this->username,
            'user_password' => $this->password
        ];


        // Use the MDS API Files
        require_once 'Mds/Cache.php';

        Factory::getApplication()->enqueueMessage('Cache file loaded', 'warning');

        require_once 'Mds/Collivery.php';
        Factory::getApplication()->enqueueMessage('Collivery file loaded', 'warning');

        $this->collivery = new Mds\Collivery($config);
        Factory::getApplication()->enqueueMessage($this->collivery, 'warning');


        // Get some information from the API

        // Class for converting lengths and weights
        require_once 'UnitConvertor.php';
        $this->converter = new UnitConvertor();
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Shipment MDS Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return string SQL Fileds
     */
    public function getTableSQLFields()
    {
        $SQLfields = [
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_number' => 'char(32)',
            'virtuemart_shipmentmethod_id' => 'int(11) UNSIGNED',
            'shipment_name' => 'varchar(5000)',
            'rule_name' => 'varchar(500)',
            'order_weight' => 'decimal(10,6)',
            'order_products' => 'int(11)',
            'shipment_cost' => 'decimal(10,2)',
        ];

        return $SQLfields;
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the shipment-specific data.
     *
     * @param integer $order_number The order Number
     *
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
    }

    /**
     * This event is fired after the order has been stored; it gets the shipment method-
     * specific data.
     *
     * @param int    $order_id The order_id being processed
     * @param object $cart the cart
     * @param array  $order The actual order saved in the DB
     *
     * @return mixed Null when this method was not selected, otherwise true
     */
    public function plgVmConfirmedOrder(VirtueMartCart $cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->shipment_element)) {
            return false;
        }
        $values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $values['order_number'] = $order['details']['BT']->order_number;
        $values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
        $values['shipment_name'] = $this->renderPluginName($method);
        $values['rule_name'] = $method->rule_name;
        $values['order_weight'] = $this->getOrderWeight($cart, $method->weight_unit);
        $values['order_products'] = $this->getOrderProducts($cart);
        $values['shipment_weight_unit'] = $method->weight_unit;
        $values['shipment_cost'] = $method->cost;
        $values['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($values);

        return true;
    }

    /**
     * @param VirtueMartCart $cart
     * @param unknown        $method
     * @param unknown        $cart_prices
     *
     * @return int
     */
    public function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        // If bill to address is not used then lets used ship to
        if (empty($cart->BT) && empty($cart->ST)) {
            return 0;
        } elseif (empty($cart->ST)) {
            $address = $cart->BT;
        } else {
            $address = $cart->ST;
        }

        // Get town name from the database
        $shippingModel = VmModel::getModel('mds');
        $state_name = $shippingModel->getState($address['virtuemart_state_id']);

        if ($method->slug == '') {
            $mds_service = array_search($method->shipment_name, $this->collivery->getServices());
        } else {
            $mds_service = $method->slug;
        }

        $to_town_id = array_search($state_name, $this->collivery->getTowns());
        $to_town_type = $address['mds_location_type'];
        $email = $this->password;

        if ($to_town_id != '') {
            $password = $method->password;

            if ($method->risk_cover == '0') {
                $risk_cover = 0;
            } else {
                $risk_cover = 1;
            }

            $collivery = $this->collivery;
            $from_town_id = $method->town_id;
            $from_location_type = $method->location_type;

            $address_data = [
                'contact' => $address['first_name'],
                'company_name' => $address['company'],
                'email_address' => $email,
                'cellphone_number' => $address['phone_1'],
                'phone_number' => $address['phone_1'],
                'address' => $address['address_1'],
                'town_id' => $from_town_id,
                'location_type' => $from_location_type
            ];

            $contacts = $collivery->addContact($address_data);

            $collivery_data = [
                'contact_from' => $contacts,
                'contact_to' => $contacts,
                'collivery_from' => $from_town_id,
                'collivery_to' => $to_town_id,
                'from_town_id' => $from_town_id,
                'to_town_id' => $to_town_id,
                'to_town_type' => $to_town_type,
                'risk_cover' => $risk_cover,
                'services' => $mds_service,
                'collivery_type' => '2',
                'num_packages' => '1',
                'service' => '3'
            ];

            $quote = $collivery->getPrice($collivery_data);

            return $quote['total'];
        }
    }

    /**
     * This method is fired when showing the order details in the backend.
     * It displays the shipment-specific data.
     *
     * @param int     $virtuemart_order_id The order ID
     * @param int     $virtuemart_shipmentmethod_id The shipment method ID
     * @param unknown $shipment_name
     * @param         $return_array
     *
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderBEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name)
    {
        $this->onShowOrderBE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
    }

    /**
     * Returns HTML code for a plugin.
     *
     * @param unknown $plugin
     * @param         $name
     * @param         $id
     * @param         $control_name
     *
     * @return string
     */
    public function plgVmOnSelectedCalculatePrice(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Check if the plugin is available.
     * Set the list $psType to 'vmshipment'
     *
     * @param VirtueMartCart $cart
     * @param unknown        $method
     * @param array          $cart_prices
     * @param int &          $shipCounter
     * @param string         $type
     *
     * @return bool
     */
    public function plgVmOnCheckAutomaticSelected(VirtueMartCart $cart, array $cart_prices, &$shipCounter, $type)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $shipCounter, $type);
    }

    /**
     * Get a list of all the available shipping methods.
     *
     * @param VirtueMartCart $cart
     * @param                $method
     * @param                $cart_prices
     *
     * @return bool
     */
    public function plgVmOnGetAutomaticSelected($cart, &$method, $cart_prices)
    {
        return $this->onGetAutomaticSelected($cart, $method, $cart_prices);
    }

    /**
     * Renders the plugin name
     *
     * @param $method
     *
     * @return string
     */
    protected function renderPluginName($method)
    {
        $html = '<span class="vmpayment">'.JText::_('VMPAYMENT_VIRTUEMART_PAYMENT_METHOD').'</span><br />';
        $html .= '<strong>'.$method->payment_name.'</strong><br />';
        $html .= '<span class="vmpayment_terms">'.$method->payment_terms.'</span>';

        return $html;
    }

    /**
     * This event is fired when showing the order details in the frontend.
     * It displays the payment-specific data.
     *
     * @param int $order_number The order Number
     *
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        return $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * Convert order weight to correct unit
     *
     * @param $cart
     * @param $unit
     *
     * @return float
     */
    protected function getOrderWeight($cart, $unit)
    {
        $weight = 0;
        foreach ($cart->products as $product) {
            $weight += $product->product_weight;
        }

        return $this->converter->convertWeight($weight, $unit);
    }

    /**
     * Get order products
     *
     * @param $cart
     *
     * @return int
     */
    protected function getOrderProducts($cart)
    {
        $product_count = 0;
        foreach ($cart->products as $product) {
            $product_count += $product->quantity;
        }

        return $product_count;
    }

    protected function getVirtueMartVersion()
    {
        if (class_exists('VmConfig')) {
            if (method_exists('VmConfig', 'getInstalledVersion')) {
                return VmConfig::getInstalledVersion();
            } elseif (defined('VM_VERSION')) {
                return VM_VERSION;
            }
        }
        return 'Unknown';
    }

    /**
     * Declare parameters from the XML file
     *
     * @return array
     */
    protected function getVarsToPush():array
    {
        return [
            'username' => ['default_value', 'char'],
            'password' => ['default_value', 'char'],
            'risk_cover' => ['0', 'int']
        ];
    }
}


