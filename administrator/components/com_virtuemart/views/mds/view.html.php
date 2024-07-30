<?php

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Version;

// Load the view framework
if (!class_exists('VmViewAdmin')) {
    require JPATH_VM_ADMINISTRATOR.'/helpers/vmviewadmin.php';
}

/**
 * HTML View class
 */
class VirtuemartViewMds extends VmViewAdmin
{
    public $db;
    public $towns;
    public $services;
    public $location_types;
    public $extension_id;
    public $app_name;
    public $app_info;
    public $collivery;
    public $converter;
    public $addresses;
    public $default_address_id;
    public $default_contacts;
    public $mds_services;
    public $risk_cover;
    public $username;
    public $password;
    public $vm_version;

    public function __construct()
    {
        parent::__construct();

        // Insert our standard javascript and css into the head
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        // Jquery validation
        $wa->registerAndUseStyle('mds.screen', 'components/com_virtuemart/views/mds/tmpl/css/screen.css');
        $wa->registerAndUseScript('mds.validate', 'components/com_virtuemart/views/mds/tmpl/js/jquery.validate.min.js');

        // Date Time Picker
        $wa->registerAndUseStyle('mds.datetimepicker', 'components/com_virtuemart/views/mds/tmpl/datetimepicker-master/jquery.datetimepicker.css');
        $wa->registerAndUseScript('mds.datetimepicker', 'components/com_virtuemart/views/mds/tmpl/datetimepicker-master/jquery.datetimepicker.js');

        // Our custom js and css
        $wa->registerAndUseScript('mds.collivery', 'components/com_virtuemart/views/mds/tmpl/js/mds_collivery.js');
        $wa->registerAndUseStyle('mds.collivery', 'components/com_virtuemart/views/mds/tmpl/css/mds_collivery.css');

        // Our base url
        $wa->addInlineScript('base_url = "'.Uri::base().'";');

        // Load the database
        $this->db = Factory::getDbo();

        // Get information of our plugin so we can pass it on to MDS Collivery for Logs
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type').' = '.$this->db->quote('plugin'))
            ->where($this->db->quoteName('element').' = '.$this->db->quote('mds_shipping'))
            ->where($this->db->quoteName('folder').' = '.$this->db->quote('vmshipment'));
        $this->db->setQuery($query);
        $result = $this->db->loadObject();

        $this->extension_id = $result->extension_id;
        $this->app_name = $result->name;
        $this->app_info = json_decode($result->manifest_cache);

        // Get our login information
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__mds_collivery_config'))
            ->where($this->db->quoteName('id').' = 1');
        $this->db->setQuery($query);
        $config = $this->db->loadObject();

        $this->password = $config->password;
        $this->username = $config->username;
        $this->risk_cover = $config->risk_cover;

        $version = new Version();
        require_once JPATH_ADMINISTRATOR.'/components/com_virtuemart/helpers/config.php';
        $this->vm_version = VmConfig::getInstalledVersion();

        $config = [
            'app_name' => $this->app_info->name,
            'app_version' => $this->app_info->version,
            'app_host' => 'Joomla: '.$version->getShortVersion().' - Virtuemart: '.$this->vm_version,
            'app_url' => Uri::base(),
            'user_email' => $this->username,
            'user_password' => $this->password,
        ];

        // Use the MDS API Files
        require_once JPATH_PLUGINS.'/vmshipment/mds_shipping/Mds/Cache.php';
        require_once JPATH_PLUGINS.'/vmshipment/mds_shipping/Mds/Collivery.php';
        $this->collivery = new Mds\Collivery($config);

        // Get some information from the API
        $this->towns = $this->collivery->getTowns();
        $this->services = $this->collivery->getServices();
        $this->location_types = $this->collivery->getLocationTypes();
        $this->addresses = $this->collivery->getAddresses();
        $this->default_address_id = [];//$this->collivery->getDefaultAddressId();
        $this->default_contacts = [];  //$this->collivery->getContacts($this->default_address_id);
        $this->mds_services = [];      //$this->collivery->getServices();

        // Class for converting lengths and weights
        require_once JPATH_PLUGINS.'/vmshipment/mds_shipping/UnitConvertor.php';
        $this->converter = new UnitConvertor();
    }

