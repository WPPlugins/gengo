<?php

if (!$gengo->is_installed()) $gengo->install(get_option('gengo_version'));

if (!current_user_can('configure_gengo')) {
	die( __('You do not have sufficient permissions to access this page.') );
}

// Add a new language to the language list.
function gengo_add_language() {
	global $wpdb, $wp_rewrite, $gengo;

	if (!$language = $_POST['gengo_language']) return $gengo->error_message(__("Please enter a name for the language.", GENGO_DOMAIN));
	elseif (!$code = $_POST['gengo_language_code']) return $gengo->error_message(__("Please enter a two letter code for the language.", GENGO_DOMAIN));
	elseif (!$locale = $_POST['gengo_language_locale']) return $gengo->error_message(__("Please enter a locale for the language.", GENGO_DOMAIN));

	foreach ($gengo->languages as $entry) if ($entry->code == $code) return $gengo->error_message(sprintf(__("Duplicate language code '%s' could not be added.", GENGO_DOMAIN), $code));

	$rtl = isset($_POST['gengo_language_rtl']) ? '1' : '0';
	$charset = $_POST['gengo_language_charset'];
	
	// Cleanup title for low-privilege users.
	if (($blog_title = $_POST['gengo_language_blog_title']) && !current_user_can('unfiltered_html')) $blog_title = wp_filter_post_kses($blog_title);
	if (($blog_tagline = $_POST['gengo_language_blog_tagline']) && !current_user_can('unfiltered_html')) $blog_tagline = wp_filter_post_kses($blog_tagline);

	// Insert language and language code into the db.
	$wpdb->query("INSERT INTO $gengo->language_table(language, code, locale, rtl, charset) VALUES ('$language', '$code', '$locale', '$rtl', '$charset')");
	$language_id = $wpdb->get_var("SELECT DISTINCT LAST_INSERT_ID() FROM $gengo->language_table;");

	$synblock_values = "('blogtagline', $language_id, '$blog_tagline'), ('blogtitle', $language_id, '$blog_title')";
	if ($synblocks = $wpdb->get_col("SELECT DISTINCT block_name FROM $gengo->synblock_table WHERE block_name != 'blogtagline' AND block_name != 'blogtitle'")) {
		$synblock_values .= ", ('" . implode ("', $language_id, ''), ('", $synblocks) . "', $language_id, '')";
	}
	$wpdb->query("INSERT INTO $gengo->synblock_table(block_name, language_id, text) VALUES $synblock_values");

	$gengo->set_defined_languages();
	$wp_rewrite->flush_rules();

	$gengo->update_message(sprintf(__("Language '%s' Added. Rewrite rules updated.", GENGO_DOMAIN), $language));

	if(1 == count($gengo->languages)) {
		// Save the first language as the default and set all posts and categories to be this language.
		$gengo->save_blog_default_language($language_id);
		$gengo->set_no_lang_posts_default(true);
		$gengo->set_wplang($locale);
		$wpdb->query("INSERT INTO $gengo->term2syn_table(term_id, language_id, synonym, sanitised) SELECT term_id, $language_id, name, slug FROM $wpdb->terms");
		$gengo->update_message(sprintf(__("All posts and categories set to the default language '%s'.", GENGO_DOMAIN), $language));
	} else {
		$wpdb->query("INSERT INTO $gengo->term2syn_table(term_id, language_id, synonym, sanitised) SELECT term_id, $language_id, '', '' FROM $wpdb->terms");
	}
	return true;
}

