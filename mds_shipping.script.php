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


        $version = new Version();
        $joomlaVersion = $version->getShortVersion();
        $virtuemartVersion = $this->getVirtueMartVersion();
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
        require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Cache.php';
        require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Collivery.php';
        $collivery = new Mds\Collivery($config);

        // Get information from the API
        $this->towns = $collivery->getTowns();
        $this->services = $collivery->getServices();
        $this->location_types = $collivery->getLocationTypes();
        $this->suburbs = $collivery->getSuburbs('');
    }

    /**
     * Method to install the extension
     *
     * @return void
     */
    public function install($parent)
    {
        $this->runSqlScript('sql/mds_collivery_install.sql');

        $this->init();

        // Copy necessary files
        $this->copyFiles();

        // Insert menu entries
        $this->insertMenuEntries();

        // Insert new mds module
        $this->insertMdsModule();

        // Insert services and towns
        $this->insertServicesAndTowns();
    }

    /**
     * Method to update the extension
     *
     * @return void
     */
    public function update($parent)
    {
        $this->install($parent);
    }

    /**
     * Method to uninstall the extension
     *
     * @return void
     */
    public function uninstall($parent)
    {
        // Implement uninstall logic if necessary
    }

    /**
     * Copy files method
     */
    private function copyFiles()
    {
        $folders = ['components', 'administrator', 'mds_validation'];
        foreach ($folders as $folder) {
            if ($folder == 'components') {
                $source = JPATH_PLUGINS . '/vmshipment/mds_shipping/components/com_virtuemart';
                $destination = JPATH_SITE . '/components/com_virtuemart';
            } elseif ($folder == 'mds_validation') {
                $dest = JPATH_PLUGINS . '/vmuserfield';
                if (!Folder::exists($dest)) {
                    Folder::create($dest, 0777);
                }
                $source = JPATH_PLUGINS . '/vmshipment/mds_shipping/mds_validation';
                $destination = $dest . '/mds_validation';
            } elseif ($folder == 'administrator') {
                $source = JPATH_PLUGINS . '/vmshipment/mds_shipping/administrator/components/com_virtuemart';
                $destination = JPATH_ADMINISTRATOR . '/components/com_virtuemart';
            }
            Folder::copy($source, $destination, '', true);
        }

        // Replace jQuery file
        $jquery_destination = JPATH_SITE . '/components/com_virtuemart/assets/js/jquery.min.js';
        if (File::exists($jquery_destination)) {
            File::delete($jquery_destination);
        }
        $jquery_source = JPATH_PLUGINS . '/vmshipment/mds_shipping/jquery.min.js';
        File::copy($jquery_source, $jquery_destination);
    }

    /**
     * Insert menu entries method
     */
    private function insertMenuEntries()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('module_id'))
            ->from($this->db->quoteName('#__virtuemart_modules'))
            ->where($this->db->quoteName('module_name') . ' = ' . $this->db->quote('order'));
        $this->db->setQuery($query);
        $order_module_id = $this->db->loadResult();

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('module_id'))
            ->from($this->db->quoteName('#__virtuemart_modules'))
            ->where($this->db->quoteName('module_name') . ' = ' . $this->db->quote('configuration'));
        $this->db->setQuery($query);
        $configuration_module_id = $this->db->loadResult();

        $adminmenuentries = [
            [$order_module_id, 'MDS Confirm Collivery', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'awaiting_dispatch'],
            [$order_module_id, 'MDS Already Confirmed', '', 'vmicon vmicon-16-lorry', 2, 'mds', 'index'],
            [$configuration_module_id, 'MDS Collivery Config', '', 'vmicon vmicon-16-config', 1, 'mds', 'config']
        ];

        $query = $this->db->getQuery(true);
        $query->insert($this->db->quoteName('#__virtuemart_adminmenuentries'))
            ->columns($this->db->quoteName(['module_id', 'name', 'link', 'icon_class', 'ordering', 'view', 'task']))
            ->values($adminmenuentries);
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Insert MDS module method
     */
    private function insertMdsModule()
    {
        $query = $this->db->getQuery(true);
        $query->insert($this->db->quoteName('#__virtuemart_modules'))
            ->columns($this->db->quoteName(['module_name', 'module_description', 'module_perms', 'published', 'is_admin', 'ordering']))
            ->values(
                $this->db->quote('mds') . ', ' .
                $this->db->quote('Process shipping: Edit shipping details, add instructions.') . ', ' .
                $this->db->quote('admin,storeadmin') . ', ' .
                '1, 1, 3'
            );
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Insert services and towns method
     */
    private function insertServicesAndTowns()
    {
        // Get South Africa ID
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('virtuemart_country_id'))
            ->from($this->db->quoteName('#__virtuemart_countries'))
            ->where($this->db->quoteName('country_name') . ' = ' . $this->db->quote('South Africa'));
        $this->db->setQuery($query);
        $virtuemart_country_id = $this->db->loadResult();

        // Insert services
        foreach ($this->services as $service_key => $service_value) {
            $query = $this->db->getQuery(true);
            $query->insert($this->db->quoteName('#__virtuemart_shipmentmethods'))
                ->columns($this->db->quoteName(['shipment_jplugin_id', 'shipment_element', 'shipment_params']))
                ->values(
                    $this->db->quote($this->extension_id) . ', ' .
                    $this->db->quote('mds_shipping') . ', ' .
                    $this->db->quote('markup=10')
                );
            $this->db->setQuery($query);
            $this->db->execute();
            $new_virtuemart_shipmentmethod_id = $this->db->insertid();

            $query = $this->db->getQuery(true);
            $query->insert($this->db->quoteName('#__virtuemart_shipmentmethods_en_gb'))
                ->columns($this->db->quoteName(['virtuemart_shipmentmethod_id', 'shipment_name', 'shipment_desc', 'slug']))
                ->values(
                    $this->db->quote($new_virtuemart_shipmentmethod_id) . ', ' .
                    $this->db->quote($service_value) . ', ' .
                    $this->db->quote('MDS Collivery: ' . $service_value) . ', ' .
                    $this->db->quote($service_key)
                );
            $this->db->setQuery($query);
            $this->db->execute();
        }

        // Delete existing towns
        $query = $this->db->getQuery(true);
        $query->delete($this->db->quoteName('#__virtuemart_states'))
            ->where($this->db->quoteName('virtuemart_country_id') . ' = ' . $this->db->quote($virtuemart_country_id));
        $this->db->setQuery($query);
        $this->db->execute();

        // Insert towns
        $values = [];
        foreach ($this->towns as $town) {
            $values[] = $this->db->quote($virtuemart_country_id) . ', ' . $this->db->quote($town['name']);
        }
        $query = $this->db->getQuery(true);
        $query->insert($this->db->quoteName('#__virtuemart_states'))
            ->columns($this->db->quoteName(['virtuemart_country_id', 'state_name']))
            ->values($values);
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Run SQL script method
     */
    private function runSqlScript($file)
    {
        $sql = file_get_contents(JPATH_PLUGINS . '/vmshipment/mds_shipping/' . $file);
        if ($sql === false) {
            Factory::getApplication()->enqueueMessage('Error reading SQL file', 'error');
            return;
        }

        $queries = $this->db->splitSql($sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $this->db->setQuery($query);
                try {
                    $this->db->execute();
                } catch (\Exception $e) {
                    Factory::getApplication()->enqueueMessage('SQL Error: ' . $e->getMessage(), 'error');
                }
            }
        }
    }


    private function getVirtueMartVersion()
    {
        // Check for VM_VERSION constant
        if (defined('VM_VERSION')) {
            return VM_VERSION;
        }

        // Try to get version from the virtuemart_configs table
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('config_value'))
                ->from($db->quoteName('#__virtuemart_configs'))
                ->where($db->quoteName('config_name') . ' = ' . $db->quote('version'));
            $db->setQuery($query);
            $version = $db->loadResult();
            if ($version) {
                return $version;
            }
        } catch (\Exception $e) {
            // If there's an error (e.g., table doesn't exist), we'll fall through to the next method
        }

        // Try to get version from the manifest file
        $manifest_file = JPATH_ADMINISTRATOR . '/components/com_virtuemart/virtuemart.xml';
        if (file_exists($manifest_file)) {
            $manifest = simplexml_load_file($manifest_file);
            if ($manifest) {
                return (string)$manifest->version;
            }
        }

        // If we can't determine the version, return a placeholder
        return 'Unknown';
    }
}
