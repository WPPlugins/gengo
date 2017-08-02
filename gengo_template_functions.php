<?php
/**
 * Returns a list of defined languages as a formatted list.
 *
 * @param string $arguments
 */
function gengo_list_languages($arguments = '') {
	global $gengo, $wp_rewrite;

	$default_args = array('pre' => '<li>', 'post' => '</li>', 'show_count' => 'true', 'show_current' => 'true', 'show_unreadable' => 'true', 'snippet' => '', 'content_type' => 'post');
  parse_str($arguments, $args);
  $args = array_merge($default_args, $args);
	
	if (!$gengo->languages) {
		echo $args['pre'] . __('No Languages Defined', GENGO_DOMAIN) . $args['post'];
	} else {
		$titles = ($args['snippet']) ? $gengo->get_synblocks($args['snippet']) : array();
		$home = trailingslashit($gengo->site_home);
		if ($wp_rewrite->using_index_permalinks()) $home .= $wp_rewrite->index;
		$totals = $gengo->get_totals($args['content_type']);
		asort($gengo->languages);
		foreach ($gengo->languages as $language_id => $entry) {
			if ($post_count = $totals[$language_id]) {
			  if ('false' != $args['show_count']) $count_string = " ($post_count)";
			  $gengo->forced_append = true;
				if (('false' != $args['show_unreadable']) || (false !== strpos($gengo->viewable_code_string, $entry->code))) {
	  			$title = $titles[$language_id] ? $titles[$language_id] : $entry->language;
					if (false === $gengo->is_set_language($language_id)) {
						echo "$args[pre]<a class=\"gengo_lang_$entry->code\" href=\"" . $gengo->append_link_language($home, $entry->code) . "\" hreflang=\"" . $entry->code . "\">$title</a>$count_string$args[post]";
					} else {
					  if ('false' != $args['show_current']) {
							echo "$args[pre]<span class=\"gengo_lang_$entry->code\">$title$count_string</span>$args[post]";
						}
		 			}
				}
			}
		}
	}
}

/**
 * Modified version of WordPress' own link_pages.
 *
 * @param string $before
 * @param string $after
 * @param string $next_or_number
 * @param string $nextpagelink
 * @param string $previouspagelink
 * @param string $pagelink
 * @param string $more_file
 */
function gengo_link_pages($before = '<br />', $after = '<br />', $next_or_number = 'number', $nextpagelink = 'next page', $previouspagelink = 'previous page', $pagelink = '%', $more_file = '') {
 	global $gengo, $id, $page, $numpages, $multipage, $more, $pagenow;

	if ($multipage) {
		$file = $more_file ? $more_file : $pagenow;
		$permalinks = get_settings('permalink_structure');

		if ($permalinks) $link_base = ($gengo->language_supplied) ? substr(trailingslashit(get_permalink()), 0, -3) : get_permalink();
		else $link_base = get_permalink() . '&amp;page=';

		if ('number' == $next_or_number) {
			echo $before;
 			for ($i = 1; $i < ($numpages + 1); $i++) {
				$j = str_replace('%', "$i", $pagelink);
				if (($i != $page) || ((!$more) && ($page == 1))) {
					$link = $link_base . $i;
					if ($gengo->language_preference && $permalinks) $link = $gengo->append_link_language($link, $gengo->language_preference[0]->code);
					echo "<a href=\"$link\">$j</a> ";
				}
				else echo "$j ";
			}
			echo $after;
		} elseif ($more) {
			echo $before;
			$i = $page - 1;
			if ($i && $more) {
				$link = $link_base . $i;
				if ($gengo->language_preference && $permalinks) $link = $gengo->append_link_language($link, $gengo->language_preference[0]->code);
				echo "<a href=\"$link\">$previouspagelink</a>";
			}
			$i = $page + 1;
			if ($i <= $numpages && $more) {
				$link = $link_base . $i;
				if ($gengo->language_preference && $permalinks) $link = $gengo->append_link_language($link, $gengo->language_preference[0]->code);
				echo "<a href=\"$link\">$nextpagelink</a>";
			}
			echo $after;
		}
	}
}

/**
 * Modified version of WordPress' own previous_posts_link.
 *
 * @param string $label
 */
function gengo_previous_posts_link($label = 'Newer Entries &raquo;') {
	global $gengo, $paged;

	if (!is_single() && ($paged > 1)) 	{
		$nextpage = intval($paged) - 1;
		if ($nextpage < 1) $nextpage = 1;
		$link = get_pagenum_link($nextpage);
		if ($gengo->language_preference) $link = str_replace("/{$gengo->viewable_code_string}/page/$nextpage/", "/page/$nextpage/{$gengo->viewable_code_string}/", $link);
		echo '<a href="' . $link . '">' . preg_replace('/&([^#])(?![a-z]{1,8};)/', '&#038;$1', $label) . '</a>';
	}
}

