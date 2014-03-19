<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

if ( ! class_exists( 'VirtueMartModelOrders' ) ) require JPATH_VM_ADMINISTRATOR.DS.'models'.DS.'orders.php';

class VirtueMartModelMds extends VirtueMartModelOrders {

	/**
	 * Select the products to list on the product list page
	 *
	 * @param unknown $uid               integer Optional user ID to get the orders of a single user
	 * @param unknown $_ignorePagination boolean If true, ignore the Joomla pagination (for embedded use, default true)
	 */
	public function getOrdersList( $uid = 0, $noLimit = true )
	{
		$this->_noLimit = $noLimit;
		$select = " o.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS order_name "
			.',u.email as order_email,pm.payment_name AS payment_method,  mds.shipment_name AS mds_service';
		$from = $this->getOrdersListQuery();

		if ( !class_exists( 'Permissions' ) ) require JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'permissions.php';
		if ( !Permissions::getInstance()->check( 'admin' ) ) {
			$myuser  =JFactory::getUser();
			$where[]= ' u.virtuemart_user_id = ' . (int)$myuser->id.' AND o.virtuemart_vendor_id = "1" ';
		} else {
			if ( empty( $uid ) ) {
				$where[]= ' o.virtuemart_vendor_id = "1" ';
			} else {
				$where[]= ' u.virtuemart_user_id = ' . (int)$uid.' AND o.virtuemart_vendor_id = "1" ';
			}
		}

		if ( $search = JRequest::getString( 'search', false ) ) {

			$search = '"%' . $this->_db->getEscaped( $search, true ) . '%"' ;
			$search = str_replace( ' ', '%', $search );

			$searchFields = array();
			$searchFields[] = 'u.first_name';
			$searchFields[] = 'u.middle_name';
			$searchFields[] = 'u.last_name';
			$searchFields[] = 'o.order_number';
			$searchFields[] = 'u.company';
			$searchFields[] = 'u.email';
			$searchFields[] = 'u.phone_1';
			$searchFields[] = 'u.address_1';
			$searchFields[] = 'u.zip';
			$where[] = implode( ' LIKE '.$search.' OR ', $searchFields ) . ' LIKE '.$search.' ';
		}

		$order_status_code = "U";
		if ( $order_status_code and $order_status_code != -1 ) {
			$where[] = ' o.order_status = "P" OR o.order_status = "U"';
		}
		// Add this so that we dont get orders that dont have our suburb and town fields
		$where[] = ' u.mds_suburb_id != ""';

		if ( count( $where ) > 0 ) {
			$whereString = ' WHERE (' . implode( ' AND ', $where ) . ') ';
		} else {
			$whereString = '';
		}

		if ( JRequest::getCmd( 'view' ) == 'orders' ) {
			$ordering = $this->_getOrdering();
		} else {
			$ordering = ' order by o.modified_on DESC';
		}

		$this->_data = $this->exeSortSearchListQuery( 0, $select, $from, $whereString, '', $ordering );

		return $this->_data ;
	}

	/**
	 * List of tables to include for the product query
	 *
	 * @return string
	 */
	private function getOrdersListQuery()
	{
		return ' FROM #__virtuemart_orders as o
			LEFT JOIN #__virtuemart_order_userinfos as u
			ON u.virtuemart_order_id = o.virtuemart_order_id AND u.address_type="BT"
			LEFT JOIN #__virtuemart_paymentmethods_'.VMLANG.' as pm
			ON o.virtuemart_paymentmethod_id = pm.virtuemart_paymentmethod_id
			LEFT JOIN #__virtuemart_shipmentmethods_'.VMLANG.' as mds
			ON o.virtuemart_shipmentmethod_id = mds.virtuemart_shipmentmethod_id ';
	}

	public function getAcceptedList( $status, $waybill )
	{
		$db = JFactory::getDBO();
		if ( $waybill && $waybill != "" ) {
			$sel_query = "SELECT * FROM `#__mds_collivery_processed` WHERE `waybill`=".$waybill.";";
		} else {
			$sel_query = "SELECT * FROM `#__mds_collivery_processed` WHERE `status`=".$status.";";
		}

		$db->setQuery( $sel_query );
		$db->query();
		return $db->loadObjectList();
	}

	public function getAccepted( $waybill )
	{
		// Get order
		$db = JFactory::getDBO();
		$sel_query = "SELECT * FROM `#__mds_collivery_processed` WHERE `waybill`=".$waybill.";";
		$db->setQuery( $sel_query );
		$db->query();
		return $db->loadObjectList()[0];
	}

	function getState( $state_id )
	{
		// Get twon name
		$sel_query = "SELECT * FROM `#__virtuemart_states` WHERE `virtuemart_state_id`=".$state_id.";";
		$this->_db->setQuery( $sel_query );
		if ( isset( $this->_db->loadObjectList()[0] ) ) {
			return $this->_db->loadObjectList()[0]->state_name;
		} else {
			return false;
		}
	}

	function getService( $method_id )
	{
		// Get twon name
		$sel_query = "SELECT * FROM `#__virtuemart_shipmentmethods_".VMLANG."` WHERE `virtuemart_shipmentmethod_id`=".$method_id.";";
		$this->_db->setQuery( $sel_query );
		if ( isset( $this->_db->loadObjectList()[0] ) ) {
			return $this->_db->loadObjectList()[0]->slug;
		} else {
			return false;
		}
	}

	function getConfig()
	{
		$q = 'SELECT * FROM `#__mds_collivery_config`;';
		$this->_db->setQuery( $q );
		$config = $this->_db->loadObjectList()[0];
		return $config;
	}
}
