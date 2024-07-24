<?php
error_reporting( E_ERROR );
defined( '_JEXEC' ) or die( 'Restricted access' );

if ( ! class_exists( 'vmPSPlugin' ) ) {
	require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

class plgVmUserfieldMds_Validation extends vmPSPlugin
{
	function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );

		// Insert javascript into the head
		$document = JFactory::getDocument();
		$document->addScript( JURI::base() . "plugins/vmuserfield/mds_validation/mds_collivery.js" );
		$document->addScriptDeclaration( 'base_url = "'.JURI::base().'";' );
	}
}
