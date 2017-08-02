<?php

// This is a supplemental file containing non-core functionality.  It provides additional features
// developed under sponsorship and should be placed in the gengo directory.  Gengo will still work
// without this file. For more information about sponsoring features, use the contact form at
// http://jamietalbot.com/about/ .

// All sponsored work will be released back into the community, under the same MIT license as Gengo,
// with a note of appreciation and a link to the sponsor's site.

// Sponsored by Eggplant Media.
// http://www.eggplant.coop
// Outputs a link to the parent of the current page.
function gengo_eggplant_parent() {
	global $gengo, $wpdb, $wp_query;

	if (!$current_page = $wp_query->posts[0]->ID) return;
	if ($page = $wpdb->get_row("SELECT p1.post_title AS post_title, p1.ID AS ID FROM $wpdb->posts p1 INNER JOIN $wpdb->posts p2 ON p1.ID = p2.post_parent WHERE p2.ID = $current_page AND p1.post_type != 'attachment' LIMIT 1")) {
		?>
		<a href="<?php echo get_page_link($page->ID) ?>" title="<?php echo wp_specialchars($page->post_title) ?>"><?php echo $page->post_title ?></a>
		<?php
	}
}

// Sponsored by Eggplant Media.
// http://www.eggplant.coop
// Lists the subpages of the current page, or its siblings if there are none.
function gengo_eggplant_subpages($args = '')
{
	global $gengo, $wpdb, $wp_query;

	parse_str($args, $r);
	if (!isset($r['title_li'])) $r['title_li'] = __('Pages');
	if (!$r['show_date']) $r['show_date'] = '';
	if (!$r['sort_column']) $r['sort_column'] = 'menu_order';
	if (!$r['sort_order']) $r['sort_order'] = 'ASC';
	if ($r['show_date']) $format = $r['date_format'] ? $r['date_format'] : get_settings('date_format');
	if ($r['exclude_above']) {
		$exclude = " AND p2.menu_order <= " . $r['exclude_above'];
	}

	if (!$current_page = $wp_query->posts[0]->ID) return;
	$language_ids = implode(',', $gengo->language_preference_id);

	if (!$pages = $wpdb->get_results("SELECT p2.post_title, p2.ID, p2.post_modified, p2.post_date FROM $gengo->post2lang_table p2l INNER JOIN $wpdb->posts p2 ON p2l.post_id = p2.ID WHERE post_parent = $current_page AND p2l.language_id IN ($language_ids) AND post_type != 'attachment' $exclude ORDER BY $r[sort_column] $r[sort_order]"))
		$pages = $wpdb->get_results("SELECT p2.post_title, p2.ID, p2.post_modified, p2.post_date FROM $wpdb->posts p1 INNER JOIN $wpdb->posts p2 ON (p1.post_parent = p2.post_parent) INNER JOIN $gengo->post2lang_table p2l ON p2.ID = p2l.post_id WHERE p1.ID = $current_page AND p2.post_type = 'page' AND p2l.language_id IN ($language_ids) $exclude ORDER BY p2.$r[sort_column] $r[sort_order]");

	if (!$pages) return;
	?>
	<li class="pagenav"><?php echo $r['title_li'] ?><ul>
	<?php
	foreach ($pages as $page)
	{
		?>
		<li class="page_item">
		<a href="<?php echo get_page_link($page->ID) ?>" title="<?php echo wp_specialchars($page->post_title) ?>"><?php echo $page->post_title ?></a>
		<?php
		if ($format)
		{
		  $date = ('modified' == $r['show_date']) ? $page->post_modified : $page->post_date;
		  echo mysql2date($format, $date);
		}
		?>
		</li>
		<?php
	}
	?>
	</ul></li>
	<?php
}

// Sponsored by Eggplant Media.
// http://www.eggplant.coop
// Returns an array containing the most recent post objects or a single post object in a given category in the current language.
// Can be called with either a category id, or the sanitised category name.
function gengo_eggplant_recent_category($category, $limit = 10)
{
	global $gengo, $wpdb;

	if (!is_numeric($limit)) $limit = 10;
	if (is_numeric($category)) $where = "p2c.category_id = $category";
	else
	{
	  $where = "c.category_nicename = '$category'";
		$join = " INNER JOIN $wpdb->categories AS c ON c.cat_ID = p2c.category_id";
	}
	$language_ids = implode(',', $gengo->language_preference_id);
	$query = "SELECT p.* FROM $wpdb->posts AS p INNER JOIN $gengo->post2lang_table AS p2l ON p.ID = p2l.post_id INNER JOIN $wpdb->post2cat AS p2c ON p2c.post_id = p.ID $join WHERE p2l.language_id IN ($language_ids) AND $where ORDER BY p.post_date DESC LIMIT $limit";

	if (1 == $limit)
	{
	  if ($result = $wpdb->get_row($query)) return $result;
	  else return NULL;
	}
	else
	{
	  if ($results = $wpdb->get_results($query)) return $results;
	  else return array();
	}
}

// Sponsored by Eggplant Media.
// http://www.eggplant.coop
// Returns a single random post from the specified category.
// Can be called with either a category id, or the sanitised category name.
function gengo_eggplant_random_category_post($category) {
	global $gengo, $wpdb;

	if (is_numeric($category)) {
		$where = "p2c.category_id = $category";
	} else {
	  $where = "c.category_nicename = '$category'";
		$join = " INNER JOIN $wpdb->categories AS c ON c.cat_ID = p2c.category_id";
	}
	$language_ids = implode(',', $gengo->language_preference_id);
	$query = "SELECT p.* FROM $wpdb->posts AS p INNER JOIN $gengo->post2lang_table AS p2l ON p.ID = p2l.post_id INNER JOIN $wpdb->post2cat AS p2c ON p2c.post_id = p.ID $join WHERE p2l.language_id IN ($language_ids) AND $where ORDER BY RAND() LIMIT 1";
	return ($result = $wpdb->get_row($query)) ? $result : NULL;
}
?>