// Edit a language already in the language list.
function gengo_edit_language() {
	global $wpdb, $wp_rewrite, $gengo;

	if (!$language = $_POST['gengo_language']) return $gengo->error_message(__("Please enter a name for the language.", GENGO_DOMAIN));
	elseif (!$code = $_POST['gengo_language_code']) return $gengo->error_message(__("Please enter a two letter code for the language.", GENGO_DOMAIN));
	elseif (!$locale = $_POST['gengo_language_locale']) return $gengo->error_message(__("Please enter a locale for the language.", GENGO_DOMAIN));

	$id = $_POST['gengo_language_id'];
	$rtl = isset($_POST['gengo_language_rtl']) ? '1' : '0';
	$charset = $_POST['gengo_language_charset'];

	// Cleanup title for low-privilege users.
	if (($blog_title = $_POST['gengo_language_blog_title']) && !current_user_can('unfiltered_html')) $blog_title = wp_filter_post_kses($blog_title);
	if (($blog_tagline = $_POST['gengo_language_blog_tagline']) && !current_user_can('unfiltered_html')) $blog_tagline = wp_filter_post_kses($blog_tagline);
	
	foreach ($gengo->languages as $language_id => $entry) {
		if ($entry->code == $code) {
			if ($language_id != $id) return $gengo->error_message(sprintf(__("Language code '%s' already exists, please choose another.", GENGO_DOMAIN), $code));
			$existing_code_id = $language_id;
			break;
		}
	}
	$wpdb->query("UPDATE $gengo->language_table SET language = '$language', code = '$code', locale = '$locale', rtl = '$rtl', charset = '$charset' WHERE language_id = $id");
	$wpdb->query("UPDATE $gengo->synblock_table SET text = '$blog_title' WHERE block_name = 'blogtitle' AND language_id = $id");
	$wpdb->query("UPDATE $gengo->synblock_table SET text = '$blog_tagline' WHERE block_name = 'blogtagline' AND language_id = $id");
	$gengo->set_defined_languages();
	if ($existing_code_id != $id) {
		$wp_rewrite->flush_rules();
		$rewrite_message = __('  Rewrite rules updated.', GENGO_DOMAIN);
	}
	return $gengo->update_message(sprintf(__("Language updated: '%s' with code '%s' and locale '%s'.%s", GENGO_DOMAIN), $language, $code, $locale, $rewrite_message));
}

// Delete a language already in the language list.
function gengo_delete_language($language_id) {
	global $wpdb, $wp_rewrite, $gengo;
	
	$default_language_id = $gengo->blog_default_language_id;
	$deleted_language = $gengo->languages[$language_id]->language;
	if ($language_id == $default_language_id) return $gengo->error_message(sprintf(__("You cannot delete the default language.  If you want to delete '%s', set another language as the default first.  If you want to delete all language data, run the uninstaller.", GENGO_DOMAIN), $gengo->languages[$language_id]->language));

	$deleted = $wpdb->query("DELETE FROM $gengo->language_table WHERE language_id = $language_id");
	if (!$deleted) return $gengo->error_message(__("The specified language does not exist.  It may already have been deleted.", GENGO_DOMAIN));

	$posts_affected = $wpdb->query("UPDATE $gengo->post2lang_table SET language_id = $default_language_id, translation_group = 0 WHERE language_id = $language_id");

	if ($orphaned_translations = $wpdb->get_col("SELECT translation_group FROM $gengo->post2lang_table GROUP BY translation_group HAVING 1 = COUNT(*)")) {
	  $orphaned_groups = implode(',', $orphaned_translations);
		$wpdb->query("UPDATE $gengo->post2lang_table SET translation_group = 0 WHERE translation_group IN ($orphaned_groups)");
	}
	$summaries_affected = $wpdb->query("DELETE FROM $gengo->summary_table WHERE language_id = $language_id");
	$wpdb->query("UPDATE $gengo->post2lang_table AS p2l INNER JOIN $gengo->summary_table AS s ON p2l.summary_group = s.summary_group SET p2l.summary_group = 0 WHERE s.summary_group IS NULL");
	$users_affected = $wpdb->query("UPDATE $wpdb->usermeta SET meta_value = $default_language_id WHERE meta_key = 'gengo_default_language' AND meta_value = $language_id");
	$gengo->set_defined_languages();
	$wp_rewrite->flush_rules();

	$wpdb->query("DELETE FROM $gengo->term2syn_table WHERE language_id = $language_id");
	$wpdb->query("DELETE FROM $gengo->synblock_table WHERE language_id = $language_id");

	return $gengo->update_message(sprintf(__("Language '%s' removed. %d summar(ies) removed.  %d post(s) and %d user(s) reset to the blog default language, '%s'.  Rewrite rules updated.", GENGO_DOMAIN), $deleted_language, $summaries_affected, posts_affected, $users_affected, $gengo->languages[$default_language_id]->language));
}

