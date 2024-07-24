<?php

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the view framework
if (!class_exists('VmViewAdmin')) {
    require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmviewadmin.php';
}

/**
 * HTML View class
 */
class VirtuemartViewMds extends VmViewAdmin
{

    var $db;
    var $towns;
    var $services;
    var $location_types;
    var $extension_id;
    var $app_name;
    var $app_info;
    var $collivery;
    var $converter;
    var $adresses;
    var $default_address_id;
    var $default_contacts;
    var $mds_services;
    var $risk_cover;
    var $username;
    var $password;
    var $vm_version;

    public function __construct()
    {
        parent::__construct();
        // Insert our standard javascript and css into the head
        $document = JFactory::getDocument();

        // Jquery validation
        $document->addStyleSheet(JURI::base() . "components/com_virtuemart/views/mds/tmpl/css/screen.css");
        $document->addScript(JURI::base() . "components/com_virtuemart/views/mds/tmpl/js/jquery.validate.min.js");

        // Date Time Picker
        $document->addStyleSheet(JURI::base() . "components/com_virtuemart/views/mds/tmpl/datetimepicker-master/jquery.datetimepicker.css");
        $document->addScript(JURI::base() . "components/com_virtuemart/views/mds/tmpl/datetimepicker-master/jquery.datetimepicker.js");

        // Our custom js and css
        $document->addScript(JURI::base() . "components/com_virtuemart/views/mds/tmpl/js/mds_collivery.js");
        $document->addStyleSheet(JURI::base() . "components/com_virtuemart/views/mds/tmpl/css/mds_collivery.css");

        // Our base url
        $document->addScriptDeclaration('base_url = "' . JURI::base() . '";');

        // Load the database and execute our sql file
        $this->db = JFactory::getDBO();

        // Get information of our plugin so we can pass it on to MDS Collivery for Logs
        $sel_query = "SELECT * FROM `#__extensions` where type = 'plugin' and element = 'mds_shipping' and folder = 'vmshipment';";
        $this->db->setQuery($sel_query);
        $this->db->query();
        $this->extension_id = $this->db->loadObjectList()[0]->extension_id;
        $this->app_name = $this->db->loadObjectList()[0]->extension_id;
        $this->app_info = json_decode($this->db->loadObjectList()[0]->manifest_cache);

        // Get our login information
        $config_query = "SELECT * FROM `#__mds_collivery_config` where id = 1;";
        $this->db->setQuery($config_query);
        $this->db->query();
        $this->password = $this->db->loadObjectList()[0]->password;
        $this->username = $this->db->loadObjectList()[0]->username;
        $this->risk_cover = $this->db->loadObjectList()[0]->risk_cover;

        $version = new JVersion();
        require_once preg_replace('|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR) . '/helpers/config.php';
        $this->vm_version = VmConfig::getInstalledVersion();

        $config = [
            'app_name'      => $this->app_info->name,
            // Application Name
            'app_version'   => $this->app_info->version,
            // Application Version
            'app_host'      => "Joomla: " . $version->getShortVersion() . ' - Virtuemart: ' . VmConfig::getInstalledVersion(),
            // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
            'app_url'       => JURI::base(),
            // URL your site is hosted on
            'user_email'    => $this->username,
            'user_password' => $this->password,
        ];

        // Use the MDS API Files
        require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Cache.php';
        require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Collivery.php';
        $this->collivery = new Mds\Collivery($config);

        // Get some information from the API
        $this->towns = $this->collivery->getTowns();
        $this->services = $this->collivery->getServices();
        $this->location_types = $this->collivery->getLocationTypes();
        $this->addresses = $this->collivery->getAddresses();
        $this->default_address_id = [];//$this->collivery->getDefaultAddressId();
        $this->default_contacts = [];//$this->collivery->getContacts($this->default_address_id);
        $this->mds_services = [];//$this->collivery->getServices();

        // Class for converting lengths and weights
        require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/UnitConvertor.php';
        $this->converter = new UnitConvertor();
    }

    function display($tpl = null)
    {
        //Load helpers
        if (!class_exists('CurrencyDisplay')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php';
        }

        if (!class_exists('VmHTML')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'html.php';
        }

        if (!class_exists('vmPSPlugin')) {
            require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
        }

        $orderStatusModel = VmModel::getModel('orderstatus');
        $orderStates = $orderStatusModel->getOrderStatusList();

        $shippingModel = VmModel::getModel('mds');
        $curTask = JRequest::getWord('task');

        if ($curTask == 'edit') {
            $this->setLayout('order');

            VmConfig::loadJLang('com_virtuemart_shoppers', true);
            VmConfig::loadJLang('com_virtuemart_orders', true);

            // Load addl models
            $userFieldsModel = VmModel::getModel('userfields');
            $productModel = VmModel::getModel('product');

            // Get the data
            $virtuemart_order_id = JRequest::getInt('virtuemart_order_id');
            $order = $shippingModel->getOrder($virtuemart_order_id);

            $tot_parcel = 0;
            $total_weight = 0;
            $total_vol_weight = 0;

            foreach ($order['items'] as $item) {
                $quantity = 1;
                $product = $productModel->getProduct($item->virtuemart_product_id);
                while ($quantity <= $item->product_quantity) {
                    // Length coversion, mds collivery only acceps CM
                    if (strtolower($product->product_lwh_uom) != 'çm') {
                        $length = $this->converter->convert($product->product_length,
                            strtolower($product->product_lwh_uom), 'cm', 6);
                        $width = $this->converter->convert($product->product_width,
                            strtolower($product->product_lwh_uom), 'cm', 6);
                        $height = $this->converter->convert($product->product_height,
                            strtolower($product->product_lwh_uom), 'cm', 6);
                    } else {
                        $length = $product->product_length;
                        $width = $product->product_width;
                        $height = $product->product_height;
                    }

                    // Weight coversion, mds collivery only acceps KG'S
                    if (strtolower($product->product_weight_uom) != 'kg') {
                        $weight = $this->converter->convert($product->product_weight,
                            strtolower($product->product_weight_uom), 'kg', 6);
                    } else {
                        $weight = $product->product_weight;
                    }

                    $parcels[] = [
                        "length" => str_replace(",", "", $length),
                        "width"  => str_replace(",", "", $width),
                        "height" => str_replace(",", "", $height),
                        "weight" => str_replace(",", "", $weight),
                    ];

                    $tot_parcel += 1;
                    $quantity++;
                }
                $total_weight += $product->product_weight * $item->product_quantity;
                $total_vol_weight += (($product->product_length * $item->product_quantity) * ($product->product_width * $item->product_quantity) * ($product->product_height * $item->product_quantity));
            }

            $total_vweight = $total_vol_weight / 4000;
            $deliver_info = [
                'vol_weight' => $total_vol_weight,
                'weight'     => $total_weight,
                'parcels'    => $parcels,
            ];

            $_orderID = $order['details']['BT']->virtuemart_order_id;
            $orderbt = $order['details']['BT'];
            $orderst = (array_key_exists('ST', $order['details'])) ? $order['details']['ST'] : $orderbt;
            $orderbt->invoiceNumber = $shippingModel->getInvoiceNumber($orderbt->virtuemart_order_id);
            $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
            $this->assignRef('currency', $currency);

            // Create an array to allow orderlinestatuses to be translated
            // We'll probably want to put this somewhere in ShopFunctions...
            $_orderStatusList = [];
            foreach ($orderStates as $orderState) {
                //$_orderStatusList[$orderState->virtuemart_orderstate_id] = $orderState->order_status_name;
                //When I use update, I have to use this?
                $_orderStatusList[$orderState->order_status_code] = JText::_($orderState->order_status_name);
            }

            $_itemStatusUpdateFields = [];
            $_itemAttributesUpdateFields = [];
            foreach ($order['items'] as $_item) {
                $_itemStatusUpdateFields[$_item->virtuemart_order_item_id] = JHTML::_('select.genericlist',
                    $orderStates, "item_id[" . $_item->virtuemart_order_item_id . "][order_status]",
                    'class="selectItemStatusCode"', 'order_status_code', 'order_status_name', $_item->order_status,
                    'order_item_status' . $_item->virtuemart_order_item_id, true);
            }

            if (!isset($_orderStatusList[$orderbt->order_status])) {
                if (empty($orderbt->order_status)) {
                    $orderbt->order_status = 'unknown';
                }
                $_orderStatusList[$orderbt->order_status] = JText::_('COM_VIRTUEMART_UNKNOWN_ORDER_STATUS');
            }

            /* Assign the data */
            $this->assignRef('orderdetails', $order);
            $this->assignRef('orderID', $_orderID);
            $this->assignRef('userfields', $userfields);
            $this->assignRef('shipmentfields', $shipmentfields);
            $this->assignRef('orderstatuslist', $_orderStatusList);
            $this->assignRef('itemstatusupdatefields', $_itemStatusUpdateFields);
            $this->assignRef('itemattributesupdatefields', $_itemAttributesUpdateFields);
            $this->assignRef('orderbt', $orderbt);
            $this->assignRef('orderst', $orderst);
            $this->assignRef('virtuemart_shipmentmethod_id', $orderbt->virtuemart_shipmentmethod_id);

            /* Data for the Edit Status form popup */
            $_currentOrderStat = $order['details']['BT']->order_status;
            // used to update all item status in one time
            $_orderStatusSelect = JHTML::_('select.genericlist', $orderStates, 'order_status', '', 'order_status_code',
                'order_status_name', $_currentOrderStat, 'order_items_status', true);
            $this->assignRef('orderStatSelect', $_orderStatusSelect);
            $this->assignRef('currentOrderStat', $_currentOrderStat);
            $this->SetViewTitle('MDS Confirm Collivery', 'Edit or accept order #' . $_orderID);

            $state_name = $shippingModel->getState($orderst->virtuemart_state_id);
            $this->assignRef('state_name', $state_name);
            $this->assignRef('mds_service', $shippingModel->getService($orderst->virtuemart_shipmentmethod_id));
            $this->assignRef('package_info', $deliver_info);

            $this->assignRef('towns', $this->towns);
            $this->assignRef('addresses', $this->addresses);
            $this->assignRef('location_types', $this->location_types);
            $this->assignRef('default_address_id', $this->default_address_id);
            $this->assignRef('default_contacts', $this->default_contacts);
            $this->assignRef('mds_services', $this->mds_services);
            $this->assignRef('risk_cover', $this->risk_cover);

            $destination_towns = $this->towns;
            $selected_town = array_search($state_name, $destination_towns);
            unset($destination_towns[$selected_town]);
            $destination_suburbs = $this->collivery->getSuburbs($selected_town);
            $this->assignRef('destination_towns', $destination_towns);

            if ($orderst->mds_suburb_id != "") {
                $destination_suburb_key = $orderst->mds_suburb_id;
            } else {
                list($destination_suburb_key) = array_keys($destination_suburbs);
            }
            $this->assignRef('destination_suburb_key', $destination_suburb_key);
            $this->assignRef('first_destination_suburb', $destination_suburbs[$destination_suburb_key]);
            unset($destination_suburbs[$destination_suburb_key]);
            $this->assignRef('destination_suburbs', $destination_suburbs);

            $destination_location_types = $this->location_types;
            $this->assignRef('first_destination_location_type',
                $destination_location_types[$orderst->mds_location_type]);
            unset($destination_location_types[$orderst->mds_location_type]);
            $this->assignRef('destination_location_types', $destination_location_types);
        } elseif ($curTask == 'awaiting_dispatch') {
            $this->setLayout('index_dispatch');

            // Older version check
            if (preg_replace('/[^0-9]/', "", $this->vm_version) >= preg_replace('/[^0-9]/', "", '2.0.26d')) {
                $model = VmModel::getModel();
                $this->addStandardDefaultViewLists($model, 'created_on');
                $orderslist = $model->getOrdersList();

                $this->assignRef('orderstatuses', $orderStates);

                if (!class_exists('CurrencyDisplay')) {
                    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
                }

                /* Apply currency This must be done per order since it's vendor specific */
                $_currencies = []; // Save the currency data during this loop for performance reasons
                if ($orderslist) {
                    foreach ($orderslist as $virtuemart_order_id => $order) {

                        //This is really interesting for multi-X, but I avoid to support it now already, lets stay it in the code
                        if (!array_key_exists('v' . $order->virtuemart_vendor_id, $_currencies)) {
                            $_currencies['v' . $order->virtuemart_vendor_id] = CurrencyDisplay::getInstance('',
                                $order->virtuemart_vendor_id);
                        }
                        $order->order_total = $_currencies['v' . $order->virtuemart_vendor_id]->priceDisplay($order->order_total);
                        $order->invoiceNumber = $model->getInvoiceNumber($order->virtuemart_order_id);
                    }
                }

                /* Assign the data */
                $this->assignRef('orderslist', $orderslist);
                $this->assignRef('services', $this->services);

                $pagination = $model->getPagination();
                $this->assignRef('pagination', $pagination);
                $this->SetViewTitle('MDS Confirm Collivery', 'MDS shipping awaiting confirmation');
            } else {
                // old version!
                $model = VmModel::getModel();
                $this->addStandardDefaultViewLists($model, 'created_on');
                $orderStatusModel = VmModel::getModel('orderstatus');
                $orderstates = JRequest::getWord('order_status_code', '');
                $orderslist = $model->getOrdersList();

                $this->assignRef('orderstatuses', $orderStates);

                if (!class_exists('CurrencyDisplay')) {
                    require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php';
                }

                /* Apply currency This must be done per order since it's vendor specific */
                $_currencies = []; // Save the currency data during this loop for performance reasons

                if ($orderslist) {

                    foreach ($orderslist as $virtuemart_order_id => $order) {

                        if (!empty($order->order_currency)) {
                            $currency = $order->order_currency;
                        } elseif ($order->virtuemart_vendor_id) {
                            if (!class_exists('VirtueMartModelVendor')) {
                                require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php';
                            }
                            $currObj = VirtueMartModelVendor::getVendorCurrency($order->virtuemart_vendor_id);
                            $currency = $currObj->virtuemart_currency_id;
                        }
                        //This is really interesting for multi-X, but I avoid to support it now already, lets stay it in the code
                        if (!array_key_exists('curr' . $currency, $_currencies)) {

                            $_currencies['curr' . $currency] = CurrencyDisplay::getInstance($currency,
                                $order->virtuemart_vendor_id);
                        }

                        $order->order_total = $_currencies['curr' . $currency]->priceDisplay($order->order_total);
                        $order->invoiceNumber = $model->getInvoiceNumber($order->virtuemart_order_id);
                    }
                }

                /* Assign the data */
                $this->assignRef('orderslist', $orderslist);
                $this->assignRef('services', $this->services);

                $pagination = $model->getPagination();
                $this->assignRef('pagination', $pagination);
                $this->SetViewTitle('MDS Confirm Collivery', 'MDS shipping awaiting confirmation');
            }
        } elseif ($curTask == 'config') {
            $shippingModel = VmModel::getModel();
            $post = JRequest::get('post');
            if (!empty($post)) {
                // change our password
                $this->db->setQuery("UPDATE `#__mds_collivery_config` SET `password` = '" . $post['password'] . "', `username` = '" . $post['username'] . "', `risk_cover` = '" . $post['risk_cover'] . "' WHERE `id` = 1;");
                $this->db->query();
            }
            $this->setLayout('config');
            $config = $shippingModel->getConfig();
            $this->assignRef('config', $config);
            $this->SetViewTitle('MDS Collivery Config', 'Account settings & Update option');
        } elseif ($curTask == 'view') {
            $model = VmModel::getModel();
            $waybill = JRequest::getInt('waybill');
            $order = $model->getAccepted($waybill);

            $this->setLayout('view');
            $this->SetViewTitle('MDS Confirmed', 'Waybill #' . $waybill);

            $directory = preg_replace('|administrator/|i', "",
                    JPATH_COMPONENT) . '/views/mds/tmpl/waybills/' . $waybill;

            // Do we have images of the parcels
            if ($pod = $this->collivery->getPod($waybill)) {
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                file_put_contents($directory . '/' . $pod['filename'], base64_decode($pod['file']));
            }

            // Do we have proof of delivery
            if ($parcels = $this->collivery->getParcelImageList($waybill)) {
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                foreach ($parcels as $parcel) {
                    $size = $parcel['size'];
                    $mime = $parcel['mime'];
                    $filename = $parcel['filename'];
                    $parcel_id = $parcel['parcel_id'];

                    if ($image = $this->collivery->getParcelImage($parcel_id)) {
                        file_put_contents($directory . '/' . $filename, base64_decode($image['file']));
                    }
                }
            }

            // Get our tracking information
            $tracking = $this->collivery->getStatus($waybill);
            $validation_results = json_decode($order->validation_results);

            $collectionAddress = $this->collivery->getAddress($validation_results->collivery_from);
            $deliveryAddress = $this->collivery->getAddress($validation_results->collivery_to);
            $collectionContact = $this->collivery->getContacts($validation_results->collivery_from);
            $deliveryContact = $this->collivery->getContacts($validation_results->collivery_to);

            $this->assignRef('order', $order);
            $this->assignRef('collection_address', $collectionAddress);
            $this->assignRef('destination_address', $deliveryAddress);
            $this->assignRef('collection_contacts', $collectionContact);
            $this->assignRef('destination_contacts', $deliveryContact);

            // Set our status
            if ($tracking['status_id'] == 6) {
                // change our status
                $this->db->setQuery("UPDATE `#__mds_collivery_processed` SET `status` = 0 WHERE `waybill` = " . $waybill . ";");
                $this->db->query();
            }

            $this->assignRef('tracking', $tracking);
            $pod = glob($directory . "/*.{pdf,PDF}", GLOB_BRACE);
            $this->assignRef('pod', $pod);
            $imageList = glob($directory . "/*.{jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG}", GLOB_BRACE);
            $this->assignRef('image_list', $imageList);
            $view_waybill = 'https://quote.collivery.co.za/waybillpdf.php?wb=' . base64_encode($waybill) . '&output=I';
            $this->assignRef('view_waybill', $view_waybill);
        } elseif ($curTask == 'index') {
            $this->setLayout('index');

            $post = JRequest::get('post');
            $status = (isset($post['status']) && $post['status'] != "") ? $post['status'] : 1;
            $waybill = (isset($post['waybill']) && $post['waybill'] != "") ? $post['waybill'] : false;

            $model = VmModel::getModel();
            $orderslist = $model->getAcceptedList($status, $waybill);

            /* Assign the data */
            $this->assignRef('orderslist', $orderslist);

            $pagination = $model->getPagination();
            $this->assignRef('pagination', $pagination);
            $this->SetViewTitle('MDS Confirmed', 'Orders already passed to MDS Collivery');
        }
        parent::display($tpl);
    }

    function SetViewTitle($name = '', $msg = '', $icon = 'shipmentmethod')
    {

        $view = JRequest::getWord('view', JRequest::getWord('controller'));
        if ($name == '') {
            $name = strtoupper($view);
        }
        if ($icon == '') {
            $icon = strtolower($view);
        }
        if (!$task = JRequest::getWord('task')) {
            $task = 'list';
        }

        if (!empty($msg)) {
            $msg = ' <span style="color: #666666; font-size: large;">' . $msg . '</span>';
        }

        $viewText = $name;

        $taskName = ':';

        JToolBarHelper::title($viewText . ' ' . $taskName . $msg, 'head vm_' . $icon . '_48');
        $this->assignRef('viewName', $viewText); //was $viewName?
        $app = JFactory::getApplication();
        $doc = JFactory::getDocument();
        $doc->setTitle($app->getCfg('sitename') . ' - ' . JText::_('JADMINISTRATION') . ' - ' . strip_tags($msg));
    }

}
