<?php
if (!current_user_can('modify_gengo_exclusions')) {
	die( __('You do not have sufficient permissions to access this page.') );
}

if (isset($_POST['gengo_exclusions_submit'])) {
	$sanitised_exclusions = array();
	$exclusions = explode("\n", $_POST['gengo_exclusions']);
	foreach ($exclusions as $exclusion) {
		if (($x = trim($exclusion)) && ('/' != $x)) $sanitised_exclusions[] = $x;
	}
	update_option('gengo_url_exclusions', implode("\n", $sanitised_exclusions));
	$gengo->update_message(__("URL exclusions updated.", GENGO_DOMAIN));
}
?>
<div class="wrap">
<h2><?php _e('URL Exclusions', GENGO_DOMAIN); ?></h2>
<p><?php _e("Language codes are not appended to URLs that match the default filters.  You may add your own URL filters below to prevent conflicts with other plugins.  Put each filter on a new line.", GENGO_DOMAIN); ?></p>
<form id="gengo_exclusions_form" method="post">
<p><label><?php _e('Default Filters:', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_default_exclusions" value="<?php foreach ($gengo->default_url_exclusions as $exclusion) echo "$exclusion "; ?>" style="width: 75%" disabled="disabled" /></p>
<p>
<label for="gengo_exclusions"><?php _e('User Defined Filters:', GENGO_DOMAIN) ?></label><br />
<textarea name="gengo_exclusions" style="width: 75%; height: 150px"><?php echo get_option('gengo_url_exclusions') ?></textarea>
</p>

<p class="submit">
<input type="submit" id="gengo_exclusions_submit" name="gengo_exclusions_submit" value="<?php _e('Update Exclusions', GENGO_DOMAIN) ?>" />
</p>
</form>
</div>