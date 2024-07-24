<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
AdminUIHelper::startAdminArea ( $this );
?>
<h1>Orders Awaiting Dispatch</h1>
<form action="index.php" method="post" name="adminForm" id="adminForm">
	<table class="adminlist table table-striped ui-sortable" cellspacing="0" cellpadding="0">
		<thead>
		<tr>
			<th><?php echo $this->sort ( 'order_number', 'COM_VIRTUEMART_ORDER_LIST_NUMBER' )  ?></th>
			<th><?php echo $this->sort ( 'order_name', 'COM_VIRTUEMART_ORDER_PRINT_NAME' )  ?></th>
			<th><?php echo $this->sort ( 'order_email', 'COM_VIRTUEMART_EMAIL' )  ?></th>
			<th>Shipping Method</th>
			<th><?php echo $this->sort ( 'created_on', 'COM_VIRTUEMART_ORDER_CDATE' )  ?></th>
			<th><?php echo $this->sort ( 'modified_on', 'COM_VIRTUEMART_ORDER_LIST_MDATE' )  ?></th>
			<th><?php echo $this->sort ( 'virtuemart_order_id', 'COM_VIRTUEMART_ORDER_LIST_ID' )  ?></th>
		</tr>
		</thead>
		<tbody>
		<?php
		if ( count( $this->orderslist ) > 0 ) {
			$i = 0;
			$k = 0;
			$keyword = JRequest::getWord ( 'keyword' );

			foreach ( $this->orderslist as $key => $order ) {
		?>
			<tr class="row<?php echo $k; ?>">
				<?php
					$link = 'index.php?option=com_virtuemart&view=mds&task=edit&virtuemart_order_id=' . $order->virtuemart_order_id;
				?>
				<td><?php echo JHTML::_( 'link', JRoute::_( $link, false ), $order->order_number, array( 'title' => 'Change Shipping Details' ) ); ?></td>
				<td><?php echo $order->order_name;?></td>
				<td><?php echo $order->order_email;?></td>
				<td><?php echo $order->mds_service; ?></td>
				<td><?php echo vmJsApi::date( $order->created_on, 'LC2', true ); ?></td>
				<td><?php echo vmJsApi::date( $order->modified_on, 'LC2', true ); ?></td>
				<td><?php echo JHTML::_( 'link', JRoute::_( $link, false ), $order->virtuemart_order_id, array( 'title' => JText::_( 'COM_VIRTUEMART_ORDER_EDIT_ORDER_ID' ) . ' ' . $order->virtuemart_order_id ) ); ?></td>

			</tr>
				<?php
				$k = 1 - $k;
				$i++;
			}
		}
		?>
		</tbody>
	</table>
	<!-- Hidden Fields -->
	<?php echo $this->addStandardHiddenToForm(); ?>
</form>
<?php AdminUIHelper::endAdminArea(); ?>
