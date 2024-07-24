<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

// Load the view framework
if ( ! class_exists( 'VmView' ) ) require JPATH_VM_SITE.DS.'helpers'.DS.'vmview.php';

/**
 * Handle the orders view
 */
class VirtuemartViewMds extends VmView
{
	var $db;
	var $towns;
	var $services;
	var $location_types;
	var $extension_id;
	var $app_name;
	var $app_info;
	var $collivery;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		// Load the database and execute our sql file
		$this->db = JFactory::getDBO();

		// Get information of our plugin so we can pass it on to MDS Collivery for Logs
		$sel_query = "SELECT * FROM `#__extensions` where type = 'plugin' and element = 'mds_shipping' and folder = 'vmshipment';";
		$this->db->setQuery( $sel_query );
		$this->db->query();
		$this->extension_id = $this->db->loadObjectList()[0]->extension_id;
		$this->app_name = $this->db->loadObjectList()[0]->extension_id;
		$this->app_info = json_decode( $this->db->loadObjectList()[0]->manifest_cache );

		// Get our login information
		$config_query = "SELECT * FROM `#__mds_collivery_config` where id = 1;";
		$this->db->setQuery( $config_query );
		$this->db->query();
		$this->password = $this->db->loadObjectList()[0]->password;
		$this->username = $this->db->loadObjectList()[0]->username;

		$version = new JVersion();
		require_once preg_replace( '|com_installer|i', "", JPATH_COMPONENT_ADMINISTRATOR ).'/helpers/config.php';

		$config = array(
			'app_name'      => $this->app_info->name, // Application Name
			'app_version'   => $this->app_info->version, // Application Version
			'app_host'      => "Joomla: ".$version->getShortVersion().' - Virtuemart: '.VmConfig::getInstalledVersion(), // Framework/CMS name and version, eg 'Wordpress 3.8.1 WooCommerce 2.0.20' / ''
			'app_url'       => JURI::base(), // URL your site is hosted on
			'user_email'    => $this->username,
			'user_password' => $this->password
		);

		// Use the MDS API Files
		require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Cache.php';
		require_once JPATH_PLUGINS . '/vmshipment/mds_shipping/Mds/Collivery.php';
		$this->collivery = new Mds\Collivery( $config );
	}

	function display( $tpl = null )
	{
		$post = JRequest::get( 'post' );
		if ( !empty( $post ) ) {
			$this->assignRef( 'waybill', $post['waybill'] );

			// check if the waybill number exists on our server before calling the API
			$sel_query = "SELECT * FROM `#__mds_collivery_processed` where waybill=".$post['waybill'].";";
			$this->db->setQuery( $sel_query );
			$this->db->query();
			if ( isset( $this->db->loadObjectList()[0] ) ) {
				$this->assignRef( 'tracking', $this->collivery->getStatus( $post['waybill'] ) );
			} else {
				$error = 'Your Waybill does not exist on our system.';
				$this->assignRef( 'error', $error );
			}
		}

		$this->setLayout( 'tracking' );
		parent::display( $tpl );
	}
}
