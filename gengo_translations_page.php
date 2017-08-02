<?php
if (!current_user_can('configure_gengo')) {
	die( __('You do not have sufficient permissions to access this page.') );
}

// TODO: A link to edit summaries directly.
// TODO: Filter translation groups. Major.  Low Priority.
function gengo_create_translation_group() {
	global $gengo, $wpdb;

	if (is_array($_POST['gengo_summary_ids'])) $summary_ids = implode(',', $_POST['gengo_summary_ids']);
	if ($_POST['gengo_resolved_summary_clashes'] != '') $summary_ids = $summary_ids ? "$summary_ids," . $_POST['gengo_resolved_summary_clashes'] : $_POST['gengo_resolved_summary_clashes'];
	$removed_summary_ids = $_POST['gengo_removed_summary_clashes'];
	$posts = $_POST['gengo_constituent_posts'];

	if (!is_array($posts) || count($posts) < 2) return $gengo->error_message(__("You must specify at least two languages to make a translation group.", GENGO_DOMAIN));

	$post_ids = implode(',', $posts);
	$new_group = $wpdb->get_var("SELECT MAX(translation_group) FROM $gengo->post2lang_table") + 1;
	$wpdb->query("UPDATE $gengo->post2lang_table SET translation_group = $new_group WHERE post_id IN ($post_ids)");

	// Now create a new summary group from the supplied posts and resolved clashes.
	if ($summary_ids) {
		$new_group = $wpdb->get_var("SELECT MAX(summary_group) FROM $gengo->summary_table") + 1;
	  foreach ($posts as $post) $post_strings[] = "($post, $new_group)";
	  $post_string = implode(', ', $post_strings);
		$wpdb->query("UPDATE $gengo->post2lang_table SET summary_group = $new_group WHERE post_id IN ($post_ids)");
		$wpdb->query("UPDATE $gengo->summary_table SET summary_group = $new_group WHERE summary_id IN ($summary_ids)");
		if ($removed_summary_ids) $wpdb->query("DELETE FROM $gengo->summary_table WHERE summary_id IN ($removed_summary_ids)");
	}
	return $gengo->update_message(__("New translation group created.", GENGO_DOMAIN));
}

function gengo_remove_post_from_translation_group($post_id) {
	global $gengo, $wpdb;
	
	if ($group_info = $wpdb->get_row("SELECT translation_group, COUNT(*) AS count FROM $gengo->post2lang_table GROUP BY translation_group HAVING 0 != COUNT(CASE WHEN post_id = $post_id THEN 37337 END)")) {
		if (!$group_info->translation_group) return $gengo->error_message(__("The specified post is not in a translation group.", GENGO_DOMAIN));

		// Either remove the entire group or just this post from the group.
		if ($group_info->count <= 2) $wpdb->query("UPDATE $gengo->post2lang_table SET translation_group = 0 WHERE translation_group = $group_info->translation_group");
		else $wpdb->query("UPDATE $gengo->post2lang_table SET translation_group = 0 WHERE post_id = $post_id");
		
		// Remove the summary links too.
		$wpdb->query("UPDATE $gengo->post2lang_table SET summary_group = 0 WHERE post_id = $post_id");
		return $gengo->update_message(__("Post successfully removed from group.", GENGO_DOMAIN));
	}
	else return $gengo->error_message(__("Please specify a valid Post ID.", GENGO_DOMAIN));
}

function gengo_remove_translation_group($translation_group)
{
	global $gengo, $wpdb;
	
	if ($post_id = $wpdb->get_var("SELECT post_id FROM $gengo->post2lang_table WHERE translation_group = $translation_group LIMIT 1")) {
		$wpdb->query("UPDATE $gengo->post2lang_table SET translation_group = 0 WHERE translation_group = $translation_group");
		if ($summary_group = $wpdb->get_var("SELECT summary_group FROM $gengo->post2lang_table WHERE post_id = $post_id LIMIT 1")) {
			$wpdb->query("UPDATE $gengo->post2lang_table SET summary_group = 0 WHERE summary_group = $summary_group");
			$wpdb->query("DELETE FROM $gengo->summary_table WHERE summary_group = $summary_group");
		}
		return $gengo->update_message(__("Translation group successfully removed.", GENGO_DOMAIN));
	}
	else return $gengo->error_message(__("Please specify a valid translation group.", GENGO_DOMAIN));
}


if (isset($_POST['gengo_create_group_submit'])) gengo_create_translation_group();
elseif (isset($_POST['gengo_group_remove_submit'])) gengo_remove_translation_group(key($_POST['gengo_group_remove_submit']));
elseif (isset($_GET['gengo_action'])) {
	if ('remove_from_translation_group' == $_GET['gengo_action']) gengo_remove_post_from_translation_group($_GET['post_id']);
}

$flag1 = $flag2 = "";
$show = $_REQUEST['show'];	
switch($show):
	case "all":	case "":	$flag1=' class="current"';	break;
	case "new": case "":	$flag2=' class="current"'; 	break;
endswitch;

