<?php
// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<h1>MDS Collivery: Tracking</h1>
<form method="post" name="mds_tracking" style="font-family: arial; color: rgb(119, 119, 119);" action="<?php echo JRoute::_( 'index.php/component/virtuemart/mds' ); ?>">
	<p>Here you will be able to track your delivery. Just enter your Waybill and submit.</p>
	<p>
		<label for="waybill">Waybill Number:</label>
		<input type="text" size="11" name="waybill" value="<?php if ( isset( $this->waybill ) ) {echo $this->waybill;}?>" style="-moz-border-radius: 5px 5px 5px 5px; border: 1px solid rgb(153, 153, 153); padding: 5px;"/>
	</p>
	<p><input type="submit" value="Check Status" style="-moz-border-radius: 5px 5px 5px 5px; border: 1px solid rgb(153, 153, 153); padding: 5px;"/></p>
</form>

<?php if ( isset( $this->error ) ):?>
<div id="mds_tracking_wrapper" style="padding-top: 10px;">
	<p style="color:red;"><?php echo $this->error;?></p>
</div>
<?php endif;?>

<?php if ( isset( $this->tracking ) ):?>
<div id="mds_tracking_wrapper" style="padding-top: 10px;">
	<p>Collivery <?php echo $this->waybill;?> Status is : <?php echo $this->tracking['status_text'];?></p>
	<p>
		<?php
			if ( isset( $this->tracking['delivered_at'] ) ) {
				echo 'Delivered at '.date( "H:i:s", strtotime( $this->tracking['delivered_at'] ) ).' on the '.date( "d/M/Y", strtotime( $this->tracking['delivered_at'] ) );
			} else {
				if ( isset( $this->tracking['eta'] ) ) {
					echo 'Estimated time of delivery: '.date( "H:i:s", $this->tracking['eta'] ).' on the '.date( "d/M/Y", $this->tracking['eta'] );
				} else {
					echo 'Delivery will be before '.$this->tracking['delivery_time'].' on the '.date( "d/M/Y", strtotime( $this->tracking['delivery_date'] ) );
				}
			}
		?>
	</p>
</div>
<?php endif;?>
