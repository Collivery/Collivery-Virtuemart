<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

// Load the controller framework
jimport( 'joomla.application.component.controller' );

if ( ! class_exists( 'VmController' ) ) require JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmcontroller.php';

class VirtuemartControllerMds extends VmController {

	function __construct()
	{
		parent::__construct();

	}

	public function get_quote()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( null );
	}

	public function accept_quote()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( null );
	}

	public function update()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( null );
	}

	public function get_suburbs()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( null );
	}

	public function get_contacts()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( null );
	}

	public function edit($layout = 'edit')
	{
		$view = $this->getView( 'mds', 'html' );
		$view->display( null );
	}

	public function config()
	{
		$view = $this->getView( 'mds', 'html' );
		$view->display( null );
	}

}