/**
 * Modified version of WordPress' own next_posts_link.
 *
 * @param string $label
 * @param int $max_page
 */
function gengo_next_posts_link($label = '&laquo; Older Entries', $max_page = 0) {
	global $gengo, $paged, $max_num_pages, $result, $request, $posts_per_page, $wpdb, $wp_query;
	if ( !$max_page ) {
		$max_page = $wp_query->max_num_pages;
	}
	if (!$paged) $paged = 1;
	(int) $nextpage = intval($paged) + 1;
#	echo $max_page;#."/".$paged;
	if (!is_single() && ($nextpage <= $max_page)) {
		$link = get_pagenum_link($nextpage);
		foreach ($gengo->language_preference_id as $id) $string .= ($string) ? GENGO_LANGUAGE_DIVIDER . $gengo->languages[$id]->code : $gengo->languages[$id]->code;
		if ($gengo->language_preference) $link = str_replace("/$string/page/$nextpage/", "/page/$nextpage/$string/", $link);
		echo '<a href="' . $link . '">' . preg_replace('/&([^#])(?![a-z]{1,8};)/', '&#038;$1', $label) . '</a>';
	}
}

/**
 * Returns a text snippet 
 *
 * @param string $block_name
 * @param bool $return
 * @return string
 */
function gengo_synblock($block_name, $return = false) {
	return gengo_snippet($block_name, $return);
}

/**
 * Prints or returns a text snippet
 *
 * @param string $block_name
 * @param bool $return
 */
function gengo_snippet($block_name, $return = false) {
	global $gengo;
	if ($code = $gengo->language_preference[0]->code) {
		if ($return) return $gengo->get_synblock($block_name, $gengo->language_preference_id[0]);
		else echo $gengo->get_synblock($block_name, $gengo->language_preference_id[0]);
	}
}

/**
 * Prints or returns the trackback url
 *
 * @param bool $display
 */
function gengo_trackback_url($display = true) {
	global $gengo, $id, $post;

	$url = get_settings('permalink_structure') ? substr(trailingslashit(get_permalink()), 0, -3) . 'trackback/' : get_settings('siteurl') . '/wp-trackback.php?p=' . $id;
	$url = $gengo->append_link_language($url, $post->code);

	if ($display) echo $url;
	else return $url;
}

/**
 * Prints an unordered list of viewing languages, with JS controls 
 *
 * @param string $title
 */
function gengo_viewing_languages($title = 'GENGO_DEFAULT') {
	global $gengo;

	if ('GENGO_DEFAULT' == $title) $title = '<h2>' . __('Current Languages', GENGO_DOMAIN) . '</h2>';
	echo $title;
	?>
	<ul id="gengo_viewing_languages">
	<?php
	$viewing_languages = explode(GENGO_LANGUAGE_DIVIDER, $gengo->viewable_code_string);
	foreach ($viewing_languages as $code) {
	  $id = $gengo->codes2ids[$code];
		?>
		<li class="gengo_language_element" id="gengo_language_<?php echo $id ?>">
		<span class="gengo_language_element_name"><?php echo $gengo->languages[$id]->language ?></span>
		<span class="gengo_element_control_set" id="gengo_control_set_<?php echo $id ?>">
		<a class="gengo_element_control" href="#" onclick="return gengo_language_move_up(<?php echo $id ?>);"><?php _e('Up', GENGO_DOMAIN) ?></a>
		<a class="gengo_element_control" href="#" onclick="return gengo_language_move_down(<?php echo $id ?>);"><?php _e('Down', GENGO_DOMAIN) ?></a>
		<a class="gengo_element_control" href="#" onclick="return gengo_language_remove(<?php echo $id ?>);"><?php _e('x', GENGO_DOMAIN) ?></a>
		</span>
		</li>
		<?php
	}
	?>
	</ul>
	<?php
}

/**
 * Prints an unordered list of available languages, with JS controls 
 *
 * @param string $title
 */
function gengo_available_languages($title = 'GENGO_DEFAULT') {
	global $gengo;

	if ('GENGO_DEFAULT' == $title) $title = '<h2>' . __('Available Languages', GENGO_DOMAIN) . '</h2>';
	echo $title;
	?>
	<ul id="gengo_available_languages">
	<?php
	$viewing_languages = explode(GENGO_LANGUAGE_DIVIDER, $gengo->viewable_code_string);
	foreach ($gengo->languages as $language_id => $entry) {
	  if (!in_array($entry->code, $viewing_languages)) {
			?>
			<li class="gengo_language_element" id="gengo_language_<?php echo $language_id ?>">
			<span class="gengo_language_element_name"><?php echo $entry->language ?></span>
			<span class="gengo_element_control_set" id="gengo_control_set_<?php echo $language_id ?>">
			<a class="gengo_element_control" href="#" onclick="return gengo_language_add(<?php echo $language_id ?>);"><?php _e('Add', GENGO_DOMAIN) ?></a>
			</span>
			</li>
			<?php
		}
	}
	?>
	</ul>
	<?php
}