if($show!="new"): ?>
<div class="wrap">
<h2><?php _e('Translations', GENGO_DOMAIN); ?></h2>
<ul class="subsubsub">
<li><a href='admin.php?page=gengo/gengo_translations_page.php&amp;show=all'<?=$flag1;?>><?php _e('All groups', GENGO_DOMAIN) ?></a> |</li>
<li><a href='admin.php?page=gengo/gengo_translations_page.php&amp;show=new'<?=$flag2;?>><?php _e('Add new', GENGO_DOMAIN) ?></a></li>
</ul>

<form name="gengo_translations_form" method="post">
<?php
$lower_bound = (isset($_POST['lower_bound'])) ? key($_POST['lower_bound']) : 0;
if ($groups = $wpdb->get_col("SELECT DISTINCT translation_group FROM $gengo->post2lang_table WHERE translation_group != 0 ORDER BY translation_group DESC LIMIT $lower_bound, ". GENGO_TRANSLATIONS_PAGED)) {
  $groups = implode(',', $groups);
	$results = $wpdb->get_results("SELECT ID AS post_id, post_title, translation_group, language FROM $wpdb->posts p INNER JOIN $gengo->post2lang_table p2l ON p.ID = p2l.post_id INNER JOIN $gengo->language_table l ON p2l.language_id = l.language_id WHERE p2l.translation_group IN ($groups) ORDER BY translation_group, post_id", ARRAY_A)	?>
	<p><?php _e('Use this page to manage existing translation groups and create new ones.  The rules for updating translation groups are as follows:', GENGO_DOMAIN); ?></p>
	<ul>
		<li><?php _e('Removing a post from a translation group will cause any translation group summaries to be unlinked from that post as well.', GENGO_DOMAIN) ?></li>
		<li><?php _e('If you remove a post from a translation group with only 2 entries, the translation group will be removed.  Summaries will stay associated with the post that was not removed from the group.', GENGO_DOMAIN) ?></li>
		<li><?php _e("Removing an entire group using the 'Remove Group' button will delete all summaries in that group.", GENGO_DOMAIN) ?></li>
	</ul>
	<?php
	$i = 0;
	foreach ($results as $result) {
		extract($result, EXTR_OVERWRITE);
		if (($translation_group != $results[$i - 1]['translation_group'])) {
			?>
			<div class="widefat">
			<div style="padding:2px;width:45%;float:left;">
			<p><strong><?php _e('Posts:', GENGO_DOMAIN); ?></strong></p>
			<table class="widefat">
			<thead>
				<th scope="col"><?php _e('ID', GENGO_DOMAIN); ?></th>
				<th scope="col"><?php _e('Title', GENGO_DOMAIN); ?></th>
				<th scope="col" colspan="2"><?php _e('Language', GENGO_DOMAIN); ?></th>
			</thead>
			<?php
		}
		?>
		<tr class="alternate">
			<th scope="row"><?php echo $post_id ?></th>
			<td><?php echo "<a href='".get_permalink($post_id)."'>$post_title</a>"; ?></td>
			<td><?php echo "$language" ?></td>
			<td><a class="delete" href="<?php echo add_query_arg(array('gengo_action' => 'remove_from_translation_group', 'post_id' => $post_id)); ?>" onclick="return confirm('<?php _e("You are about to remove this post from the translation group.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN) ?>');">Remove</a></td>
		</tr>
		<?php

		if ($translation_group != $results[++$i]['translation_group']) {
			?>
			</table>
			</div>
			<div style="float:right;width:48%;padding:2px;">
			<p><strong><?php _e('Summaries:', GENGO_DOMAIN) ?></strong></p>
			<?php
			if ($summaries = $gengo->get_the_summaries($post_id)) $gengo->list_summaries($summaries, $dummy, 'existing', false);
			else { ?><p><em><?php _e('None', GENGO_DOMAIN) ?></em></p><?php }
			?>
			</div>
			<br style="clear:both;" />
			<p class="submit" style="border:0px; margin:10px 10px; padding:0px;"><input type="submit" value="<?php _e('Remove Group', GENGO_DOMAIN) ?>" name="gengo_group_remove_submit[<?php echo $translation_group ?>]" id="deletepost" onclick="return confirm('<?php _e("You are about to delete this translation group.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN) ?>');" /></p>
			</div>
			<br style="clear:both;" />
			<?php
		}
	}
	?>
	<div>
	<form method="post">
	<p>
	<?php
	if ($lower_bound) {
		$previous = ($lower_bound < GENGO_TRANSLATIONS_PAGED) ? $lower_bound : GENGO_TRANSLATIONS_PAGED;
		?>
		<input type="submit" style="padding:1px;" name="lower_bound[<?php echo $lower_bound - $previous ?>]" id="previous_groups" value="&laquo; Previous <?php echo $previous ?> Groups" />
		<?php
	}
	$translation_groups = count($wpdb->get_col("SELECT DISTINCT translation_group FROM $gengo->post2lang_table WHERE translation_group != 0"));
	if ($translation_groups > ($lower_bound + GENGO_TRANSLATIONS_PAGED)) {
		$remaining = $translation_groups - ($lower_bound + GENGO_TRANSLATIONS_PAGED);
		if ($remaining > GENGO_TRANSLATIONS_PAGED) $remaining = GENGO_TRANSLATIONS_PAGED;
		?>
		<input type="submit" style="float: right; margin: 0 25px 10px 0;" name="lower_bound[<?php echo $lower_bound + GENGO_TRANSLATIONS_PAGED ?>]" id="previous_groups" value="Next <?php echo $remaining ?> Groups &raquo;" />
		<?php
	}
	?>
	</p>
	</form>
	</div>
	<br style="clear:both;" />
	<p><strong><?php _e('Note:', GENGO_DOMAIN) ?></strong> <?php _e('Removing a post from a translation group or deleting an entire translation group will <strong>not</strong> delete the affected posts.', GENGO_DOMAIN) ?></p>
	</div>
	<?php
} else {
	?><p><?php _e('No translation groups defined yet.', GENGO_DOMAIN) ?></p><?php
} ?>
</form>
</div>

