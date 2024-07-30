<?php

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Component\ComponentHelper;

if (!class_exists('VmView')) {
    require_once JPATH_VM_SITE.'/helpers/vmview.php';
}

class VirtuemartViewMds extends VmView
{
    protected $db;
    protected $towns;
    protected $services;
    protected $location_types;
    protected $extension_id;
    protected $app_name;
    protected $app_info;
    protected $collivery;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->db = Factory::getDbo();

        $this->loadExtensionInfo();
        $this->loadConfig();
        $this->initCollivery();
    }

    protected function loadExtensionInfo()
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type').' = '.$this->db->quote('plugin'))
            ->where($this->db->quoteName('element').' = '.$this->db->quote('mds_shipping'))
            ->where($this->db->quoteName('folder').' = '.$this->db->quote('vmshipment'));

        $this->db->setQuery($query);
        $extension = $this->db->loadObject();

        if ($extension) {
            $this->extension_id = $extension->extension_id;
            $this->app_name = $extension->name;
            $this->app_info = json_decode($extension->manifest_cache);
        }
    }

    protected function loadConfig()
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__mds_collivery_config'))
            ->where($this->db->quoteName('id').' = 1');

        $this->db->setQuery($query);
        $config = $this->db->loadObject();

        if ($config) {
            $this->password = $config->password;
            $this->username = $config->username;
        }
    }

    protected function initCollivery()
    {
        $version = new Version();
        require_once JPATH_ADMINISTRATOR.'/components/com_virtuemart/helpers/config.php';

        $config = [
            'app_name' => $this->app_info->name,
            'app_version' => $this->app_info->version,
            'app_host' => 'Joomla: '.$version->getShortVersion().' - Virtuemart: '.VmConfig::getInstalledVersion(),
            'app_url' => Uri::base(),
            'user_email' => $this->username,
            'user_password' => $this->password
        ];

        require_once JPATH_PLUGINS.'/vmshipment/mds_shipping/Mds/Cache.php';
        require_once JPATH_PLUGINS.'/vmshipment/mds_shipping/Mds/Collivery.php';
        $this->collivery = new Mds\Collivery($config);
    }

    public function display($tpl = null)
    {
        $input = Factory::getApplication()->input;
        $waybill = $input->getString('waybill', '');

        if (!empty($waybill)) {
            $this->waybill = $waybill;

            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__mds_collivery_processed'))
                ->where($this->db->quoteName('waybill').' = '.$this->db->quote($waybill));

            $this->db->setQuery($query);
            $result = $this->db->loadObject();

            if ($result) {
                $this->tracking = $this->collivery->getStatus($waybill);
            } else {
                $this->error = 'Your Waybill does not exist on our system.';
            }
        }

        $this->setLayout('tracking');
        parent::display($tpl);
    }
}