    public function display($tpl = null)
    {
        //Load helpers
        if (!class_exists('CurrencyDisplay')) {
            require JPATH_VM_ADMINISTRATOR.'/helpers/currencydisplay.php';
        }

        if (!class_exists('VmHTML')) {
            require JPATH_VM_ADMINISTRATOR.'/helpers/html.php';
        }

        if (!class_exists('vmPSPlugin')) {
            require JPATH_VM_PLUGINS.'/vmpsplugin.php';
        }

        $orderStatusModel = VmModel::getModel('orderstatus');
        $orderStates = $orderStatusModel->getOrderStatusList();

        $shippingModel = VmModel::getModel('mds');
        $curTask = Factory::getApplication()->input->getCmd('task');

        if ($curTask == 'edit') {
            $this->setLayout('order');

            VmConfig::loadJLang('com_virtuemart_shoppers', true);
            VmConfig::loadJLang('com_virtuemart_orders', true);

            // Load addl models
            $userFieldsModel = VmModel::getModel('userfields');
            $productModel = VmModel::getModel('product');

            // Get the data
            $virtuemart_order_id = Factory::getApplication()->input->getInt('virtuemart_order_id');
            $order = $shippingModel->getOrder($virtuemart_order_id);

            $tot_parcel = 0;
            $total_weight = 0;
            $total_vol_weight = 0;
            $parcels = [];

            foreach ($order['items'] as $item) {
                $quantity = 1;
                $product = $productModel->getProduct($item->virtuemart_product_id);
                while ($quantity <= $item->product_quantity) {
                    // Length conversion, mds collivery only accepts CM
                    if (strtolower($product->product_lwh_uom) != 'cm') {
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

                    // Weight conversion, mds collivery only accepts KG's
                    if (strtolower($product->product_weight_uom) != 'kg') {
                        $weight = $this->converter->convert($product->product_weight,
                            strtolower($product->product_weight_uom), 'kg', 6);
                    } else {
                        $weight = $product->product_weight;
                    }

                    $parcels[] = [
                        'length' => str_replace(',', '', $length),
                        'width' => str_replace(',', '', $width),
                        'height' => str_replace(',', '', $height),
                        'weight' => str_replace(',', '', $weight),
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
                'weight' => $total_weight,
                'parcels' => $parcels,
            ];

            $_orderID = $order['details']['BT']->virtuemart_order_id;
            $orderbt = $order['details']['BT'];
            $orderst = (array_key_exists('ST', $order['details'])) ? $order['details']['ST'] : $orderbt;
            $orderbt->invoiceNumber = $shippingModel->getInvoiceNumber($orderbt->virtuemart_order_id);
            $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
            $this->currency = $currency;

            // Create an array to allow orderlinestatuses to be translated
            $_orderStatusList = [];
            foreach ($orderStates as $orderState) {
                $_orderStatusList[$orderState->order_status_code] = Text::_($orderState->order_status_name);
            }

            $_itemStatusUpdateFields = [];
            $_itemAttributesUpdateFields = [];
            foreach ($order['items'] as $_item) {
                $_itemStatusUpdateFields[$_item->virtuemart_order_item_id] = HTMLHelper::_(
                    'select.genericlist',
                    $orderStates,
                    'item_id['.$_item->virtuemart_order_item_id.'][order_status]',
                    ['class' => 'selectItemStatusCode'],
                    'order_status_code',
                    'order_status_name',
                    $_item->order_status,
                    'order_item_status'.$_item->virtuemart_order_item_id,
                    true
                );
            }

            if (!isset($_orderStatusList[$orderbt->order_status])) {
                if (empty($orderbt->order_status)) {
                    $orderbt->order_status = 'unknown';
                }
                $_orderStatusList[$orderbt->order_status] = Text::_('COM_VIRTUEMART_UNKNOWN_ORDER_STATUS');
            }

            /* Assign the data */
            $this->orderdetails = $order;
            $this->orderID = $_orderID;
            $this->userfields = $userfields;
            $this->shipmentfields = $shipmentfields;
            $this->orderstatuslist = $_orderStatusList;
            $this->itemstatusupdatefields = $_itemStatusUpdateFields;
            $this->itemattributesupdatefields = $_itemAttributesUpdateFields;
            $this->orderbt = $orderbt;
            $this->orderst = $orderst;
            $this->virtuemart_shipmentmethod_id = $orderbt->virtuemart_shipmentmethod_id;

            /* Data for the Edit Status form popup */
            $_currentOrderStat = $order['details']['BT']->order_status;
            // used to update all item status in one time
            $_orderStatusSelect = HTMLHelper::_(
                'select.genericlist',
                $orderStates,
                'order_status',
                [],
                'order_status_code',
                'order_status_name',
                $_currentOrderStat,
                'order_items_status',
                true
            );
            $this->orderStatSelect = $_orderStatusSelect;
            $this->currentOrderStat = $_currentOrderStat;
            $this->setViewTitle('MDS Confirm Collivery', 'Edit or accept order #'.$_orderID);

            $state_name = $shippingModel->getState($orderst->virtuemart_state_id);
            $this->state_name = $state_name;
            $this->mds_service = $shippingModel->getService($orderst->virtuemart_shipmentmethod_id);
            $this->package_info = $deliver_info;

            $destination_towns = $this->towns;
            $selected_town = array_search($state_name, $destination_towns);
            unset($destination_towns[$selected_town]);
            $destination_suburbs = $this->collivery->getSuburbs($selected_town);
            $this->destination_towns = $destination_towns;

            if ($orderst->mds_suburb_id != '') {
                $destination_suburb_key = $orderst->mds_suburb_id;
            } else {
                [$destination_suburb_key] = array_keys($destination_suburbs);
            }
            $this->destination_suburb_key = $destination_suburb_key;
            $this->first_destination_suburb = $destination_suburbs[$destination_suburb_key];
            unset($destination_suburbs[$destination_suburb_key]);
            $this->destination_suburbs = $destination_suburbs;

            $destination_location_types = $this->location_types;
            $this->first_destination_location_type = $destination_location_types[$orderst->mds_location_type];
            unset($destination_location_types[$orderst->mds_location_type]);
            $this->destination_location_types = $destination_location_types;
        } elseif ($curTask == 'awaiting_dispatch') {
            $this->setLayout('index_dispatch');

            $model = VmModel::getModel();
            $this->addStandardDefaultViewLists($model, 'created_on');
            $orderslist = $model->getOrdersList();

            $this->orderstatuses = $orderStates;

            if (!class_exists('CurrencyDisplay')) {
                require JPATH_VM_ADMINISTRATOR.'/helpers/currencydisplay.php';
            }

            /* Apply currency This must be done per order since it's vendor specific */
            $_currencies = []; // Save the currency data during this loop for performance reasons
            if ($orderslist) {
                foreach ($orderslist as $virtuemart_order_id => $order) {
                    if (!array_key_exists('v'.$order->virtuemart_vendor_id, $_currencies)) {
                        $_currencies['v'.$order->virtuemart_vendor_id] = CurrencyDisplay::getInstance('', $order->virtuemart_vendor_id);
                    }
                    $order->order_total = $_currencies['v'.$order->virtuemart_vendor_id]->priceDisplay($order->order_total);
                    $order->invoiceNumber = $model->getInvoiceNumber($order->virtuemart_order_id);
                }
            }

            /* Assign the data */
            $this->orderslist = $orderslist;
            $this->pagination = $model->getPagination();
            $this->setViewTitle('MDS Confirm Collivery', 'MDS shipping awaiting confirmation');
        } elseif ($curTask == 'config') {
            $shippingModel = VmModel::getModel();
            $post = Factory::getApplication()->input->post->getArray();
            if (!empty($post)) {
                // change our password
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__mds_collivery_config'))
                    ->set($this->db->quoteName('password').' = '.$this->db->quote($post['password']))
                    ->set($this->db->quoteName('username').' = '.$this->db->quote($post['username']))
                    ->set($this->db->quoteName('risk_cover').' = '.$this->db->quote($post['risk_cover']))
                    ->where($this->db->quoteName('id').' = 1');
                $this->db->setQuery($query);
                $this->db->execute();
            }
            $this->setLayout('config');
            $this->config = $shippingModel->getConfig();
            $this->setViewTitle('MDS Collivery Config', 'Account settings & Update option');
        } elseif ($curTask == 'view') {
            $model = VmModel::getModel();
            $waybill = Factory::getApplication()->input->getInt('waybill');
            $order = $model->getAccepted($waybill);

            $this->setLayout('view');
            $this->setViewTitle('MDS Confirmed', 'Waybill #'.$waybill);

            $directory = JPATH_SITE.'/components/com_virtuemart/views/mds/tmpl/waybills/'.$waybill;

            // Do we have images of the parcels
            if ($pod = $this->collivery->getPod($waybill)) {
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                file_put_contents($directory.'/'.$pod['filename'], base64_decode($pod['file']));
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
                        file_put_contents($directory.'/'.$filename, base64_decode($image['file']));
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

            $this->order = $order;
            $this->collection_address = $collectionAddress;
            $this->destination_address = $deliveryAddress;
            $this->collection_contacts = $collectionContact;
            $this->destination_contacts = $deliveryContact;

            // Set our status
            if ($tracking['status_id'] == 6) {
                // change our status
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__mds_collivery_processed'))
                    ->set($this->db->quoteName('status').' = 0')
                    ->where($this->db->quoteName('waybill').' = '.$waybill);
                $this->db->setQuery($query);
                $this->db->execute();
            }

            $this->tracking = $tracking;
            $this->pod = glob($directory.'/*.{pdf,PDF}', GLOB_BRACE);
            $this->image_list = glob($directory.'/*.{jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG}', GLOB_BRACE);
            $this->view_waybill = 'https://quote.collivery.co.za/waybillpdf.php?wb='.base64_encode($waybill).'&output=I';
        } elseif ($curTask == 'index') {
            $this->setLayout('index');

            $post = Factory::getApplication()->input->post->getArray();
            $status = (isset($post['status']) && $post['status'] != '') ? $post['status'] : 1;
            $waybill = (isset($post['waybill']) && $post['waybill'] != '') ? $post['waybill'] : false;

            $model = VmModel::getModel();
            $this->orderslist = $model->getAcceptedList($status, $waybill);
            $this->pagination = $model->getPagination();
            $this->setViewTitle('MDS Confirmed', 'Orders already passed to MDS Collivery');
        }
        parent::display($tpl);
    }

    protected function setViewTitle($name = '', $msg = '', $icon = 'shipmentmethod')
    {
        $view = Factory::getApplication()->input->getCmd('view', Factory::getApplication()->input->getCmd('controller'));
        if ($name == '') {
            $name = strtoupper($view);
        }
        if ($icon == '') {
            $icon = strtolower($view);
        }
        if (!$task = Factory::getApplication()->input->getCmd('task')) {
            $task = 'list';
        }

        if (!empty($msg)) {
            $msg = ' <span style="color: #666666; font-size: large;">'.$msg.'</span>';
        }

        $viewText = $name;
        $taskName = ':';

        Factory::getApplication()->JComponentTitle = $viewText.' '.$taskName.$msg;
        $this->viewName = $viewText;

        $document = Factory::getApplication()->getDocument();
        $document->setTitle(Factory::getApplication()->get('sitename').' - '.Text::_('JADMINISTRATION').' - '.strip_tags($msg));
    }
}


