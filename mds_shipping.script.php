<?php

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;

// Load VirtueMart config
if (!class_exists('VmConfig')) {
    require(JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php');
}

/**
 * Installation script for the plugin
 */
class plgVmShipmentMds_ShippingInstallerScript extends InstallerScript
{
    protected $db;
    protected $towns;
    protected $services;
    protected $location_types;
    protected $extension_id;
    protected $app_name;
    protected $app_info;
    protected $suburbs;
    protected $password;
    protected $username;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            Factory::getApplication()->enqueueMessage('Your PHP version is not compatible with this plugin. Please update to PHP 7.4 or higher.', 'error');
            return false;
        }

        // Load the database
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Initialize method to load MDS API and configurations
     */
    protected function init()
    {
        // Get plugin information
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('mds_shipping'))
            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('vmshipment'));
        $this->db->setQuery($query);
        $extension = $this->db->loadObject();
        $this->extension_id = $extension->extension_id;
        $this->app_info = json_decode($extension->manifest_cache);

        // Get configuration
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__mds_collivery_config'))
            ->where($this->db->quoteName('id') . ' = 1');
        $this->db->setQuery($query);
        $config = $this->db->loadObject();
        $this->username = $config->username;
        $this->password = $config->password;

        $version = new \Joomla\CMS\Version();
        $joomlaVersion = $version->getShortVersion();
        $virtuemartVersion = VmConfig::getInstalledVersion(true);
        $app_host = 'Joomla: ' . $joomlaVersion . ' - Virtuemart: ' . $virtuemartVersion;

        // Setup config for MDS API
        $config = [
            'app_name' => $this->app_info->name,
            'app_version' => $this->app_info->version,
            'app_host' => $app_host,
            'app_url' => Uri::base(),
            'user_email' => $this->username,
            'user_password' => $this->password
        ];

        // Use the MDS API Files
        require_once 'Mds/Cache.php';
        require_once 'Mds/Collivery.php';
        $collivery = new Mds\Collivery($config);

        // Get information from the API
        $this->towns = $collivery->getTowns();
        $this->services = $collivery->getServices();
        $this->location_types = $collivery->getLocationTypes();
        $this->suburbs = $collivery->getSuburbs('');
    }

    /**
     * Called on installation
     *
     * @param mixed $adapter The object responsible for running this script
     *
     * @return boolean true on success
     */
    public function install($adapter)
    {
        $this->runSqlScript('sql/mds_collivery_install.sql');

        $this->init();

        // Copy necessary files
        foreach (['components', 'administrator', 'mds_validation'] as $folder) {
            if ($folder == 'components') {
                $source = JPATH_PLUGINS.'/vmshipment/mds_shipping/components/com_virtuemart';
                $destination = JPATH_SITE.'/components/com_virtuemart';
                $this->recurse_copy($source, $destination);
            } elseif ($folder == 'mds_validation') {
                $dest = JPATH_PLUGINS.'/vmuserfield';
                if (!is_dir($dest)) {
                    @mkdir($dest, 0755);
                }
                $source = JPATH_PLUGINS.'/vmshipment/mds_shipping/mds_validation';
                $destination = $dest.'/mds_validation';
                $this->recurse_copy($source, $destination);
            } elseif ($folder == 'administrator') {
                $source = JPATH_PLUGINS.'/vmshipment/mds_shipping/administrator/components/com_virtuemart';
                $destination = JPATH_ADMINISTRATOR.'/components/com_virtuemart';
                $this->recurse_copy($source, $destination);
            }
        }

        // Replace jQuery file
        $jquery_destination = JPATH_SITE.'/components/com_virtuemart/assets/js/jquery.min.js';
        if (file_exists($jquery_destination)) {
            unlink($jquery_destination);
        }
        $jquery_source = JPATH_PLUGINS.'/vmshipment/mds_shipping/jquery.min.js';
        copy($jquery_source, $jquery_destination);

        // Insert menu entries
        $sel_query = "SELECT module_id FROM `#__virtuemart_modules` WHERE module_name='order';";
        $this->db->setQuery($sel_query);
        $order_module_id = $this->db->loadResult();

        $sel_query = "SELECT module_id FROM `#__virtuemart_modules` WHERE module_name='configuration';";
        $this->db->setQuery($sel_query);
        $configuration_module_id = $this->db->loadResult();

        $adminmenuentries = '('.$order_module_id.", 'MDS Confirm Collivery', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'awaiting_dispatch'),";
        $adminmenuentries .= '('.$order_module_id.", 'MDS Already Confirmed', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'index'),";
        $adminmenuentries .= '('.$configuration_module_id.", 'MDS Collivery Config', '', 'vmicon vmicon-16-config', 1, 'mds', 'config');";
        $adminmenuentries_insert_query = 'INSERT INTO `#__virtuemart_adminmenuentries` (`module_id`, `name`, `link`, `icon_class`, `ordering`, `view`, `task`) VALUES '.$adminmenuentries;
        $this->db->setQuery($adminmenuentries_insert_query);
        $this->db->execute();

        // Insert new mds module
        $module_value = "('mds', 'Process shipping: Edit shipping details, add instructions.', 'admin,storeadmin', 1, 1, 3);";
        $module_insert_query = 'INSERT INTO `#__virtuemart_modules` (`module_name`, `module_description`, `module_perms`, `published`, `is_admin`, `ordering`) VALUES '.$module_value;
        $this->db->setQuery($module_insert_query);
        $this->db->execute();

        // Get South Africa ID
        $sel_query = "SELECT virtuemart_country_id FROM `#__virtuemart_countries` WHERE country_name='South Africa';";
        $this->db->setQuery($sel_query);
        $virtuemart_country_id = $this->db->loadResult();

        // Insert services
        foreach ($this->services as $service_key => $service_value) {
            $service_insert = '('.$this->extension_id.", 'mds_shipping', 'markup=10');";
            $states_insert_query = 'INSERT INTO `#__virtuemart_shipmentmethods` (`shipment_jplugin_id`, `shipment_element`, `shipment_params`) VALUES '.$service_insert;
            $this->db->setQuery($states_insert_query);
            $this->db->execute();
            $new_virtuemart_shipmentmethod_id = $this->db->insertid();

            $service_description_insert = '('.$new_virtuemart_shipmentmethod_id.", '".addslashes($service_value)."', 'MDS Collivery: ".addslashes($service_value)."', ".$service_key.')';
            $states_insert_query = 'INSERT INTO `#__virtuemart_shipmentmethods_en_gb` (`virtuemart_shipmentmethod_id`, `shipment_name`, `shipment_desc`, `slug`) VALUES '.$service_description_insert;
            $this->db->setQuery($states_insert_query);
            $this->db->execute();
        }

        // Delete existing towns
        $delete_query = 'DELETE FROM `#__virtuemart_states` WHERE `virtuemart_country_id`='.$virtuemart_country_id.';';
        $this->db->setQuery($delete_query);
        $this->db->execute();

        // Insert towns
        $town_sql = '';
        foreach ($this->towns as $towns_value) {
            $town_name = $this->db->quote($towns_value['name']);
            $town_sql .= '('.$virtuemart_country_id.', '.$town_name.'),';
        }
        $town_sql = rtrim($town_sql, ',');
        $town_insert_query = 'INSERT INTO `#__virtuemart_states` (`virtuemart_country_id`, `state_name`) VALUES '.$town_sql;
        $this->db->setQuery($town_insert_query);
        $this->db->execute();
    }

    /**
     * Recursive function to copy files and directories
     *
     * @param string $src Source path
     * @param string $dst Destination path
     */
    private function recurse_copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src.'/'.$file)) {
                    $this->recurse_copy($src.'/'.$file, $dst.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Called on update
     *
     * @param mixed $adapter The object responsible for running this script
     *
     * @return boolean true on success
     */
    public function update($adapter)
    {
        $this->install($adapter);
    }

    /**
     * Called on uninstallation
     *
     * @param mixed $adapter The object responsible for running this script
     *
     * @return boolean true on success
     */
    public function uninstall($adapter)
    {
        // Implement uninstall logic if necessary
    }


    private function runSqlScript($file)
    {
        $db = JFactory::getDbo();
        $query = file_get_contents(JPATH_PLUGINS . '/vmshipment/mds_shipping/' . $file);
        if ($query === false) {
            JFactory::getApplication()->enqueueMessage('Error reading SQL file', 'error');
            return;
        }

        $queries = explode(';', $query);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $db->setQuery($query);
                    $db->execute();
                } catch (RuntimeException $e) {
                    JFactory::getApplication()->enqueueMessage('SQL Error: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
}
