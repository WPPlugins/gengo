<?php
if (!current_user_can('configure_gengo')) {
	die( __('You do not have sufficient permissions to access this page.') );
}

if ($_POST['gengo_synonyms_submit'] && !empty($_POST['update_synonyms'])) {
	foreach ($_POST['update_synonyms'] as $term_id) {
		foreach ($gengo->languages as $language_id => $entry) {
			$synonym = $_POST[$term_id . '_' . $language_id];
			$sanitised = sanitize_title($synonym);
			$wpdb->query("UPDATE $gengo->term2syn_table SET synonym = '$synonym', sanitised = '$sanitised' WHERE term_id = $term_id AND language_id = $language_id");
		}
	}
	$gengo->update_message(__("Category translations updated.", GENGO_DOMAIN));
}
elseif ($_POST['gengo_synblock_submit']) {
	if (-1 == $_POST['gengo_synblock_name']) {
		if (!$block_name = $_POST['gengo_new_synblock']) {
			$gengo->error_message(__("Please specify a name for the new snippet.", GENGO_DOMAIN));
			$synblock_failed = true;
		} elseif ($block_name == $wpdb->get_var("SELECT block_name FROM $gengo->synblock_table WHERE block_name = '$block_name' LIMIT 1")) {
			$gengo->error_message(sprintf(__("Snippet '%s' already exists, please choose another name.", GENGO_DOMAIN), $block_name));
			$synblock_failed = true;
		} else {
			foreach ($gengo->languages as $language_id => $entry) $values[] = "('$block_name', $language_id, '" . str_replace("\n", '<br />', $_POST['synblock_' . $language_id]) . "')";
			if ($values) $wpdb->query("INSERT INTO $gengo->synblock_table(block_name, language_id, text) VALUES " . implode(', ', $values));
			$gengo->update_message(sprintf(__("Snippet '%s' created.", GENGO_DOMAIN), $block_name));
		}
	} else {
		$block_name = $_POST['gengo_synblock_name'];
		foreach ($gengo->languages as $language_id => $entry) {
			$text = str_replace("\n", '<br />', $_POST['synblock_' . $language_id]);
			$wpdb->query("UPDATE $gengo->synblock_table SET text = '$text' WHERE block_name = '$block_name' AND language_id = $language_id");
		}
		$gengo->update_message(sprintf(__("Snippet '%s' updated.", GENGO_DOMAIN), $block_name));
	}
}
elseif ($_POST['gengo_delete_synblock']) {
  $block_name = $_POST['gengo_synblock_name'];
	if (!$affected = $wpdb->query("DELETE FROM $gengo->synblock_table WHERE block_name = '$block_name'")) $gengo->error_message(sprintf(__("Synonym block '%s' does not exist.", GENGO_DOMAIN), $block_name));
	else $gengo->update_message(sprintf(__("Snippet '%s' deleted.", GENGO_DOMAIN), $block_name));
}
?>

<?php
$flag1 = $flag2 = $flag3 = "";
$show = $_REQUEST['show'];	
switch($show):
	case "snippets":			$flag3=' class="current"'; break;
	case "tags":				$flag2=' class="current"'; $taxonomy = 'post_tag'; break;
	case "categories": case "":	$flag1=' class="current"'; $taxonomy='category'; break;
endswitch;

if($show=="tags" || $show=="categories" || $show==""): ?>

<div class="wrap">
<h2><?php _e("Taxonomy Translations", GENGO_DOMAIN); ?></h2>
<ul class="subsubsub">
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=categories'<?php echo $flag1;?>><?php _e('Categories', GENGO_DOMAIN) ?></a> |</li>
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=tags'<?php echo $flag2;?>><?php _e('Tags', GENGO_DOMAIN) ?></a> |</li>
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=snippets'<?php echo $flag3;?>><?php _e('Snippets', GENGO_DOMAIN) ?></a></li>
</ul>