// Handle Actions.
if (isset($_POST['gengo_set_blog_default_submit']) && current_user_can('set_blog_default_language')) {
	$gengo->save_blog_default_language($_POST['gengo_blog_default_language']);
}	elseif (isset($_POST['gengo_update_language_submit'])) {
	$gengo_editing_language = $add_edit_language_failed = !gengo_edit_language();
} elseif (isset($_POST['gengo_add_language_submit'])) {
	$add_edit_language_failed = !gengo_add_language();
} elseif ($_GET['gengo_action'] == "cancel") {
} elseif ($_GET['gengo_action'] == "edit_language") {
	$gengo_editing_language = true;
	$language_id = $_GET['language_id'];
} elseif (($_GET['gengo_action'] == "delete_language") && current_user_can('delete_languages')) {
	gengo_delete_language($_GET['language_id']);
} elseif (isset($_POST['gengo_language_settings_submit']) && current_user_can('modify_gengo_settings')) {
  $append = isset($_POST['gengo_append_urls']) ? '1' : '0';
	update_option('gengo_append_urls', $append);
	$gengo->append_urls = $append;
  $multiread = isset($_POST['gengo_allow_multiread']) ? '1' : '0';
	update_option('gengo_allow_multiread', $multiread);
	if ('MANUAL' == $_POST['gengo_default_reading_choice']) {
		update_option('gengo_default_reading_language', $_POST['gengo_default_reading_language']);
	} else {
		update_option('gengo_default_reading_language', '0');
	}
	$gengo->update_message(__('Settings updated.', GENGO_DOMAIN));
}

if ($gengo_editing_language && !$add_edit_language_failed) {
	$gengo_language = $gengo->languages[$language_id]->language;
	$gengo_code = $gengo->languages[$language_id]->code;
	$gengo_locale = $gengo->languages[$language_id]->locale;
	$gengo_charset = $gengo->languages[$language_id]->charset;
	$gengo_rtl = $gengo->languages[$language_id]->rtl;
	$gengo_blog_title = $gengo->get_synblock('blogtitle', $language_id);
	$gengo_blog_tagline = $gengo->get_synblock('blogtagline', $language_id);
} elseif ($add_edit_language_failed) {
	$gengo_language = $_POST['gengo_language'];
	$gengo_code = $_POST['gengo_language_code'];
	$gengo_locale = $_POST['gengo_language_locale'];
	$gengo_rtl = isset($_POST['gengo_language_rtl']) ? '1' : '0';
	$gengo_blog_title = $_POST['gengo_language_blog_title'];
	$gengo_blog_tagline = $_POST['gengo_language_blog_tagline'];
}

if ($language_count = count($gengo->languages)) {
	$default_language_id = $gengo->blog_default_language_id;
	if (!WPLANG && !$gengo->wplang) {
		$gengo->error_message(sprintf(__("WPLANG has not been set and could not be set automatically.  This can cause problems with your blog.  Please edit wp-config.php and change WPLANG to your default locale, '%s'.", GENGO_DOMAIN), $gengo->languages[$default_language_id]->locale));
	}
}