<?php else: ?>

<div class="wrap" id="gengo_add_translation_group">
<h2><?php _e('Create New Translation Group', GENGO_DOMAIN); ?></h2>
<ul class="subsubsub">
<li><a href='admin.php?page=gengo/gengo_translations_page.php&amp;show=all'<?=$flag1;?>><?php _e('All groups', GENGO_DOMAIN) ?></a> |</li>
<li><a href='admin.php?page=gengo/gengo_translations_page.php&amp;show=new'<?=$flag2;?>><?php _e('Add new', GENGO_DOMAIN) ?></a></li>
</ul>
	<form name="gengo_translations_form" method="post">
<?php
if (($count_languages = count($gengo->languages)) > 1) {
	if ($posts = $wpdb->get_results("SELECT ID AS post_id, post_title, language_id FROM $wpdb->posts p INNER JOIN $gengo->post2lang_table p2l ON p.ID = p2l.post_id WHERE translation_group = 0 ORDER BY language_id ASC, post_id DESC")) {
		?>
		<p><?php _e("Select the posts that you wish to create a translation group from and then click 'Create Group'.  To exclude a language from a group, select '(None)'.", GENGO_DOMAIN) ?></p>
		<form name="gengo_group_form" method="post">
		<table class="widefat" id="the-list-x">
		<?php
		$options_available = 1;
		foreach ($posts as $key => $post) {
			if ($posts[$key - 1]->language_id != $post->language_id) $option_string = '<option value="0" selected="selected">(None)</option>';
			$title = (strlen($post->post_title) > GENGO_SIDEBAR_SELECT_LENGTH) ? substr($post->post_title, 0, GENGO_SIDEBAR_SELECT_LENGTH-2) . ".." : $post->post_title;
			$option_string .= "<option value=\"$post->post_id\">$title</option>";
			if ($posts[$key + 1]->language_id != $post->language_id) {
			  if ($posts[$key + 1]->language_id) $options_available++;
			  elseif ($options_available == 1) break;
			  if (($options_available == 2) && !$header_written) {
					?>
					<thead>
					<tr>
					<th scope="col"><?php _e('Language', GENGO_DOMAIN) ?></th>
					<th scope="col"><?php _e('Post / Page', GENGO_DOMAIN) ?></th>
					<th scope="col" colspan="<?php echo $count_languages ?>" align="center"><?php _e('Summaries', GENGO_DOMAIN) ?></th>
					</tr>
					</thead>
					<?php
					$header_written = true;
				}
				?>
				<tr>
					<th scope="row"><?php echo $gengo->languages[$post->language_id]->language ?></th>
					<td><select onchange="gengo_get_translation_group_components(<?php echo $post->post_id ?>)" name="gengo_constituent_posts[]" id="gengo_lang_<?php echo $post->language_id ?>"><?php echo $option_string ?></select></td>
					<?php
					foreach ($gengo->languages as $language_id => $entry) {
						?>
						<td id='<?php echo $post->language_id . '_' . $language_id ?>' style="width: 155px"></td>
						<?php
					}
					?>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<input type="hidden" name="gengo_resolved_summary_clashes" id="gengo_resolved_summary_clashes" />
		<input type="hidden" name="gengo_removed_summary_clashes" id="gengo_removed_summary_clashes" />
		<?php
		if ($options_available > 1) {
			?>
			<p class="submit">
			<input type="submit" id="gengo_create_group_submit" name="gengo_create_group_submit" value="Create Group" disabled="disabled" /></p></div>
			<div class="wrap" id="gengo_create_group_message"><p><strong><?php _e('Note:', GENGO_DOMAIN) ?></strong>
			<?php _e('You must select a post or page from at least 2 languages to create a translation group.', GENGO_DOMAIN) ?></p>
			<?php
		} else {
			?>
			<p><?php _e('There must be at least one post or page not already in a translation group in more than one language to create a new translation group.', GENGO_DOMAIN) ?></p>
			<?php
		}
		?>
		</form>
		<?php
	} else {
		?>
		<p><?php _e('There must be at least two languages defined to create translation groups.', GENGO_DOMAIN) ?></p>
		<?php
	}
?>
</div>
<?php
}

endif;
?>