/**
 * Prints language controls set
 *
 * @param string $title
 */
function gengo_language_set($title = 'GENGO_DEFAULT') {
	global $gengo;
	if ('GENGO_DEFAULT' == $title) $title = '<h2>' . __('Language Control', GENGO_DOMAIN) . '</h2>';
	echo $title;
	?>
	<ul id="gengo_available_languages">
	<li class="gengo_language_element">
	<div class="gengo_settings_control_set">
	<a class="gengo_settings_save_control" href="#" onclick="return gengo_language_save();"><?php _e('Save', GENGO_DOMAIN) ?></a>
	<a class="gengo_settings_reset_control" href="#" onclick="return gengo_language_reset();"><?php _e('Reset', GENGO_DOMAIN) ?></a>
	</div>
	</li>
	</ul>
	<?php
}

/**
 * Viewing languages, available languages, and control set.
 *
 * @param string $viewing_title
 * @param string $available_title
 */
function gengo_language_control($viewing_title = 'GENGO_DEFAULT', $available_title = 'GENGO_DEFAULT') {
	?>
	<div id="gengo_language_control">
	<?php
	gengo_viewing_languages($viewing_title);
	gengo_available_languages($available_title);
	gengo_language_set()
	?>
	</div>
	<?php
}

/**
 * Echoes or returns a home URL link with correct language appending.
 *
 * @param bool $return
 */
function gengo_home_url($return = false) {
	global $gengo, $wp_rewrite;

	if ($wp_rewrite->using_index_permalinks()) $index = $wp_rewrite->index;
	$url = $gengo->append_link_language(trailingslashit(get_settings('home')) . $index, $gengo->viewable_code_string);
	if ("index.php" == substr($url, -9)) $url = substr($url, 0, -9);

	if ($return) return $url;
	else echo $url;
}


// Checks whether we are on a language page.
function is_language($specific = '') {
	global $gengo;

	if (!$code = $gengo->language_preference[0]->code) return false;
	if ($specific) {
		if (false !== $gengo->is_set_language($gengo->codes2ids[$specific])) return true;
		return false;
	}
	return true;
}

function the_template_language($return = false) {
	global $gengo;
	
	if ($return) return $gengo->language_preference[0]->language;
	else echo $gengo->language_preference[0]->language;
}

function the_template_code($return = false) {
	global $gengo;

	if ($return) return $gengo->language_preference[0]->code;
	else echo $gengo->language_preference[0]->code;
}

function the_template_locale($return = false) {
	global $gengo;

	if ($return) return $gengo->language_preference[0]->locale;
	else echo $gengo->language_preference[0]->locale;
}

function the_template_direction($return = false) {
	global $gengo;

	if ($return) return $gengo->language_preference[0]->rtl ? 'rtl' : 'ltr';
	else echo $gengo->language_preference[0]->rtl ? 'rtl' : 'ltr';
}

// Echoes the language of the current post.
function the_language($return = false) {
	global $post;

	if ($return) return $post->language;
	else echo $post->language;
}

// Echoes or returns the language code of the current post.  Thanks to Martin Gottstein for the idea.
function the_language_code($return = false) {
	global $post;

	if ($return) return $post->code;
	else echo $post->code;
}

// Echoes or returns the language locale of the current post.
function the_language_locale($return = false) {
	global $post;

	if ($return) return $post->locale;
	else echo $post->locale;
}

// Echoes or returns the language text direction of the current post.
function the_language_direction($return = false) {
	global $post;

	if ($return) return $post->rtl ? 'rtl' : 'ltr';
	else echo $post->rtl ? 'rtl' : 'ltr';
}

// Outputs a string of all the languages a reader can read.
function the_viewable_languages($aspect, $glue = ', ', $return = false) {
	global $gengo;

	$viewable_count = count($gengo->language_preference_id);
	if (1 == $viewable_count) $string = $gengo->languages[$gengo->language_preference_id[0]]->language;
	else {
		$i = 0;
		foreach ($gengo->language_preference_id as $id) {
		  if ($i++) $string .= ($i == $viewable_count) ? " $aspect " : $glue;
		  $string .= $gengo->languages[$id]->language;
		}
	}

	if ($return) return $string;
	else echo $string;
}

// Outputs a string of language codes in use on this page.
function the_viewable_codes($return = false) {
	global $gengo;

	if ($return) return $gengo->viewable_code_string;
	else echo $gengo->viewable_code_string;
}