// Output Gengo's Language Management Page.
if (!$gengo_editing_language) {
	?>
	<div class="wrap">
	<h2><?php _e('Languages', GENGO_DOMAIN); ?></h2>
	<ul class="subsubsub">
	<li><a href='admin.php?page=gengo/gengo_languages_page.php' class="current"><?php _e('All languages', GENGO_DOMAIN) ?></a> |</li>
	<li><a href='#gengo_add_edit_language' ><?php _e('Add new', GENGO_DOMAIN) ?></a></li>
	</ul>
	<?php
	if ($language_count) {
		?>
		<p><?php _e('The following languages are defined for this blog:', GENGO_DOMAIN); ?></p>
			<!-- div class="tablenav">
				<div class="tablenav-pages">y</div>
				<div class="alignleft">x</div>
			</div -->
		<table class="widefat" id="the-list-x">
		<thead>
		  <tr>
			<th scope="col"><?php _e('ID', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Language', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Code', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Locale', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Charset', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Direction', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Posts', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Pages', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Blog Title', GENGO_DOMAIN) ?></th>
			<th scope="col"><?php _e('Tagline', GENGO_DOMAIN) ?></th>
			<th scope="col">&nbsp;</th>
		  </tr>
		</thead>
		<tbody>
		<?php
		// Show a list of defined languages.
		$i = 0;
		foreach($gengo->languages as $language_id => $entry) {
			$default_list .= ($default_language_id == $language_id) ? "<option value=\"$language_id\" selected=\"selected\">$entry->language</option>" : "<option value=\"$language_id\">$entry->language</option>";
			$blog_title = $gengo->get_synblock('blogtitle', $language_id);
			$blog_tagline = $gengo->get_synblock('blogtagline', $language_id);
			$total_post = $gengo->get_totals('post');
			$total_page = $gengo->get_totals('page');
			$tpost = empty($total_post[$language_id]) ? '0' : $total_post[$language_id];
			$tpage = empty($total_page[$language_id]) ? '0' : $total_page[$language_id];
			
			?>
			<tr<?php echo (++$i % 2) ? ' class="alternate"' : ""; ?>>
				<th scope="row"><?php echo $language_id; ?></th>
				<td><a href="<?php echo add_query_arg(array('gengo_action' => 'edit_language', 'language_id' => $language_id)); ?>" class="edit"><?php echo $entry->language; ?></a>
				</td>
				<td><?php echo $entry->code ?></td>
				<td><?php echo $entry->locale ?></td>
				<td><?php echo $entry->charset ?></td>
				<td><?php echo ($entry->rtl) ? 'RTL' : 'LTR' ?></td>
				<td><?php echo $tpost; ?></td>
				<td><?php echo $tpage; ?></td>
				<td><?php echo $blog_title ?></td>
				<td><?php echo $blog_tagline ?></td>
				<td>
					<?php 
					if ($default_language_id != $language_id) { 
						if (current_user_can('delete_languages')) {								
							?><a href="<?php echo add_query_arg(array('gengo_action' => 'delete_language', 'language_id' => $language_id)); ?>" class="delete" onclick="return confirm('<?php printf(__("You are about to delete the language \'%s\'.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN), $entry->language) ?>');"><?php _e('Delete', GENGO_DOMAIN) ?></a><?php 
						} else {
							?>&nbsp;<?php
						}
					} else { 
						?><strong><?php _e('Default', GENGO_DOMAIN); ?></strong><?php
					}
					?>
				</td>
			</tr>
			<?php
		}
		?>
		</tbody>
		</table>
		
		<?php
		if (current_user_can('modify_gengo_settings')) {
			?>
			<form name="gengo_set_blog_default_form" method="post">
			<p><label for="gengo_append_urls"><input type="checkbox" id="gengo_append_urls" name="gengo_append_urls"<?php if (get_option('gengo_append_urls')) echo ' checked="checked"' ?> /> <?php _e('Gengo should append language codes to permalinks automatically.', GENGO_DOMAIN) ?></label></p>
			<?php
			if ('x' != ($allow_multiread = get_option('gengo_allow_multiread'))) {
				?>
				<p><label for="gengo_allow_multiread"><input type="checkbox" id="gengo_allow_multiread" name="gengo_allow_multiread"<?php if ($allow_multiread) echo ' checked="checked"' ?> /> <?php _e('Allow visitors to read this site in a combination of languages.', GENGO_DOMAIN) ?></label></p>
				<?php
			}
			$default_reading_language = get_option('gengo_default_reading_language');
			?>
			<p><?php _e('When reader visits for the first time:', GENGO_DOMAIN) ?></p>
			<p><label for="gengo_reading_automatic"><input type="radio" id="gengo_reading_automatic" value="AUTO" onclick="this.form.gengo_default_reading_language.disabled = true;" name="gengo_default_reading_choice"<?php if (!$default_reading_language) echo ' checked="checked"'; ?> /> <?php _e('Let Gengo choose the best languages to display.', GENGO_DOMAIN) ?></label></p>
			<p><label for="gengo_reading_manual"><input type="radio" id="gengo_reading_manual" value="MANUAL" onclick="this.form.gengo_default_reading_language.disabled = false;" name="gengo_default_reading_choice"<?php if ($default_reading_language) echo ' checked="checked"'; ?> /> <?php _e('Show posts in ', GENGO_DOMAIN) ?>
			<select name="gengo_default_reading_language"<?php if (!$default_reading_language) echo ' disabled="disabled"' ?>>
			<?php
			reset($gengo->languages);
			foreach ($gengo->languages as $language_id => $entry) {
				$selected = ($language_id == $default_reading_language) ? ' selected="selected"' : '';
			  ?>
			  <option value="<?php echo $language_id ?>"<?php echo $selected ?>><?php echo $entry->language ?></option>
			  <?php
			}
			if ($language_count > 1) {
			  ?>
			  <option value="-1"<?php if ('-1' == $default_reading_language) echo ' selected="selected"'; ?>><?php _e('all defined languages', GENGO_DOMAIN) ?></option>
			  <?php
			}
			?>
			</select></label></p>
			<p class="submit"><input type="submit" value="<?php _e('Update Settings', GENGO_DOMAIN) ?>" name="gengo_language_settings_submit" /></p>
			</form>
			<?php
		}
	}
	else { ?><p><?php _e('No languages defined yet.', GENGO_DOMAIN) ?></p><?php } ?>
	</div>
	<?php
	if (current_user_can('delete_languages') && count($gengo->languages) > 1) {
		?>
		<div class="wrap"><p><strong><?php _e('Note:', GENGO_DOMAIN) ?></strong> <?php _e('Deleting a language will return affected posts and users to the default language and remove associated translation and synonym entries.  Deleting a language does <strong>not</strong> delete posts in that language.', GENGO_DOMAIN) ?></p></div>
		<?php
	}
	$gengo_add_edit_title = __("Add New Language", GENGO_DOMAIN);
	$gengo_button_title = __("Add Language", GENGO_DOMAIN);
} else {
	$cancel_link = '' ;
	$gengo_add_edit_title = __('Edit Language', GENGO_DOMAIN) . ' (<a href="admin.php?page=' . GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE . '">' . __('cancel', GENGO_DOMAIN) . '</a>)';
	$gengo_button_title = __("Update Language", GENGO_DOMAIN);
}
if (!$language_count) {
	?><div class="wrap"><p><strong><?php _e('Note:', GENGO_DOMAIN) ?></strong> <?php _e("You should now add your first language.  All existing posts and pages will automatically be set to this language, so be sure to choose the language that represents the majority of your existing content.", GENGO_DOMAIN) ?></p></div><?php
}
?>
<br style="clear:both;" />
<div class="wrap" id="gengo_add_edit_language">
<h2><?php echo $gengo_add_edit_title ?></h2>
<form name="gengo_languages_form" method="post">
<?php
if ($gengo_editing_language) {
	$gengo_submit_mode = "gengo_update_language_submit";
	?><input type="hidden" name="gengo_language_id" value="<?php echo $language_id ?>" /><?php
} else {
	$gengo_submit_mode = "gengo_add_language_submit";
	?><p><?php _e("Add new languages here.  Choose a language from the list to automatically fill in language information, or enter it manually.  The name should be a human-readable description of the language.  The language code is the ISO 639-2 or 639-3 code for the language and will be used for permalinks.  The locale should match a corresponding .mo file to localise WordPress.", GENGO_DOMAIN) ?></p><?php
}
include ABSPATH . GENGO_DIR . 'gengo_languages.php';

