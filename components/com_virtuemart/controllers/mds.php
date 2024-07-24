<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

// Load the controller framework
jimport( 'joomla.application.component.controller' );

class VirtueMartControllerMds extends JController {

	/**
	 * Get Suburbs
	 */
	public function suburbs()
	{
		$view = $this->getView( 'mds', 'json' );
		$view->display( NULL );
	}

	public function display( $cachable = false, $urlparams = false )
	{
		$view = $this->getView( 'mds', 'html' );
		$view->display( NULL );
	}
}