// Outputs a div containing all the summaries for this article.  Use in The Loop.
function the_summaries($lang = 'xml:lang,lang', $title = 'GENGO_DEFAULT') {
	global $gengo, $post;

	if (!$summaries = $gengo->post_language_cache[$post->ID]->summaries) return;

	$lowest = 10000;
	$default_id = 0;
	$viewable_summaries = array();
	foreach ($summaries as $summary_id => $summary) {
		if (false !== ($order = $gengo->is_viewable_language($summary['summary_language']))) {
		  $viewable_summaries[$summary_id] = $summary;
			if ($order < $lowest) {
				$default_id = $summary_id;
		  	$lowest = $order;
			}
		}
	}
	if (!$viewable_summaries) return;
	if (!$default_id) $default_id = $summaries[0]['summary_id'];

	if (($sum_count = count($viewable_summaries)) > 1) {
		?><script type="text/javascript">var current_id = <?php echo $default_id ?>;</script><?php
	}
	if ($title) {
		if ('GENGO_DEFAULT' == $title) $title = __('Summaries:', GENGO_DOMAIN);
		?>
		<legend id="gengo_summaries_title" for="gengo_summaries_container"><?= $title ?></legend>
		<?php
	}
	?>
	<div id="gengo_summaries_container">
	<?php
	foreach ($viewable_summaries as $summary_id => $summary) {
		if ($lang) {
		  $attributes = explode(',', $lang);
		  $lang_atts = '';
			foreach($attributes as $attribute) {
				$lang_atts .= ' ' . $attribute . '="' . $gengo->languages[$summary['summary_language']]->code . '"';
			}
		}
		?><p class="gengo_summary_inner" id="gengo_summary_<?= $summary_id ?>" style="display: <?php echo ($summary_id == $default_id) ? 'block' : 'none' ?>"<?= $lang_atts ?>><?php echo $summary['summary'] ?></p><?php
		$link_list .= '<a class="gengo_summary_link" id="gengo_summary_' . $summary_id . '" href="#" onclick="gengo_switch_summary(' . $summary_id . '); return false;">' . $gengo->languages[$summary['summary_language']]->language . '</a> ';
	}
	if ($sum_count > 1) echo $link_list;
	?>
	<p style="clear: both"></p>
	</div>
	<?php
}

// Returns a formatted list of translations for this article. Use in The Loop.
function the_translations($arguments = '') {
	global $gengo;

	$default_args = array('return' => false, 'pre' => '<ul><li>', 'post' => '</li></ul>', 'inner' => '</li><li>', 'title_none' => 'GENGO_DEFAULT', 'title_exists' => 'GENGO_DEFAULT', 'show_authors' => false, 'show_dates' => '', 'show_current' => false, 'snippet' => '');
  parse_str($arguments, $args);
  $args = array_merge($default_args, $args);

	$titles = ($args['snippet']) ? $gengo->get_synblocks($args['snippet']) : array();
	// TODO: Change to use the cache.
	if (!$translations = $gengo->get_the_translations()) {
		if ($args['return']) return array();
	  if ('GENGO_DEFAULT' == $args['title_none']) $args['title_none'] = '<p>' . __('No Translations', GENGO_DOMAIN) . '</p>';
	  echo $args['title_none'];
		return;
	}
	if ($args['return']) return $translations;
	if ('GENGO_DEFAULT' == $args['title_exists']) $args['title_exists'] = '<p>' . __('Other Languages:', GENGO_DOMAIN) . '</p>';
	echo $args['title_exists'] . $args['pre'];
	foreach ($translations as $translation) {
	  if ($i++) echo $args['inner'];
	  $title = $titles[$translation['translation_language']] ? $titles[$translation['translation_language']] : '';
	  echo $gengo->translation_link($translation, 'a', $title);
	  if ($args['show_author']) echo ' (' . $translation['translation_author'] . ')';
	  if ($args['show_date']) echo ' ' .  mysql2date($args['show_date'], $translation['translation_date']);
	}
	if ($args['show_current']) {
	  $title = $args['snippet'] ? $titles[$gengo->codes2ids[the_language_code(true)]] : the_language(true);
		echo $args['inner'] . $title;
	}
	echo $args['post'];
}

function the_translations_comments($show_count = true) {
	global $gengo, $wpdb, $post;

	if (!$translations = $this->post_language_cache[$post->ID]->translations) return;
	foreach ($translations as $translation) {
		if (!$translation['translation_comments']) continue;
		$comments_string .= $gengo->translation_link($translation, 'comments');
		if ($show_count) $comments_string .= " ($translation[translation_comments]) ";
	}
	if ($comments_string) printf(__('View Comments in: %s', GENGO_DOMAIN), $comments_string);
}

include_once(ABSPATH . GENGO_DIR . 'gengo_extra_functions.php');
?>
