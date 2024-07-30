<?php

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;

/**
 * Json View class for the VirtueMart Component
 */
class VirtuemartViewMds extends HtmlView
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

        // Get some information from the API
        $this->towns = $this->collivery->getTowns();
        $this->services = $this->collivery->getServices();
        $this->location_types = $this->collivery->getLocationTypes();
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
        $task = $input->getCmd('task');
        $user = Factory::getUser();

        $mds_suburb_id = null;
        $mds_location_type = null;

        if ($user->id > 0) {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__virtuemart_userinfos'))
                ->where($this->db->quoteName('virtuemart_user_id').' = '.$this->db->quote($user->id));

            $this->db->setQuery($query);
            $userInfo = $this->db->loadObject();

            if ($userInfo) {
                $mds_suburb_id = $userInfo->mds_suburb_id;
                $mds_location_type = $userInfo->mds_location_type;
            }
        }

        if ($task === 'suburbs') {
            $townName = $input->getString('town_name');
            $townId = array_search($townName, $this->collivery->getTowns());
            $suburbs = $this->collivery->getSuburbs($townId);

            if (!$suburbs) {
                echo '<option value="0">Error retrieving suburbs. Try again.</option>';
            } else {
                $options = [];
                foreach ($suburbs as $key => $suburb) {
                    if ($key !== '' && $suburb !== '') {
                        $selected = ($mds_suburb_id == $key) ? ' selected="selected"' : '';
                        $options[] = '<option value="'.$key.'"'.$selected.'>'.$suburb.'</option>';
                    }
                }
                echo implode('', $options);
            }
            Factory::getApplication()->close();
        }
    }
}
