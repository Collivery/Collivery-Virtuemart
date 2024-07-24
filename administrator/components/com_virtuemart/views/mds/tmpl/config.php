<?php
// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );
AdminUIHelper::startAdminArea( $this );
?>
<h1>MDS Collivery Configuration</h1>

<div class="parallel">
	<fieldset class="parallel_target">
		<legend>Config:</legend>
		<p>These are your MDS Collivery account login details.</p>
		<div id="mds_config">
			<form method="post" name="mds_tracking" action="<?php echo JRoute::_( 'index.php?option=com_virtuemart&view=mds&task=config', FALSE ); ?>">
				<script src="js/mds_collivery.js" type="text/javascript"></script>
				<label for="username">Username:</label>
				<input name="username" id="username" value="<?php echo $this->config->username;?>" size="30">
				<br />
				<label for="password">Password:</label>
				<input name="password" id="password" value="<?php echo $this->config->password;?>" size="30">
				<br />
				<label for="risk_cover">Insurance up to R5000:</label>
				( Include <input type="radio" name="risk_cover" id="risk_cover" value="1" <?php if($this->config->risk_cover == 1) {echo 'checked="checked" ';}?>> | Don't Include <input type="radio" name="risk_cover" value="0"<?php if($this->config->risk_cover == 0) {echo 'checked="checked" ';}?>> )
				<p><input type="submit" value="Update"/></p>
			</form>
		</div>
	</fieldset>

	<fieldset class="parallel_target">
		<legend>Update:</legend>
		<p>Update all towns, location types and services. Update should be performed at least once a month.</p>
		<ul id="top_menu">
			<li><button type="button" id="update">Update Plugin</button></li>
		</ul>
	</fieldset>
</div>

<div id="api_results"></div>

<?php
AdminUIHelper::imitateTabs( 'end' );
AdminUIHelper::endAdminArea();
?>