<p><?php _e("Add translations for taxonomy terms here to translate your categories, tags and permalinks.  The underlying term will be used for display and permalink purposes if there is no synonym specified for a language.  Editing the underlying term does not alter these translations.", GENGO_DOMAIN); ?></p>
<?php
if ($results = $wpdb->get_results("SELECT ts.term_id, ts.language_id, ts.synonym FROM $gengo->term2syn_table AS ts LEFT JOIN $wpdb->term_taxonomy AS tt ON ts.term_id = tt.term_id WHERE tt.taxonomy = '$taxonomy' ORDER BY term_id, language_id")) {
	?>
	<form id="gengo_synonyms_form" method="post">
	<div class="extender" style="width:100%;overflow:auto;">
	<table class="widefat" id="gengo_synonyms_table">
	<thead>
	<tr>
		<th scope="col">&nbsp;</th>
		<th scope="col">ID</th>
		<?php
		foreach ($gengo->languages as $entry) {
			?>
			<th scope="col"><?= $entry->language ?></th>
			<?php
		}
		?>
	</tr>
	</thead>
	<?php
	$num = count($results);
	$dimtext = array(1=>"",2=>"",3=>"",4=>"11px",5=>"11px");
	foreach ($results as $result) {
		if ($previous_id != $result->term_id) {
			// Starting a new row.
			if ($previous_id) { ?></tr><?php }
			?>
			<tr>
			<th scope="row" class="check-column"><input type="checkbox" name="update_synonyms[]" id="check_<?= $result->term_id ?>" value="<?= $result->term_id ?>" /></td>
			<td><?= $result->term_id ?></td>
			<?php
		}
		?>
		<td><input  style="font-size:11px;margin:0px;padding:3px;" type="text" name="<?= $result->term_id . "_" . $result->language_id ?>" value="<?= $result->synonym ?>" onkeydown="gengo_set_synonym_checkbox(<?= $result->term_id ?>)" /></td>
		<?php
		$previous_id = $result->term_id;
	}
	?>
	</tr>
	</table>
	</div>
	<p class="submit">
	<input type="submit" id="gengo_synonyms_submit" name="gengo_synonyms_submit" value="<?php _e('Update Checked', GENGO_DOMAIN) ?>" />
	</p>
	</form>
	</div>
	<div class="wrap"><p><strong><?php _e('Note:', GENGO_DOMAIN) ?></strong> <?php _e('These are only translations.  Changing these values will <strong>not</strong> alter the underlying term.', GENGO_DOMAIN) ?></p>
	<?php
} else {
	?>
	<p><?php _e('No languages defined yet.', GENGO_DOMAIN) ?></p>
	<?php
}
?>
</div>

<?php elseif($show=="snippets"): ?>

<?php
if ($count = count($gengo->languages)) {
	$block_names = $wpdb->get_col("SELECT DISTINCT block_name FROM $gengo->synblock_table");
	$block_list = '<option value="-1">' . __('(Add New Snippet)', GENGO_DOMAIN) . '</option>';
	foreach ($block_names as $block_name) $block_list .= "<option value=\"$block_name\">$block_name</option>";
?>
<script type="text/javascript">
var add_button_text = '<?php _e('Add Snippet', GENGO_DOMAIN) ?>';
var update_button_text = '<?php _e('Update Snippet', GENGO_DOMAIN) ?>';
</script>
<div class="wrap" id="gengo_snippets">
<h2><?php _e('Taxonomy Translations', GENGO_DOMAIN); ?></h2>
<ul class="subsubsub">
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=categories'<?php echo $flag1;?>><?php _e('Categories', GENGO_DOMAIN) ?></a> |</li>
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=tags'<?php echo $flag2;?>><?php _e('Tags', GENGO_DOMAIN) ?></a> |</li>
	<li><a href='admin.php?page=gengo/gengo_synonyms_page.php&amp;show=snippets'<?php echo $flag3;?>><?php _e('Snippets', GENGO_DOMAIN) ?></a></li>
</ul>
<p><?php _e("Snippets are small blocks of translated text for use throughout your site.  You can display these blocks anywhere by using <code>gengo_snippet('snippet_name')</code> in your template.", GENGO_DOMAIN); ?></p>
<form id="gengo_synblocks_form" method="post">
<p style="float: left"><label for="gengo_synblock_name"><?php _e('Snippet Name:', GENGO_DOMAIN) ?></label><br />
<select id="gengo_synblock_name" name="gengo_synblock_name" onchange="gengo_get_synblock(this.value);"><?= $block_list ?></select></p>
<p style="float: left; margin-left: 50px" id="gengo_new_synblock_block"><label for="gengo_new_synblock"><?php _e('New Snippet Name:', GENGO_DOMAIN) ?></label><br />
<input type="text" id="gengo_new_synblock" name="gengo_new_synblock" /></p>
<?php
foreach ($gengo->languages as $language_id => $entry) {
	?>
	<p style="clear: both"><label for="synblock_<?= $language_id ?>"><?= $entry->language ?>:<br />
	<textarea id="synblock_<?= $language_id ?>" name="synblock_<?= $language_id ?>" style="width: 75%"><?php if ($synblock_failed) echo $_POST['synblock_' . $language_id]; ?></textarea></p>
	<?php
}
?>
<p class="submit">
<input style="float: left; display: none;" type="submit" id="deletepost" name="gengo_delete_synblock" value="<?php _e('Delete Snippet', GENGO_DOMAIN) ?>" onclick="return confirm('<?php _e("You are about to delete this snippet.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN) ?>')" />
<input type="submit" name="gengo_synblock_submit" id="gengo_synblock_submit" value="<?php _e('Add Snippet', GENGO_DOMAIN) ?>" />
</p>
</form>
<?php
} else {
	?>
	<p><?php _e('No languages defined yet.', GENGO_DOMAIN) ?></p>
	<?php
}
?>
</div>

<?php endif; ?>