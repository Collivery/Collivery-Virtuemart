<?php

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
AdminUIHelper::startAdminArea ( $this );
?>

<form method="post" action="<?php echo JRoute::_( 'index.php?option=com_virtuemart&view=mds&task=index', false ); ?>">
	<div id="header">
		<div id="filterbox">
			<table>
				<tr>
					<td align="left" width="100%">
						<label for="waybill">Filter Waybill:</label>
						<input type="text" name="waybill" size="11"/>
						<label for="status"> Filter Status:</label>
						( Open <input type="radio" name="status" checked="checked" value="1"/> | Closed <input type="radio" name="status" value="0"/> )
						<input type="submit" value="Search"/>
					</td>
				</tr>
			</table>
		</div>
		<div id="resultscounter"><?php echo $this->pagination->getResultsCounter (); ?></div>
	</div>
	<table class="adminlist table table-striped ui-sortable" cellspacing="0" cellpadding="0">
		<thead>
		<tr>
			<th>Waybill Number</th>
			<th>Shipping Method</th>
			<th>Order Date</th>
		</tr>
		</thead>
		<tbody>
		<?php
		if ( count( $this->orderslist ) > 0 ) {
			$i = 0;
			$k = 0;
			$keyword = JRequest::getWord ( 'keyword' );

			foreach ( $this->orderslist as $key => $order ) {
				$validation_results = json_decode( $order->validation_results );
				?>
				<tr class="row<?php echo $k; ?>">
				<?php
					$link = 'index.php?option=com_virtuemart&view=mds&task=view&waybill=' . $order->waybill;
				?>
				<td><?php echo JHTML::_( 'link', JRoute::_( $link, false ), $order->waybill, array( 'title' => 'View the delivery' ) ); ?></td>
				<td><?php echo $this->services[$validation_results->service]; ?></td>
				<td><?php echo date( "Y-m-d H:m", $validation_results->collection_time); ?></td>
			</tr>
				<?php
				$k = 1 - $k;
				$i++;
			}
		}
		?>
		</tbody>
	</table>
</form>
<?php AdminUIHelper::endAdminArea (); ?>