if ($gengo_recognised_languages) {
	$gengo_code_list = '<option value="-1" selected="selected">' . __('Select Language', GENGO_DOMAIN) . '</option>';
	foreach ($gengo_recognised_languages as $code => $entry) {
		foreach ($entry as $locale => $language) {
			$gengo_code_list .= "<option value=\"$code-$locale\">$language</option>";
		}
	}
	?>
	<script type="text/javascript">
	var rtl_scripts = new Array (<?php echo "'" . implode("', '", $gengo_rtl_scripts) . "'"; ?>);
	</script>
	<?php
}
if ($gengo->append_urls) {
	$query_arg = (!$wp_rewrite->permalink_structure) ? '?language=' : '/';
	if ($wp_rewrite->using_index_permalinks() || !$wp_rewrite->permalink_structure) {
		$index_permalink = "/$wp_rewrite->index";
	}
	$append = 'true';
} else{
	$index_permalink = '/';
	$append = 'false';
}
$path = trailingslashit(ABSPATH) . 'wp-content/languages/';
if (false !== strpos($path, ':')) $path = str_replace('/', '\\', $path);
?>
<div id="language_box" style="float: right; border: 1px solid #000; padding: 10px; margin: 10px; width: 49%">
<p><strong><span id="language_def"></span></strong></p>
<p><?php echo get_settings('home') . $index_permalink . $query_arg; ?><strong><span id="language_code"></span></strong><?php if ('/' == $query_arg) echo '/'; ?></p>
<p><?php echo $path ?><strong><span id="language_locale"></span></strong>.mo</p>
</div>
<p style="float: left;"><label for="gengo_recognised_languages"><?php _e('Language:', GENGO_DOMAIN) ?><br /><select name="gengo_recognised_languages" id="gengo_recognised_languages" onchange="gengo_select_language(<?= $append ?>)"><?php echo $gengo_code_list ?></select></p>
<p style="clear: left; float: left;"><label for="gengo_language"><?php _e('Name:', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_language" id="gengo_language" value="<?php echo $gengo_language; ?>" onkeyup="gengo_update_definition(<?= $append ?>)" /></p>
<p style="float: left; margin-left: 10px;"><label for="gengo_language_code"><?php _e('Code:', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_language_code" id="gengo_language_code" style="width: 30px;" onkeyup="gengo_update_definition(<?= $append ?>);" value="<?php echo $gengo_code; ?>" /></p>
<p style="float: left; margin-left: 10px;"><label for="gengo_language_locale"><?php _e('Locale:', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_language_locale" id="gengo_language_locale" style="width: 70px;" onkeyup="gengo_update_definition(<?= $append ?>);" value="<?php echo $gengo_locale; ?>" /></p>
<p style="float: left; margin-left: 10px;"><label for="gengo_language_charset"><?php _e('Charset:', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_language_charset" id="gengo_language_charset" style="width: 70px;" value="<?php echo $gengo_charset; ?>" /></p>
<p style="clear: both"><label for="gengo_language_blog_title"><?php _e('Blog Title: (optional)', GENGO_DOMAIN) ?></label><br /><input type="text" name="gengo_language_blog_title" id="gengo_language_blog_title" value="<?php echo $gengo_blog_title; ?>" /></p>
<p><label for="gengo_language_blog_tagline"><?php _e('Tagline: (optional)', GENGO_DOMAIN) ?></label><br /><input id="gengo_language_blog_tagline" name="gengo_language_blog_tagline" type="text" style="width: 75%" value="<?php echo $gengo_blog_tagline; ?>" /></p>
<p><label for="gengo_language_rtl"><input id="gengo_language_rtl" name="gengo_language_rtl" type="checkbox"<?php if ($gengo_rtl) echo ' checked="checked"' ?> onclick="gengo_change_dir()" /> <?php _e('This language is written right to left.', GENGO_DOMAIN) ?></label></p>
<p class="submit"><input type="submit" value="<?php echo $gengo_button_title ?>" name="<?php echo $gengo_submit_mode ?>" /></p>
</form>
</div>
<script type="text/javascript">addLoadEvent(function () { gengo_update_definition(<?= $append ?>) });</script>
<?php
if (!$gengo_editing_language) {
	if (current_user_can('set_blog_default_language')) {
		if (count($gengo->languages) > 1) {
			?>
			<br style="clear:both;" />
			<div class="wrap" id="gengo_set_default">
			<h2><?php _e("Set Blog Default Language", GENGO_DOMAIN); ?></h2>
			<p><?php _e('Users that do not specify a language themselves in their profile will default to using this language.  The blog default language will also be used to choose a reader\'s language on their first visit if their browser doesn\'t specify a preferred one.', GENGO_DOMAIN) ?></p>
			<form name="gengo_set_blog_default_form" method="post">
			<label for="gengo_blog_default_language"><?php _e('Set blog default language:', GENGO_DOMAIN) ?></label>
			<select name="gengo_blog_default_language"><?php echo $default_list; ?></select>
			<p class="submit"><input type="submit" value="<?php _e('Set Blog Default', GENGO_DOMAIN) ?>" name="gengo_set_blog_default_submit" /></p>
			</form>
			</div>
			<?php
		}
	}
	if (current_user_can('uninstall_gengo')) {
		?>
		<br style="clear:both;" />
		<div class="wrap" id="gengo_uninstall">
		<h2><?php _e("Uninstall Gengo", GENGO_DOMAIN); ?></h2>
		<p><strong><?php _e('Warning:', GENGO_DOMAIN) ?></strong> <?php _e('Uninstalling Gengo will remove all language information from the database.  All translation and summary data will be lost and the process is not reversible.  Uninstalling does <strong>not</strong> delete any of your posts, but may cause theme errors if you are using Gengo template functions.', GENGO_DOMAIN) ?></p>
		<form name="gengo_uninstall_form" method="post">
		<p class="submit"><input type="submit" value="<?php _e('Uninstall Gengo', GENGO_DOMAIN) ?>" id="deletepost" name="gengo_uninstall_submit" onclick="return confirm('<?php _e("You are about to uninstall Gengo!  This process is not reversible!\\n\'Cancel\' to stop, \'OK\' to uninstall", GENGO_DOMAIN) ?>')" /></p>
		</form>
		</div>
		<?php
	}
}
?>
<br style="clear:both;" />