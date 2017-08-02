<?php
/*
Plugin Name: Gengo
Plugin URI: http://wordpress.org/extend/plugins/gengo/
Description: Multi-language blogging for WordPress.<br/>Licensed under the <a href="http://www.opensource.org/licenses/mit-license.php">MIT License</a>, Copyright &copy; 2006-2008 Jamie Talbot.
Version: 2.5.3
Author: Jamie Talbot
Author URI: http://jamietalbot.com/
*/

/*
Gengo - Multi-language blogging for WordPress.
Copyright (c) 2006-2008 Jamie Talbot
Additional commits (c) 2008+ Paolo Tresso (http://pixline.net)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated
documentation files (the "Software"), to deal in the
Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software,
and to permit persons to whom the Software is furnished to
do so, subject to the following conditions:

The above copyright notice and this permission notice shall
be included in all copies or substantial portions of the
Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY
KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// Load some variables for when running as ajax or activating.
if (!defined('ABSPATH')) {
	require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php');
}

define ("GENGO_VERSION", "2.5.3");
define ("GENGO_BASE_DIR", "gengo/");
define ("GENGO_DIR", "wp-content/plugins/" . GENGO_BASE_DIR);
define ("GENGO_LANGUAGES_DIR", "wp-content/languages/");
define ("GENGO_LANGUAGES_PAGE", "gengo_languages_page.php");
define ("GENGO_TRANSLATIONS_PAGE", "gengo_translations_page.php");
define ("GENGO_SYNONYMS_PAGE", "gengo_synonyms_page.php");
define ("GENGO_EXCLUSIONS_PAGE", "gengo_exclusions_page.php");
define ("GENGO_LANGUAGE_TABLE", $wpdb->prefix . "languages");
define ("GENGO_POST2LANG_TABLE", $wpdb->prefix . "post2lang");
define ("GENGO_SUMMARY_TABLE", $wpdb->prefix . "summaries");
define ("GENGO_TERM2SYN_TABLE", $wpdb->prefix . "term2syn");
define ("GENGO_SYNBLOCK_TABLE", $wpdb->prefix . "synblocks");
define ("GENGO_SIDEBAR_TITLE_LENGTH", 80);
define ("GENGO_SIDEBAR_SELECT_LENGTH", 50);
define ("GENGO_POSTS_LIMIT", 10);
define ("GENGO_GROUPS_LIMIT", 3);
define ("GENGO_TRANSLATIONS_PAGED", 5);
define ("GENGO_DOMAIN", "gengo");

// You can alter this if you really want to, but I don't recommend it.  For a start all your
// permalinks will change, and your permalink structure may break entirely.  There has been
// no testing on anything other than '+', which works.  So leave it :D  Your other alternative
// is to turn off code-appending entirely.
define ("GENGO_LANGUAGE_DIVIDER", '+');

class Language {
	var $code;
	var $language;
	var $locale;
	var $rtl;
	var $charset;
	
	function Language($code, $language, $locale, $rtl, $charset) {
		$this->code = $code;
		$this->language = $language;
		$this->locale = $locale;
		$this->rtl = $rtl;
		$this->charset = $charset;
	}
}

class Gengo {
	var $version;
	var $language_table;
	var $summary_table;
	var $post2lang_table;
	var $term2syn_table;
	var $synblock_table;
	var $languages;
	var $language_cookie;
	var $language_preference;
	var $language_preference_id;
	var $blog_default_language_id;
	var $default_url_exclusions;

	function Gengo($ajax = false) {
		static $language_preference;
		static $language_preference_id;
		static $languages;
		static $codes2ids;
		static $blog_default_language_id;
		static $viewable_code_string;

		$this->version = GENGO_VERSION;
		$this->language_table = GENGO_LANGUAGE_TABLE;
		$this->post2lang_table = GENGO_POST2LANG_TABLE;
		$this->summary_table = GENGO_SUMMARY_TABLE;
		$this->term2syn_table = GENGO_TERM2SYN_TABLE;
		$this->synblock_table = GENGO_SYNBLOCK_TABLE;
		$this->language_cookie = 'gengo_language' . COOKIEHASH;
		$this->default_url_exclusions = array('/wp-content', 'wp-login.php', 'xmlrpc.php', 'wp-pass.php', 'wp-register.php', 'theme-editor.php', '/wp-includes', 'wp-cron.php');
		$this->append_urls = get_option('gengo_append_urls');
		$this->site_home = get_option('home');
		$this->site_url = get_option('siteurl');
		$this->ajax = $ajax;
		
		$this->languages = & $languages;
		$this->codes2ids = & $codes2ids;
		$this->language_preference = & $language_preference;
		$this->language_preference_id = & $language_preference_id;
		$this->blog_default_language_id = & $blog_default_language_id;
		$this->viewable_code_string = & $viewable_code_string;

		// Populate a languages array for future use.
		$this->set_defined_languages();
		$this->set_blog_default_language();
		$this->language_preference = array();
		$this->language_preference_id = array();
		$this->strict_links = true;
		$this->viewable_code_string = '';

		if (!$ajax) {

			// Admin UI.
			if (is_admin()) {
				
				if (did_action('locale')) {
					echo "Another plugin is incorrectly localised.";
				}
				
				// Installer.
				register_activation_hook(__FILE__, array(& $this, 'activated'));
				register_deactivation_hook(__FILE__, array(& $this, 'deactivated'));

				add_action('admin_head', array(& $this, 'admin_head'));
				add_action('admin_menu', array(& $this, 'admin_menu'));
				add_action('edit_form_advanced', array(& $this, 'post_sidebar'));
				add_action('edit_page_form', array(& $this, 'post_sidebar'));
				add_action('edit_form_advanced', array(& $this, 'edit_form_advanced'));
				add_action('edit_page_form', array(& $this, 'edit_form_advanced'));
				add_action('admin_footer', array(& $this, 'admin_footer'));

				// User actions.
				add_action('profile_personal_options', array(& $this,'profile_personal_options'));
				add_action('personal_options_update', array(& $this,'personal_options_update'));
				add_action('delete_user', array(& $this,'delete_user'));

				// Manage tables.
				add_filter('manage_posts_columns', array(& $this,'manage_posts_columns'));
				add_action('manage_posts_custom_column', array(& $this,'manage_posts_custom_column'), 10, 2);
				add_filter('manage_pages_columns', array(& $this,'manage_posts_columns'));
				add_action('manage_pages_custom_column', array(& $this,'manage_posts_custom_column'), 10, 2);

				// Post updating.
				add_action('save_post', array(& $this, 'edit_post'));
				add_action('delete_post', array(& $this, 'delete_post'));

				// Miscellaneous.
				add_action('activity_box_end', array(& $this,'activity_box_end'));
			} else {
				// Links and permalinks.
				add_filter('category_link', array(& $this,'append_links'));
				add_filter('author_link', array(& $this,'append_links'));
				add_filter('year_link', array(& $this,'append_links'));
				add_filter('month_link', array(& $this,'append_links'));
				add_filter('day_link', array(& $this,'append_links'));
				add_filter('feed_link', array(& $this,'append_links'));
				add_filter('tag_link', array(& $this, 'append_links'));
				add_filter('post_comments_feed_link', array(& $this,'post_comments_feed_link'));
				add_filter('trackback_url', array(& $this,'trackback_url'));
				
				// SQL control.
				add_filter('query_vars', array(& $this,'query_vars'));
				add_filter('posts_fields', array(& $this,'posts_fields'));
				add_filter('posts_join', array(& $this,'posts_join'));
				add_filter('posts_where', array(& $this,'posts_where'));
				add_filter('getarchives_join', array(& $this,'posts_join'));
				add_filter('getarchives_where', array(& $this,'posts_where'));
				add_filter('comment_feed_join', array(& $this,'posts_join'));
				add_filter('comment_feed_where', array(& $this,'posts_where'));
				add_filter('get_previous_post_join', array(& $this,'previous_next_posts_join'));
				add_filter('get_previous_post_where', array(& $this,'previous_next_posts_where'));
				add_filter('get_next_post_join', array(& $this,'previous_next_posts_join'));
				add_filter('get_next_post_where', array(& $this,'previous_next_posts_where'));
				add_filter('posts_request', array(& $this,'posts_request'));
				//
				add_action('parse_query', array(& $this,'parse_query'));
				add_filter('get_pages', array(& $this,'get_pages'), 10, 2);
//				add_filter('get_categories', array(& $this,'get_categories'), 10, 2);
				add_filter('query_string', array(& $this,'query_string'));

				// Semantic information.
				add_action('wp_head', array(& $this,'wp_head'));

				// Static front page compatibility
				add_action('pre_get_posts', array(& $this, 'pre_get_posts'));
				add_filter('option_page_on_front', array(& $this,'option_page_on_front'));
			}

			// Term manipulation can happen either in the admin or when doing ajax.
			add_action('create_term', array(& $this, 'create_term'), 10, 2);
			add_action('edit_term', array(& $this, 'edit_term'), 10, 2);
			add_action('delete_term', array(& $this, 'delete_term'));

			// Tags
			add_filter('get_term', array(& $this, 'get_term'), 10, 2);
			
				// Rewrite rules.
			add_filter('rewrite_rules_array', array(& $this,'everybody_wants_prosthetic_rewrite_rules'));
			remove_filter('template_redirect', 'redirect_canonical');

			// Language appending throughout the site.
			add_filter('post_link', array(& $this,'post_link'), 100, 2);
			add_filter('page_link', array(& $this,'page_link'), 100, 2);
			add_filter('get_pagenum_link', array(& $this,'get_pagenum_link'));

			// Correct categories for wp_list_cats();
			add_filter('list_cats', array(& $this,'list_cats'), 8, 2);
			
			// Correct categories for the_category();
			add_filter('get_category', array(& $this,'get_category'));

			// Miscellaneous
			add_action('init', array(& $this,'init'));
			add_action('plugins_loaded', array(& $this,'plugins_loaded'), 0);
			add_filter('locale', array(& $this, 'locale'), 0);
			add_filter('option_rss_language', array(& $this,'option_rss_language'));

			// Title, description and charset filtering.
			add_filter('option_blogname', array(& $this,'option_blogname'));
			add_filter('option_blogdescription', array(& $this,'option_blogdescription'));
			add_filter('option_blog_charset', array(& $this,'option_blog_charset'));

		}
	}

	// Filters and Actions.
	
	/**
	 * Triggered on the 'activated' hook.
	 * 
	 * Creates synonym entries for those terms that have been entered since 
	 * the last time Gengo was activated. Sends users to the configuration
	 * page.
	 * 
	 * Have to add the prefix again, because $wpdb->prefix isn't set when 
	 * gengo.php is included.
	 *
	 */
	function activated() {
	  global $wp_rewrite, $wpdb;
	  
	  $wp_rewrite->flush_rules();

		if (get_option('gengo_version')) {
			$this->language_table = $wpdb->prefix . $this->language_table;
			$this->term2syn_table = $wpdb->prefix . $this->term2syn_table;
			$this->set_defined_languages();
		  // We are reactivating Gengo, so add category synonym entries for all categories added since Gengo was deactivated.		
			foreach ($this->languages as $language_id => $entry) {
			  if ($language_id == $this->blog_default_language_id) {
			  	$wpdb->query("INSERT INTO $this->term2syn_table(term_id, language_id, synonym, sanitised, description) SELECT t.term_id, $language_id, name, slug, '' FROM $wpdb->terms AS t LEFT JOIN $this->term2syn_table AS t2s ON (t.term_id = t2s.term_id AND t2s.language_id = $language_id) WHERE t2s.term_id IS NULL");
				} else {
					$wpdb->query("INSERT INTO $this->term2syn_table(term_id, language_id, synonym, sanitised, description) SELECT t.term_id, $language_id, '', '', '' FROM $wpdb->terms AS t LEFT JOIN $this->term2syn_table AS t2s ON (t.term_id = t2s.term_id AND t2s.language_id = $language_id) WHERE t2s.term_id IS NULL");
				}
			}
		}
		
		header('Location: admin.php?page=' . GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE);
		die();
	}

	/**
	 * Remove rewrite rules when Gengo is deactivated.
	 *
	 */
	function deactivated() {
	  global $wp_rewrite;
		remove_filter('rewrite_rules_array', array(& $this,'everybody_wants_prosthetic_rewrite_rules'));
	  $wp_rewrite->flush_rules();
	}

	function option_rss_language($language) {
		$codes = explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string);
		return $codes[0];
	}

	/**
	 * Add the Gengo options page to the menu.
	 *
	 */
	function admin_menu() {
		global $language_menu;
		if (current_user_can('configure_gengo')) {
			if (!$language_menu) {
				add_menu_page(__('Language Options', GENGO_DOMAIN), __('Languages', GENGO_DOMAIN), 1, GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE);
				$language_menu = GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE;
			}
			add_submenu_page($language_menu, __('Gengo Languages', GENGO_DOMAIN), __('Languages', GENGO_DOMAIN), 1, GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE);
			add_submenu_page($language_menu, __('Gengo Translations', GENGO_DOMAIN), __('Translations', GENGO_DOMAIN), 1, GENGO_BASE_DIR . GENGO_TRANSLATIONS_PAGE);
			add_submenu_page($language_menu, __('Gengo Synonyms', GENGO_DOMAIN), __('Synonyms', GENGO_DOMAIN), 1, GENGO_BASE_DIR . GENGO_SYNONYMS_PAGE);
			if (current_user_can('modify_gengo_exclusions')) {
		    if (($this->append_urls && !((isset($_POST['gengo_action']) && $_POST['gengo_action'] == "update_appending") && !isset($_POST['gengo_append_urls']))) || (isset($_POST['gengo_append_urls']))) {
					add_submenu_page($language_menu, __('Gengo Exclusions', GENGO_DOMAIN), __('Exclusions', GENGO_DOMAIN), 1, GENGO_BASE_DIR . GENGO_EXCLUSIONS_PAGE);
				}
			}
		}
	}
	
	/**
	 * Print required code in the admin <head> section.
	 *
	 */
	function admin_head() {
		?>
		<script type="text/javascript">
		var script_uri = "<?php echo $this->site_url . '/' . GENGO_DIR . 'gengo.php'; ?>";
		<?php
		if ($count_languages = count($this->languages)) {
			?>
			var languages = new Array (<?php echo $count_languages ?>);
			var language_names = new Array (<?php echo $count_languages ?>);
			var rtl = new Array (<?php echo $count_languages ?>);
			var clashes = new Array (<?php echo $count_languages ?>);
			<?php
			$i = 0;
			foreach ($this->languages as $language_id => $language_object) {
				?>
				languages[<?php echo $i++ ?>] = <?php echo $language_id ?>;
				language_names[<?php echo $language_id ?>] = '<?php echo $language_object->language ?>';
				rtl[<?php echo $language_id ?>] = '<?php echo ($language_object->rtl) ? "rtl" : "ltr"; ?>';
				clashes[<?php echo $language_id ?>] = 'false';
				<?php
			}
		}
		?>
		var delete_summary_message = "<?php _e("You are about to delete this summary.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN) ?>";
		var updated_summary_message = "<?php _e("Summary updated.  Your main post was not saved.", GENGO_DOMAIN) ?>";
		var sidebar_title = "<?php _e("Language", GENGO_DOMAIN) ?>";
		var summary_title = "<?php _e('Summary', GENGO_DOMAIN) ?>";
		var readonly_message = "<?php _e('Read Only', GENGO_DOMAIN) ?>";
		var translation_title = "<?php _e('Translation', GENGO_DOMAIN) ?>";
		var translation_updated_message = "<?php _e("Translation updated.  Your main post was not saved.", GENGO_DOMAIN) ?>";
		</script>
		<?php
	}

	// Place options on the write page and write post pages.
	function post_sidebar() {
		global $wpdb;

		$post_id = $_GET['post'] ? $_GET['post'] : 0;
		?>
		<div id="gengo" class="postbox">
		<h3 id="gengo_sidebar_title"><?php _e('Language', GENGO_DOMAIN) ?></h3>
		<div class="inside" id="gengo_sidebar_updating" style="text-align: center; display: none">
		<img src="../wp-content/plugins/gengo/loading.gif" />
		</div>
		<div class="inside" id="gengo_sidebar_group">
		<?php
		if (count($this->languages)) {
			$default_language_id = $this->get_default_language_id();
			if ($post_language_info = $this->get_post_language_data($post_id, 'l.language_id AS post_language_id, p2l.translation_group AS post_translation_group')) {
				extract($post_language_info, EXTR_OVERWRITE);
			} else {
				$post_language_id = 0;
				$post_translation_group = 0;
			}
			?>
			<input type="hidden" id="gengo_language_id" name="gengo_language_id" value="<?php echo $post_language_id ?>" />
			<?php
			if (!$post_language_id) $post_language_id = $default_language_id;
			?>
			<script type="text/javascript">
			var existing_post_id = <?php echo ($_GET['post']) ? $_GET['post'] : 0 ?>;
			var existing_translation_group = <?php echo $post_translation_group ?>;
			</script>
			<label for="gengo_use_language" class="selectit"><?php _e("This article's language:", GENGO_DOMAIN); ?>
			<?php
			// Show a list of defined languages.
			foreach($this->languages as $language_id => $entry) {
				$selected_language = (($post_language_id == $language_id) || (!$post_language_id && ($default_language_id == $language_id))) ? ' selected="selected"' : '';
				$default_list .= ($default_language_id == $language_id) ? "<option value=\"$language_id\"$selected_language>$entry->language (Default)</option>" : "<option value=\"$language_id\"$selected_language>$entry->language</option>";
			}
			?>
			<select name="gengo_use_language" id="gengo_use_language" onchange="gengo_change_language();"><?php echo $default_list; ?></select></label>
			<?php
			$post_summary_group = ($sum = $wpdb->get_var("SELECT summary_group FROM $this->post2lang_table WHERE post_id = $post_id")) ? $sum : 0;
			?>
			<p><label for="gengo_view_translations" class="selectit"><input type="checkbox" name="gengo_view_translations" id="gengo_view_translations" onclick="if(!this.checked){document.getElementById('gengo_translations_container').style.display = 'none';}else{document.getElementById('gengo_translations_container').style.display = 'block';}" /> <?php _e('View/Edit translations', GENGO_DOMAIN) ?></label></p>
			<div id="gengo_translations_container" style="display: none; padding: 0; ">
				<label for="gengo_translation" class="selectit"><input type="checkbox" name="gengo_translation" id="gengo_translation" onclick="if(!this.checked){document.getElementById('gengo_translation_block').style.display = 'none';document.getElementById('gengo_translation_content_block').style.display = 'none';}else{document.getElementById('gengo_translation_block').style.display = 'block';} gengo_lock_controls(); gengo_refresh_summary_list();"<?php if ($post_translation_group) echo ' checked="checked"'; ?> /> <?php _e('This is a translation', GENGO_DOMAIN) ?></label>
				<input type="hidden" id="gengo_existing_translation_group" name="gengo_existing_translation_group" value="<?php echo $post_translation_group ?>" />
				<div id="gengo_translation_block"<?php if (!$post_translation_group) echo ' style="display: none;"'; ?>>
				<?php $this->generate_translation_lists($post_language_id, $post_translation_group, $post_id) ?>
				</div>
			</div>
			<p style="clear: both;"><label for="gengo_view_summaries" class="selectit"><input type="checkbox" name="gengo_view_summaries" id="gengo_view_summaries" onclick="if(!this.checked){document.getElementById('gengo_summaries_container').style.display = 'none';}else{document.getElementById('gengo_summaries_container').style.display = 'block';}" /> <?php _e('View/Edit summaries', GENGO_DOMAIN) ?></label></p>
			<div id="gengo_summaries_container" style="display: none; padding: 0;">
				<input type="hidden" id="gengo_existing_summary_group" name="gengo_existing_summary_group" value="<?php echo $post_summary_group ?>" />
				<div id="gengo_summary_block" style="padding-top: 0; padding-bottom: 0; border-left: 1px solid #ddd; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd;">
				<?php
				$is_in_group = ($post_translation_group) ? 'true' : 'false';
				$this->generate_summary_lists($post_id, $post_summary_group, $is_in_group, $post_translation_group);
				?>
				</div>
			</div>
			<?php
		} else {
			_e('No languages defined yet.', GENGO_DOMAIN);
		}
		if (current_user_can('configure_gengo')) {
			?><p><a href="admin.php?page=<?php echo GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE ?>"><?php _e('Configure Gengo', GENGO_DOMAIN) ?></a></p><?php
		}
		?>
		</div></div>
		<?php
	}

	/**
	 * Place the second translation panel on the post/page edit screen.
	 *
	 */
	function edit_form_advanced() {
		?>
		<div id="gengo_edit_box" class="postarea">
			<div style="display: none" id="gengo_translation_content_block">
				<div style="float: right" id="gengo_translation_message"></div>
				<h3 id="gengo_translation_legend">Translation</h3>
				<div style="padding: 6px;">
					<textarea onkeydown="$('gengo_update_button').style.display = 'block';$('gengo_translation_message').innerHTML='<em><?php _e('Alterations have not been saved yet!', GENGO_DOMAIN) ?><em>';" style="width: 100%; margin: 0; border: none; padding 0;" rows="10" cols="40" name="gengo_translation_content" id="gengo_translation_content"></textarea>
					<p class="submit">
						<input style="float: right;" id="gengo_cancel_translation_button" name="gengo_cancel_translation_button" type="button" onclick="document.getElementById('gengo_translation_content_block').style.display = 'none';" value="<?php _e('Cancel', GENGO_DOMAIN) ?>" />
						<input style="float: right; font-weight: bold; margin-right: 5px; display: none" id="gengo_update_button" name="gengo_update_button" type="button" onclick="gengo_update_translation_content()" value="<?php _e('Update Translation', GENGO_DOMAIN) ?>" />
					</p>
					<div style="clear: right"></div>
				</div>
			</div>
			<div style="display: none" id="gengo_summary_content_block">
	  		<input type="hidden" name="gengo_summary_id" id="gengo_summary_id" />
	  		<input type="hidden" name="gengo_summary_language_id" id="gengo_summary_language_id" />
				<div style="float: right" id="gengo_summary_message"></div>
				<h3 id="gengo_summary_legend">Summary</h3>
				<div style="padding: 6px">
					<textarea onkeydown="$('gengo_summary_update').style.display = 'block';$('gengo_summary_message').innerHTML='<em><?php _e('Alterations have not been saved yet!', GENGO_DOMAIN) ?><em>';" style="width: 100%; margin: 0; border: none; padding 0pt;" rows="10" cols="40" name="gengo_summary_content" id="gengo_summary_content"></textarea>
					<p class="submit">
						<input style="float: right;" id="gengo_close_summary_button" name="gengo_close_translation_button" type="button" onclick="document.getElementById('gengo_summary_content_block').style.display = 'none';document.getElementById('gengo_summary_content').value = '';" value="<?php _e('Cancel', GENGO_DOMAIN) ?>" />
						<input name="gengo_summary_update" style="float: right; font-weight: bold; margin-right: 5px; display: none" type="button" onclick="gengo_update_summary(); document.getElementById('gengo_summary_content').value = '';" id="gengo_summary_update" value="<?php _e('Update Summary', GENGO_DOMAIN) ?>" />
					</p>
					<div style="clear: right"></div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			function gengo_position_content() { $('postdiv').up().insertBefore($('gengo_edit_box'), $('tagsdiv')); }
			addLoadEvent(gengo_position_content);
		</script> 
		<?php
	}

	/**
	 * Place delete all translations checkbox on the admin screen.
	 *
	 */
	function admin_footer() {
		global $post;
		if (preg_match('|post.php|i', $_SERVER['SCRIPT_NAME']) && ("edit" == $_GET['action'])) {
			?>
			<div id="gengo_delete_translations_block">
				<a id="gengo_delete_translations" onclick="return confirm('<?php printf(__("You are about to delete all translations of the post \'%s\'.\\n\'Cancel\' to stop, \'OK\' to delete", GENGO_DOMAIN), addslashes($post->post_title)) ?>');">Delete all translations</a>
			</div>
			<script type="text/javascript">
			gengo_add_delete_all();
			</script>
			<?php
		}
		?>
		<div id="gengo_ajax_notification" style="position: fixed; left: 10px; bottom: 10px; padding: 5px; display: none; background: #C7DAE2; border: 2px solid #448ABD;">
			<img src="../wp-content/plugins/gengo/loading.gif" />
			<span id="gengo_ajax_notification_message" style="color: #000; margin: 10px; vertical-align: super; font-family: arial,verdana,helevetica; font-size: 12px;"></span>
		</div>
		<div id="gengo_ajax_feedback" style="position: fixed; right: 10px; bottom: 10px; padding: 5px; display: none"></div>
		<?php
	}

	/**
	 * Remove any orphaned summaries.
	 *
	 */
	function remove_orphan_summaries() {
		global $wpdb;

		if ($orphaned_summaries = $wpdb->get_col("SELECT DISTINCT s.summary_group AS summary_group FROM $this->summary_table AS s LEFT JOIN $this->post2lang_table AS p2l ON s.summary_group = p2l.summary_group WHERE p2l.summary_group IS NULL")) {
		  $orphans = implode(',', $orphaned_summaries);
			$wpdb->query("DELETE FROM $this->summary_table WHERE summary_group IN ($orphans)");
		}
	}

	/**
	 * Handle language information when a post is saved.
	 *
	 * @param int $post_id
	 * @return int
	 */
	function edit_post($post_id) {
		global $wpdb;
		if(!isset($post_id)) {
			// We are creating a new post.
			$post_id = $_POST['post_ID'];
			$language_id = isset($_POST['gengo_use_language']) ? $_POST['gengo_use_language'] : $this->get_default_language_id();
			$existing_language_id = isset($_POST['gengo_language_id']) ? $_POST['gengo_language_id'] : 0;
		} else {
			// We are editing a post.  Can't support updating language by XMLRPC at the moment.
			$existing_language_id = isset($_POST['gengo_language_id']) ? $_POST['gengo_language_id'] : $wpdb->get_var("SELECT language_id FROM $this->post2lang_table WHERE post_id = $post_id");
			$language_id = isset($_POST['gengo_use_language']) ? $_POST['gengo_use_language'] : $existing_language_id;
		}
		$existing_translation_group = isset($_POST['gengo_existing_translation_group']) ? $_POST['gengo_existing_translation_group'] : 0;
		$translation_id = $_POST['gengo_translation_post'];

		// Get a group number if we are creating a new one.
		if (isset($_POST['gengo_translation'])) {
			if (isset($_POST['gengo_translation_group']) && ($_POST['gengo_translation_group'] == 0) && $translation_id) {
				$new_group = true;
				$translation_group = $wpdb->get_var("SELECT MAX(translation_group) FROM $this->post2lang_table") + 1;
			} else {
				$translation_group = ($_POST['gengo_translation_group']) ? $_POST['gengo_translation_group'] : 0;
			}
		} else {
			$translation_group = 0;
		}

		// If the language and translation hasn't changed, do nothing.
		if ($language_id == $existing_language_id && $translation_group == $existing_translation_group) return $post_id;

		// Insert or update the new or altered data.
		if (!$wpdb->query("UPDATE $this->post2lang_table SET language_id = $language_id, translation_group = $translation_group WHERE post_id = $post_id")) {
			$wpdb->query("INSERT INTO $this->post2lang_table(post_id, language_id, translation_group, summary_group) VALUES ($post_id, $language_id, $translation_group, 0)");
		}
		// Check if we now don't need a translation group for the one that was deleted.
		if ($existing_translation_group && ($existing_translation_group != $translation_group)) {
			if ('1' == $wpdb->get_var("SELECT COUNT(*) FROM $this->post2lang_table WHERE translation_group = $existing_translation_group")) {
				$wpdb->query("UPDATE $this->post2lang_table SET translation_group = 0 WHERE translation_group = $existing_translation_group LIMIT 1");
			}
		}

		// Put the other post in the same translation group if we have just created a new one.
		if ($new_group) $wpdb->query("UPDATE $this->post2lang_table SET translation_group = $translation_group WHERE post_id = $translation_id LIMIT 1");

		if (!$summary_group = $wpdb->get_var("SELECT summary_group FROM $this->post2lang_table AS p2l WHERE p2l.translation_group = $translation_group AND p2l.post_id != $post_id AND p2l.translation_group != 0 LIMIT 1")) {
			$summary_group = (!$existing_translation_group) ? $wpdb->get_var("SELECT summary_group FROM $this->post2lang_table AS p2l WHERE p2l.post_id = $post_id LIMIT 1") : 0;
		}

		// Make sure the summary group is correct..
		$wpdb->query("UPDATE $this->post2lang_table SET summary_group = $summary_group WHERE post_id = $post_id LIMIT 1");
		$this->remove_orphan_summaries();
		return $post_id;
	}

	/**
	 * Handle language alterations when a post is deleted.
	 *
	 * @param int $post_id
	 */
	function delete_post($post_id) {
		global $wpdb, $current_user;

		if (!$deleted_translation_group = $this->get_post_language_data($post_id, "p2l.translation_group")) {
			$deleted_translation_group = 0;
		}
		// @TODO: Why isn't this working?
		if ($_GET['gengo_delete_all_translations'] && 'true' == $_GET['gengo_delete_all_translations']) {
			if ($translations = $this->get_the_translations($post_id)) {
				foreach ($translations as $translation) {
					if (!current_user_can('edit_post', $translation['translation_id'])) continue;

					// Temporarily unregister the action to prevent an infinite loop and delete this translation.
					remove_action('delete_post', array(&$this, 'delete_post'));
					wp_delete_post($translation['translation_id']);
					add_action('delete_post', array(&$this, 'delete_post'));
					$post_id .= ", $translation[translation_id]";
				}
			}
		}
		$wpdb->query("DELETE FROM $this->post2lang_table WHERE post_id IN ($post_id)");

		if ('1' == $wpdb->get_var("SELECT COUNT(*) FROM $this->post2lang_table WHERE translation_group = $deleted_translation_group")) {
			$wpdb->query("UPDATE $this->post2lang_table SET translation_group = 0 WHERE translation_group = $deleted_translation_group");
		}
		// Remove summaries that have been orphaned.
		$this->remove_orphan_summaries();
	}

	/**
	 * Add language table columns to the manage posts table.
	 *
	 * @param array $post_columns
	 * @return array
	 */
	function manage_posts_columns(& $post_columns) {
		$post_columns['gengo_language'] = __('Language', GENGO_DOMAIN);
		$post_columns['gengo_translations'] = __('Translations', GENGO_DOMAIN);
		$post_columns['gengo_summaries'] = __('Summaries', GENGO_DOMAIN);
		$this->mixed_categories = true;
		return $post_columns;
	}

	/**
	 * Add language information to the manage posts table.
	 *
	 * @param string $column_name
	 * @param int $id
	 */
	function manage_posts_custom_column($column_name, $id) {
		if ('gengo_language' == $column_name) {
			echo $this->get_post_language_data($id, 'l.language');
		} elseif ('gengo_translations' == $column_name) {
			if ($translations = $this->get_the_translations($id)) {
				foreach ($translations as $translation) {
					?><a href="<?= get_permalink($translation['translation_id']) ?>" title="<?php printf(__('View the %s translation of this article.', GENGO_DOMAIN), $this->languages[$translation['translation_language']]->language) ?>"><?= $this->languages[$translation['translation_language']]->code ?></a> <?php
				}
			}
		} elseif ('gengo_summaries' == $column_name) {
			$summaries = $this->get_the_summaries($id);
			foreach ($summaries as $summary) echo '<a title="' . $summary['summary'] . '">' . $this->languages[$summary['language_id']]->code . '</a> ';
		}
	}

	/**
	 * Rewrite Rules
	 * Modified from original source courtesy of Andy Skelton.  Solved a major headache.
	 *
	 * @param array $rules
	 * @return array
	 */
	function everybody_wants_prosthetic_rewrite_rules($rules) {
	  global $wp_rewrite, $wp_version;
		if (!$language_count = count($this->languages)) return $rules;

		foreach ($this->languages as $entry) $codes .= $codes ? '|' . $entry->code : $entry->code;
		for ($i = 0; $i < $language_count; $i++) $code_string .= $i ? "(\+($codes))?" : "($codes)?";

		if ($wp_rewrite->using_index_permalinks()) $index = $wp_rewrite->index . '/';
		$newrules["$index($code_string)/?$"] = 'index.php?language=$matches[1]';
		foreach ($rules as $match => $query) {
			preg_match_all('#matches\[(\d+)\]#', $query, $matches);
			$number = count($matches[1]) + 1;
			$newquery = urldecode(add_query_arg('language', '$' . rawurlencode("matches[$number]"), $query));

			// For terse rewrite rules, we have to add an extra one to make sure language codes aren't matched as page names.
			if ($this->starts_with($match, '(.+?)(/[0-9]+)?')) {
				$newrules[str_replace('/?$', "/($code_string)/?$", $match)] = $newquery;
			}

			$newmatch = str_replace('/?$', "/?($code_string)?/?$", $match);
			$newrules[$newmatch] = $newquery;
		}
		return $newrules;
	}

	/**
	 * Check if a given string starts with another string.
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return string
	 */
	function starts_with($haystack, $needle) {
		return (strpos($haystack, $needle) === 0);
	}
	
	/**
	 * Adds language information to page links.
	 *
	 * @param string $link
	 * @param int $id
	 * @return string
	 */
	function page_link($link, $id) {
		global $wpdb, $wp_query;
		if (!$id) return $link;
		
		if (get_option('page_for_posts') == $id) {
			// Append all viewable codes.
			return $this->append_link_language($link, $this->viewable_code_string);
		}
		
		// First try and get the code from the posts on display.
		if ($wp_query->posts) {
			foreach ($wp_query->posts as $post) {
				if ($post->ID == $id) {
					$code = $post->code;
					break;
				}
			}
		}
		
		if (!$code && $this->post_language_cache) {
			// Next try gengo's post language cache - should catch all translations.
			foreach ($this->post_language_cache as $cache) {
				if ($cache->translations) {
					foreach ($cache->translations as $post_id => $translation) {
						if ($post_id == $id) {
						  $code = $this->languages[$translation['translation_language']]->code;
						}
					}
				}
			}
		}
		
		if (!$code && $this->page_language_cache) {
			// Finally try gengo's page language cache - should catch all pages.
			foreach ($this->page_language_cache as $page_id => $page_info) {
				if ($page_id == $id) {
					$code = $page_info['code'];
					break;
				}
			}
		}

		if (!$code) {
			// If all else fails, take the db hit, just to be safe.
		  $code = $wpdb->get_var("SELECT code FROM $this->language_table AS l INNER JOIN $this->post2lang_table AS p2l ON l.language_id = p2l.language_id WHERE p2l.post_id = $id LIMIT 1");
		}

		return $this->append_link_language($link, $code);
	}

	/**
	 * Adds language information to the permalink.
	 *
	 * @param string $link
	 * @param int $post
	 * @return string
	 */
	function post_link($link, $post) {
	  return $this->page_link($link, $post->ID);
	}

	/**
	 * Filters the paged archive link by the get_pagenum_link filter.
	 *
	 * @param string $link
	 * @return string
	 */
	function get_pagenum_link($link){
		global $wpdb;
		if(get_option('permalink_structure')!=""):
		$block = explode("/page",$link);
		if(isset($block[2]) && $block[2]!=""): 
			$newurl = $this->append_links($block[0]."/page".$block[2]);
		elseif($block[1]=="/2/" && !isset($block[2])):
			$newurl = $block[0]."/page".$block[1];
		else: $newurl = $this->append_links($block[0]); endif;
		return $newurl;
		else: return $link; endif;
	}

	/**
	 * Append language codes to other links (wrapper)
	 *
	 * @param string $link
	 * @see append_link_language();
	 * @return string
	 */
	function append_links($link) {
		return $this->append_link_language($link, $this->viewable_code_string);
	}

	/**
	 * Appends language code to a given link
	 *
	 * @param string $link
	 * @param string $code
	 * @return string
	 */
	function append_link_language($link, $code, $regular_links = false) {
		global $wp_rewrite;
		$append = $this->append_urls;
		if (!$code || (!$append && !$this->forced_append)) return $link;
		$this->forced_append = false;
		if ((!$query_string = strpos($link, '?')) && $wp_rewrite->permalink_structure) {
			if($this->is_excluded_url($link)) return trailingslashit($link); 
				else return trailingslashit($link) . "$code/";
		} else{
			if ($query_string) {
				if ($this->strict_links && !$regular_links) {
					if($this->is_excluded_url($link)) return $link; else return $link . "&amp;language=$code";
				} else {
					if($this->is_excluded_url($link)) return $link; else return $link . "&language=$code";
				}
			}
			if($this->is_excluded_url($link)) return trailingslashit($link); 
				else return trailingslashit($link)."?language=$code";
		}
	}
		
	/**
	 * Filters the pages that the visitor can read, according to their language preference.
	 *
	 * @param array $pages
	 * @param array $arguments
	 * @see wp_list_pages();
	 * @return array
	 */
	function get_pages($pages,$arguments) {
	  global $wpdb;
		if (!$pages) return $pages;
	  foreach ($pages as $page) {
			$page_ids[] = $page->ID;
		}
		$sort_column = $arguments['sort_column'];
		$sort_order = $arguments['sort_order'];
		$page_ids = implode(',', $page_ids);
		$where = $this->posts_where("WHERE p.ID IN ($page_ids)", true);
		$pages = $wpdb->get_results("SELECT p.*, p2l.language_id FROM $wpdb->posts AS p INNER JOIN $this->post2lang_table AS p2l ON p.ID = p2l.post_id $where ORDER BY $sort_column $sort_order");

		foreach ($pages as $page) {
			$this->page_language_cache[$page->ID]['code'] = $this->languages[$page->language_id]->code;
		}

		return $pages;
	}

  /**
   * Called from wp_list_categories.
   *
   * @todo check if this is still used or not
   * @param array $categories
   * @param array $taxonomies
   * @param array $arguments
   * @see wp_list_categories();
   * @return array
   */
  function get_categories($categories, $taxonomies, $arguments) {
    global $wpdb;

		if (('post' != $arguments['type']) || !$categories) return $categories;

		foreach ($categories as $category) {
			$category_ids[] = $category->term_id;
		}
		$category_ids = implode(',', $category_ids);

		$viewable_codes = explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string);
		foreach ($viewable_codes as $viewable_code) $ids[] = $this->codes2ids[$viewable_code];
		$language_ids = implode(',', $ids);
		if ($language_ids && $counted_categories = $wpdb->get_results("SELECT c.cat_ID, p.post_type, COUNT(DISTINCT (CASE WHEN translation_group > 0 THEN -translation_group ELSE p2l.post_id END)) as category_count FROM $this->post2lang_table p2l INNER JOIN $wpdb->post2cat p2c ON p2c.post_id = p2l.post_id INNER JOIN $wpdb->posts p ON p2l.post_id = p.ID INNER JOIN $wpdb->categories AS c ON p2c.category_id = c.cat_ID WHERE p2l.language_id IN ($language_ids) AND p2c.category_id IN ($category_ids) AND p.post_status = 'publish' GROUP BY c.cat_ID, p.post_type $sort_column $sort_order")) {
			foreach ($counted_categories as $counted_category) {
			  $hashed_counts[$counted_category->post_type][$counted_category->cat_ID] = $counted_category->category_count;
			}
			$post_types = array_keys($hashed_counts);

			foreach ($categories as $cat) {
				foreach ($post_types as $post_type) {
					$count_slot = ('post' == $post_type) ? 'category_count' : 'category_' . $post_type . 'count';
					$cat->$count_slot = $hashed_counts[$post_type][$cat->term_id];
				}
			}

			if ($arguments['hide_empty'] || $arguments['pad_counts']) {
				$protected = -1;
			  $pad = array();
			  $keys = array_reverse(array_keys($categories));
			  foreach ($keys as $k) {
					$cat = $categories[$k];
					if ($cat->category_count || ($cat->cat_ID == $protected)) {
						if ($arguments['hierarchical']) {
							$protected = $cat->category_parent;
						}
						if ($arguments['pad_counts']) {
							// Known bug: if a post belongs to a category and its parent, counted twice. Not worth the trouble.
							$cat->category_count += $pad[$cat->cat_ID];
			        if ($cat->category_parent) {
								$pad[$cat->category_parent] += $cat->category_count;
							}
			      }
			    } elseif ($arguments['hide_empty']) {
						unset($categories[$k]);
			    }
			  }
			}
		}
		return $categories;
  }

  /**
   * Removes the language code from the comment permalink, so we don't get language codes inline.
   *
   * @param string $url
   * @return string
   */
	function post_comments_feed_link($url) {
	  global $post;
	  if (get_option('permalink_structure')) $url = preg_replace("|/$post->code/$|", '/', $url);
		return $this->append_link_language($url, $post->code);
	}

  /**
   * Removes the language code from the trackback permalink, so we don't get language codes inline.
   *
   * @param string $url
   * @return string
   */
	function trackback_url($url) {
	  global $post;
	  if (get_option('permalink_structure')) $url = preg_replace("|/$post->code/trackback/$|", '/trackback/', $url);
		return $this->append_link_language($url, $post->code);
	}	
	

	/**
	 * Adds the language query variable for routing.
	 *
	 * @param array $vars
	 * @return array
	 */
	function query_vars($vars) {
		$vars[] = 'language';
		return $vars;
	}

	/**
	 * Adds language tables into the posts query for the main post query.
	 * 
	 * @param string $join
	 * @return string
	 */	
	function posts_join($join) {
		global $wpdb;
		if ($this->language_preference) return $join . " INNER JOIN $this->post2lang_table p2l ON $wpdb->posts.ID = p2l.post_id INNER JOIN $this->language_table l ON p2l.language_id = l.language_id";
		return $join;
	}
	
	function posts_request($request){
		global $wpdb;
#		if(is_feed()) return false; else return $request;
		return $request;
	}

	/**
	 * Adds language tables into the posts query for the previous and next post.
	 * 
	 * Can't be the same as posts_join because of a difference in the alias for $wpdb->posts.
	 *
	 * @param string $join
	 * @return string
	 */
	function previous_next_posts_join($join) {
		global $wpdb;
		if ($this->language_preference) return $join . " INNER JOIN $this->post2lang_table p2l ON p.ID = p2l.post_id INNER JOIN $this->language_table l ON p2l.language_id = l.language_id";
		return $join;
	}
	
	/**
	 * Appends language specific information to the posts query.
	 *
	 * @param string $fields
	 * @return string
	 */
	function posts_fields($fields) {
		global $wpdb;
		if ($this->language_preference) return "$fields, l.*, p2l.translation_group";
		return $fields;
	}

	/**
	 * Add language constraint to the posts query.
	 *
	 * @param string $original_where
	 * @param boolean $forced
	 * @return string
	 */
	function posts_where($original_where, $forced = false) {
		global $wp_query;
		if (($language_id = $this->language_preference_id[0]) && (!isset($this->static_front) || $forced)) {
			if (!$this->static_front && ((1 == count($this->language_preference)) || is_single() || is_page())) {
				return $original_where . " AND p2l.language_id = '$language_id'";
			} elseif ($this->searching) {
			  return $original_where . " AND p2l.language_id IN (" . implode(", ", $this->language_preference_id) . ")";
			} else {
				foreach ($this->language_preference_id as $key => $language_id) {
					if (0 == $key) $where = "p2l.language_id = $language_id";
					else $where .= " OR (p2l.language_id = $language_id AND (translation_group = 0 OR translation_group IN (SELECT translation_group FROM (SELECT translation_group FROM $this->post2lang_table GROUP  BY translation_group HAVING 0 = COUNT(CASE $exclusions END)) AS x$language_id)))";
					$exclusions .= " WHEN language_id = $language_id THEN 1";
				}
				return $original_where . " AND ($where)";
			}
		}
		return $original_where;
	}
	
	/**
	 * Add language constraint to the previous/next posts query.
	 *
	 * @param string $original_where
	 * @return string
	 */
	function previous_next_posts_where($original_where) {
		if ($this->language_preference_id) {
		  $language_ids = implode(',', $this->language_preference_id);
			return $original_where . " AND l.language_id IN ($language_ids)";
		}
		return $original_where;
	}

	/**
	 * Translate category synonyms if necessary.
	 *
	 * @param array $query_object
	 */
	function parse_query(& $query_object) {
		global $wpdb;
		if (($cat_name = $query_object->query_vars['category_name'])) {
		  $cat_names = explode('/', $cat_name);
			$viewable_codes = explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string);
			foreach ($viewable_codes as $viewable_code) $ids[] = $this->codes2ids[$viewable_code];
			$language_ids = implode(',', $ids);
			$cat_name_string = "'" . implode("', '", array_map('strtolower', array_map('urlencode', $cat_names))) . "'";
			if ($cat_synonyms = $wpdb->get_col("SELECT name FROM $wpdb->terms AS c INNER JOIN $this->term2syn_table AS syn ON c.term_id = syn.term_id WHERE syn.sanitised IN ($cat_name_string) AND syn.language_id IN ($language_ids)")) {
				foreach ($cat_names as $name) $matches[] = "/$name/";
				$query_object->query_vars['category_name'] = preg_replace($matches, $cat_synonyms, $query_object->query_vars['category_name']);
			}
		}
		if (($tag_name = $query_object->query_vars['tag'])) {
		  $tag_names = explode('/', $tag_name);
			$viewable_codes = explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string);
			foreach ($viewable_codes as $viewable_code) $ids[] = $this->codes2ids[$viewable_code];
			$language_ids = implode(',', $ids);
			$tag_name_string = "'" . implode("', '", array_map('strtolower', array_map('urlencode', $tag_names))) . "'";
			if ($tag_synonyms = $wpdb->get_col("SELECT slug FROM $wpdb->terms AS c INNER JOIN $this->term2syn_table AS syn ON c.term_id = syn.term_id WHERE syn.sanitised IN ($tag_name_string) AND syn.language_id IN ($language_ids)")) {
				foreach ($tag_names as $name) $matches[] = "/$name/";
				$query_object->query_vars['tag'] = preg_replace($matches, $tag_synonyms, $query_object->query_vars['tag']);
			}
		}

	}

	/**
	 * Check correct language in the query string
	 *
	 * @param string $query_string
	 * @return string
	 */
	function query_string($query_string) {
		global $wpdb;
		parse_str($query_string, $vars);
		if (!isset($this->single_post_corrected) && (isset($vars['p']) || isset($vars['name']) || isset($vars['pagename']) || isset($vars['page_id']))) {
			if (isset($vars['p'])) {
				$where = "p2l.post_id = $vars[p]";
			} elseif (isset($vars['page_id'])) {
				if ('page' == get_option('show_on_front') && ($vars['page_id'] == get_option('page_for_posts'))) return $query_string;
				$where = "p2l.post_id = $vars[page_id]";
			} elseif (isset($vars['name'])) {
				if ('page' == get_option('show_on_front') && ($wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$vars[name]' AND post_type = 'page'") == get_option('page_for_posts'))) return $query_string;
				$where = "p.post_name = '$vars[name]'";
				$join = "INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id";
			} else {
				$value = $vars['pagename'];
				if ('page' == get_option('show_on_front') && ($wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$value' AND post_type = 'page'") == get_option('page_for_posts'))) return $query_string;
			  if (($position = strpos($parser->query_vars['pagename'], '/')) !== false) {
					$value = substr($value, $position + 1);
				}
				$where = "p.post_name = '$value'";
				$join = "INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id";
			}
			if (($language_ids = $wpdb->get_col("SELECT language_id FROM $this->post2lang_table AS p2l $join WHERE $where")) && (!array_intersect($language_ids, $this->language_preference_id))) {
				if ($this->language_supplied) {
			    if ($translation_id = $wpdb->get_var("SELECT p2l2.post_id FROM $this->post2lang_table p2l INNER JOIN $this->post2lang_table p2l2 ON (p2l.translation_group = p2l2.translation_group AND p2l.translation_group != 0) $join WHERE $where AND p2l2.language_id = {$this->language_preference_id[0]}")) {
			      // A translation exists in the language specified, so just go straight there.
						$this->strict_links = false;
						header('Location: ' . get_permalink($translation_id));
						die();
					} else {
				    // There's no translation in the language specified, so just remove it and redirect.
				    if ($_SERVER['QUERY_STRING']) {
							$_SERVER['REQUEST_URI'] = remove_query_arg('language', $_SERVER['REQUEST_URI']);
						} else {
							$request = trailingslashit($_SERVER['REQUEST_URI']);
							$position = strrpos(substr($request, 0, -1), '/');
							$_SERVER['REQUEST_URI'] = substr($request, 0, $position + 1);
						}
					}
				}
				$this->forced_append = true;
				$location = $this->append_link_language($_SERVER['REQUEST_URI'], $this->languages[$language_id]->code, true);
				header("Location: $location");
				die();
			}
		}
		return $query_string;
	}

	/**
	 * Place default language option on the profile page.
	 *
	 */
	function profile_personal_options() {
		global $current_user;

		if (count($this->languages)) {
			$default_language_id = $this->get_default_language_id();
			foreach($this->languages as $language_id => $entry) {
				$default_list .= ($default_language_id == $language_id) ? "<option value=\"$language_id\" selected=\"selected\">$entry->language</option>" : "<option value=\"$language_id\">$entry->language</option>";
			}
			?>
			<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Default language:', GENGO_DOMAIN) ?></th>
				<td><label for="gengo_default_language"><select id="language" name="gengo_default_language"><?php echo $default_list; ?></select> <?php _e('Browse website and administration area in this language', GENGO_DOMAIN) ?></label></td>
			</tr>
			</table>
			<?php
		}
	}

	/**
	 * Handle updated personal default.
	 *
 	 */
	function personal_options_update() {
		global $current_user;
		update_usermeta($current_user->id, 'gengo_default_language', $_POST['gengo_default_language']);
	}

	/**
	 * Clean up the default language option for a deleted user.
	 *
	 * @param string $user_id
 	 */
	function delete_user($user_id) {
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key = 'gengo_default_language' AND user_id = $user_id");
	}

	/**
	 * Adds language preference.
	 *
	 * @param string $language_id
	 * @see set_language_preference();
 	 */
	function add_language_preference($language_id) {
		$this->language_preference[] = $this->languages[$language_id];
		$this->language_preference_id[] = $language_id;
	}

	/**
	 * Set language preference for codes.
	 *
	 * @param array $codes
	 * @see add_language_preference();
 	 */
	function set_language_preference($codes) {
		foreach ($codes as $code) $this->add_language_preference($this->codes2ids[$code]);
	}

	/**
	 * Save the language preference in a cookie.
	 *
	 * @param array $original_codes
	 * @see set_language_preference();
 	 */
	function store_language_preference($original_codes) {
		if ('1' == get_option('gengo_allow_multiread')) {
			if ($existing_cookie = trim($_COOKIE[$this->language_cookie])) {
				$codes = array_unique(array_merge(explode(GENGO_LANGUAGE_DIVIDER, $existing_cookie), $original_codes));
			} else {
				$codes = $original_codes;
			}
		} else {
			$original_codes = $codes = array($original_codes[0]);
		}
		$this->viewable_code_string = (count($codes) > 1) ? implode(GENGO_LANGUAGE_DIVIDER, $codes) : $codes[0];
		if (!isset($this->preview) && !isset($this->error)) {
			setcookie($this->language_cookie, $this->viewable_code_string, time() + 30000000, COOKIEPATH);
		}
		$this->set_language_preference($original_codes);
	}

	/**
	 * Add semantic links to the head of a page with alternate translations.
	 *
	 */
	function wp_head() {
		global $wp_query, $wp_rewrite, $wpdb;
		
		if ($wp_rewrite->using_index_permalinks()) $index = "/$wp_rewrite->index";

		// Populate a temporary cache:
		if (isset($gengo->language) && $wp_query->posts) {
			foreach ($wp_query->posts as $post) {
				$post_ids[] = $post->ID;
			}
			$post_ids = implode(',', $post_ids);
			$language_ids = implode(',', $this->language_preference_id);
	
			if ($results = $wpdb->get_results("SELECT p2l.post_id AS original_post_id, p.ID AS translation_post_id, p.comment_count AS translation_comments, u.display_name AS translation_post_author, p.post_date AS translation_date, p2l2.language_id AS translation_language_id, s.summary_id, s.language_id AS summary_language_id, s.summary FROM $this->post2lang_table AS p2l LEFT JOIN $this->post2lang_table AS p2l2 ON (p2l.translation_group = p2l2.translation_group AND p2l.translation_group != 0 AND p2l.language_id != p2l2.language_id) LEFT JOIN $wpdb->posts AS p ON p2l2.post_id = p.ID LEFT JOIN $wpdb->users AS u ON u.ID = p.post_author LEFT JOIN $this->summary_table AS s ON p2l.summary_group = s.summary_group WHERE p2l.post_id IN ($post_ids) AND (s.language_id IS NULL OR s.language_id IN ($language_ids))")) {
				foreach ($results as $result) {
					if ($result->translation_post_id) {
						$this->post_language_cache[$result->original_post_id]->translations[$result->translation_post_id] = array(
							'translation_id' => $result->translation_id,
							'translation_date' => $result->translation_date,
							'translation_author' => $result->translation_post_author,
							'translation_language' => $result->translation_language_id,
							'translation_comments' => $result->translation_comments
						);
					}
					if ($result->summary_id) {
						$this->post_language_cache[$result->original_post_id]->summaries[$result->summary_id] = array(
							'summary_language' => $result->summary_language_id,
							'summary' => $result->summary
						);
					}
				}
			}
		}
		
		$code = (1 == count($this->language_preference_id)) ? $this->languages[$this->language_preference_id[0]]->code : implode(',', explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string));
		?>
		<meta http-equiv="Content-Language" content="<?php echo $code?>" />
		<script type="text/javascript">
		var add_message = '<?php _e("Add", GENGO_DOMAIN) ?>';
		var up_message = '<?php _e("Up", GENGO_DOMAIN) ?>';
		var down_message = '<?php _e("Down", GENGO_DOMAIN) ?>';
		var x_message = '<?php _e("x", GENGO_DOMAIN) ?>';
		var cookie_name = '<?php echo $this->language_cookie ?>';
		var cookie_path = '<?php echo COOKIEPATH ?>';
		var encoded_divider = '<?php echo urlencode(GENGO_LANGUAGE_DIVIDER) ?>';
		var language_divider = '<?php echo GENGO_LANGUAGE_DIVIDER ?>';
		var save_error_message = '<?php _e("You must choose at least one language to view in!", GENGO_DOMAIN)?>';
		var site_home = '<?php echo $this->site_home . $index ?>';
	    var original_codes = '<?php echo $this->viewable_code_string ?>';
		<?php
		if ($count_languages = count($this->languages)) {
			?>
		var language_codes = new Array (<?php echo $count_languages ?>);
		var language_ids = new Array (<?php echo $count_languages ?>);
			<?php
			foreach ($this->languages as $language_id => $language_object) {
				?>
		language_codes[<?php echo $language_id ?>] = '<?php echo $language_object->code ?>';
		language_ids['<?php echo $language_object->code ?>'] = '<?php echo $language_id ?>';
				<?php
			}
		}
		?>
		</script>
		<?php
		if (is_single() || is_page()) {
			if ($translations = $this->post_language_cache[$wp_query->posts[0]->ID]->translations) {
				foreach ($translations as $translation_id => $translation) {
					echo $this->translation_link(array('translation_id' => $translation_id, 'translation_language' => $translation['translation_language']), 'link');
				}
			}
			if ($summaries = $this->post_language_cache[$wp_query->posts[0]->ID]->summaries) {
				foreach ($summaries as $summary) {
					if ($summary->summary_language == $wp_query->posts[0]->language_id) {
						?><meta name="Description" content="<?= $summary ?>" /><?php
						break;
					}
				}
			}
		} elseif (is_home()) {
			foreach ($this->languages as $language_id => $entry) {
			  if (in_array($language_id, $this->language_preference_id)) continue;
				echo $this->translation_link(array('translation_language' => $language_id), 'home_link');
			}
		}
		foreach ($wp_query->posts as $post) {
			$ids[] = $post->ID;
		}
	}

	/**
	 * Adds language information whenever terms are created.
	 *
	 * @param int $term_id
	 * @param int $term_taxonomy_id
	 */
	function create_term($term_id, $term_taxonomy_id) {
		global $wpdb;
		$language_preference_id = $this->language_preference_id[0];
		$result = $wpdb->get_row("SELECT t.name, t.slug, ta.description FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy ta ON (ta.term_id = t.term_id) WHERE t.term_id = $term_id", ARRAY_A);
		$wpdb->query("INSERT INTO $this->term2syn_table (term_id, language_id, synonym, sanitised, description) VALUES ($term_id, '".$language_preference_id."', '".$result['name']."', '".$result['slug']."', '".$result['description']."')");
		foreach ($this->languages as $language_id => $entry) {
			if ($language_id != $language_preference_id) {
				$values[] = "($term_id, $language_id, '".$result['name']."', '".$result['slug']."')";
			}
		}
		if ($values) {
			$values = implode(',', $values);
			$wpdb->query("INSERT INTO $this->term2syn_table (term_id, language_id, synonym, sanitised) VALUES $values");
		}
	}

	/**
	 * Adds language information whenever terms are edited.
	 *
	 * @param int $term_id
	 * @param int $term_taxonomy_id
	 */
	function edit_term($term_id, $term_taxonomy_id) {
		global $wpdb;
		$language_preference_id = $this->language_preference_id[0];
		$result = $wpdb->get_row("SELECT t.name, t.slug, ta.description FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy ta ON (ta.term_id = t.term_id) WHERE t.term_id = $term_id", ARRAY_A);
		$wpdb->query("UPDATE $this->term2syn_table SET synonym='".$result['name']."', sanitised='".$result['slug']."', description='".addslashes($result['description'])."' WHERE term_id = $term_id AND language_id = $language_preference_id");
	}

	/**
	 * Delete all the synonyms for this category.
	 *
	 * @param string $id
 	 */
	function delete_term($id) {
		global $wpdb;
		$wpdb->query("DELETE FROM $this->term2syn_table WHERE term_id = $id");
	}

	/**
	 * Translate the categories in lists, if necessary.
	 * list_cats is sometimes called with the entire list, which we don't need.
	 *
	 * @param string $name
	 * @param string $category
 	 */
	function list_cats($name, $category = '') {
		global $wpdb, $wp_object_cache;
		
		if (!$category) return $name;
		return $wp_object_cache->cache['category'][$category->term_id]->name;
	}

	/**
	 * Repopulates WP's own category cache with translated names, nicenames and descriptions.
	 *
	 * @param array $category_data
	 * @return array $category_data
 	 */
	function get_category($category_data) {
		global $wpdb, $wp_object_cache, $post;

		if (isset($this->mixed_categories)) {
			// For the manage posts list, display the categories in the language of that post.
			if ($translated_categories = $wpdb->get_row("
			SELECT t2s.synonym, t2s.sanitised, t2s.description FROM $this->term2syn_table AS t2s 
			INNER JOIN $wpdb->term_taxonomy AS tt ON t2s.term_id = tt.term_id 
			INNER JOIN $this->post2lang_table AS p2l ON (t2s.language_id = p2l.language_id) 
			WHERE p2l.post_id = $post->ID AND t2s.term_id = $category_data->term_id")) {
				print_r($translated_categories); die();
				$category_data->name = $translated_categories->synonym;
				$category_data->slug = $translated_categories->sanitised;
				$category_data->description = $translated_categories->description;
			}
		} elseif ((!isset($this->categories_cached)) && ($category_cache =& $wp_object_cache->cache['category']) && ($language_id = $this->language_preference_id[0])) {
	    foreach ($category_cache as $cat) {
				if ($cat->term_id) $category_ids[] = $cat->term_id;
			}
			$category_ids = implode(', ', $category_ids);
			if ($translated_categories = $wpdb->get_results("SELECT term_id, synonym, sanitised FROM $this->term2syn_table WHERE term_id IN ($category_ids) AND language_id = $language_id")) {
				foreach ($translated_categories as $translated_cat) {
					// $reference =& $category_cache[$translated_cat->term_id];
					if ($translated_cat->synonym) $category_cache[$translated_cat->term_id]->name = $translated_cat->synonym;
					if ($translated_cat->sanitised) $category_cache[$translated_cat->term_id]->slug = $translated_cat->sanitised;
					if ($translated_cat->description) $category_cache[$translated_cat->term_id]->description = $translated_cat->description;
					//unset($reference);
				}
			}
			$this->categories_cached = true;
			$category_data = $wp_object_cache->cache['category'][$category_data->term_id];
		}
		return $category_data;
	}
	
	/**
	 * Filters get_term with term cache.
	 *
	 * @param string $term
	 * @param string $taxonomy
	 * @return string $term;
 	 */
	function get_term($term, $taxonomy) {
		global $wpdb;
		
		if (!isset($this->term_cache[$term->term_id])) {
			$this->term_cache[$term->term_id] = $term;
			$language_id = $this->language_preference_id[0];
			if ($translated_term = $wpdb->get_row("SELECT synonym, sanitised, description FROM $this->term2syn_table WHERE term_id = $term->term_id AND language_id = $language_id")) {
				$this->term_cache[$term->term_id]->name = $translated_term->synonym;
				$this->term_cache[$term->term_id]->slug = $translated_term->sanitised;
				$this->term_cache[$term->term_id]->description = $translated_term->description;
			}
		}
		$term = $this->term_cache[$term->term_id];

		return $term;
	}
	
	/**
	 * Place Gengo information in the activity box on the Dashboard.
	 *
 	 */
	function activity_box_end() {
		global $wpdb;
		if (!$language_count = count($this->languages)) return;
		$blog_default_language = $this->languages[$this->blog_default_language_id]->language;
		$default_language = $this->languages[$this->get_default_language_id()]->language;
		$profile_link = '<a href="' . $this->site_home . '/wp-admin/profile.php#language">' . $default_language . '</a>';
		$language_link = $language_count . ' ';
		if (current_user_can('configure_gengo')) {
			$open = '<a href="admin.php?page=' . GENGO_BASE_DIR . GENGO_LANGUAGES_PAGE . '">';
			$close = '</a>';
		}
		$language_link .= $open . __('language(s)', GENGO_DOMAIN) . $close;
		?>
		<div>
		<h3><?php _e('Languages', GENGO_DOMAIN); ?></h3>
		<p><?php printf(__("There are %s defined, of which the blog default is %s and your default is %s.", GENGO_DOMAIN), $language_link, $blog_default_language, $profile_link); ?></p>
		</div>
		<?php
	}

	/**
	 * Define the ability to configure Gengo.
	 *
	 * @todo look again role manager
 	 */
	function init() {
		load_plugin_textdomain(GENGO_DOMAIN, GENGO_LANGUAGES_DIR);
    if (isset($_POST['gengo_uninstall_submit'])) {
    	if (current_user_can('uninstall_gengo')) {
    		$this->uninstall();
    	} else {
    		$this->error_message(__("You don't have permission to uninstall Gengo!", GENGO_DOMAIN));
    		die;
    	}
    }
		wp_enqueue_script('prototype');
		wp_enqueue_script('gengo_common', '/' . GENGO_DIR . 'js/gengo_common.js', false, $this->version);
		if (is_admin()) {
			wp_enqueue_script('gengo_admin', '/' . GENGO_DIR . 'js/gengo_admin.js', array('gengo_common', 'prototype'), $this->version);
		} else {
			wp_enqueue_script('gengo_site', '/' . GENGO_DIR . 'js/gengo_site.js', array('gengo_common', 'prototype'), $this->version);
		}
	}

	/**
	 * Provides compatibility with static front pages.
	 *
	 * @param WP_Query $query
	 */
	function pre_get_posts(& $query) {
		if ($query->is_home && !$query->is_posts_page && ('page' == get_option('show_on_front')) && get_option('page_on_front')) {
			$this->static_front = true;
			unset($query->query['language']);
		}
	}

	/**
	 * Provides compatibility with static front pages.
	 *
	 * @param int $page_on_front The original static front page.
	 * @return int The translated static front page.
	 */
	function option_page_on_front($page_on_front) {
		global $wpdb;
		if (is_admin()) return $page_on_front;
		if (!$this->page_on_front) {
			foreach ($this->language_preference as $language) {
				$ids[] = $this->codes2ids[$language->code];
			}
			$language_ids = implode(",", $ids);
			if ($pages = $wpdb->get_results("SELECT p2l2.post_id, p2l2.language_id FROM $this->post2lang_table AS p2l1 INNER JOIN $this->post2lang_table AS p2l2 ON (p2l1.translation_group = p2l2.translation_group) WHERE p2l2.language_id IN ($language_ids) AND p2l1.post_id = $page_on_front AND p2l1.translation_group != 0")) {
				foreach ($ids as $id) {
					foreach ($pages as $page) {
						if ($id == $page->language_id) {
							$this->page_on_front = $page->post_id;
							return $this->page_on_front;
						}
					}
				}
				$this->page_on_front = 0;
			} else {
				$this->page_on_front = $page_on_front;
			}
		}
		return $this->page_on_front;
	}
	
	/**
	 * Filters the blog's title, by using the defined snippet.
	 *
	 * @param string $name
	 * @return string
	 */
	function option_blogname($name) {
		if ($this->languages && $this->get_synblock('blogtitle', $this->language_preference_id[0]) != "") {
			return $this->get_synblock('blogtitle', $this->language_preference_id[0]);
		}
		return $name;
	}

	/**
	 * Sets the charset to that of the current preferred language.
	 *
	 * @param string $charset
	 * @return string
	 */
	function option_blog_charset($charset) {
		if(empty($charset)) $charset = "UTF-8";
		if ($this->languages) {
			return $this->languages[$this->language_preference_id[0]]->charset;
		}
		return $charset;
	}
		
	/**
	 * Filters the blog's description, by using the defined snippet.
	 *
	 * @param string $description
	 * @return string
	 */
	function option_blogdescription($description) {
		if ($this->languages && $this->get_synblock('blogtagline', $this->language_preference_id[0]) != "") {
			return $this->get_synblock('blogtagline', $this->language_preference_id[0]);
		}
		return $description;
	}
		
	/**
	 * Set the locale string quite everywhere.
	 *
	 * @param string $locale
	 * @return string
	 */
	function locale($locale) {
		global $wp_rewrite, $gengo_use_default_language;

		if (isset($this->processed_locale)) return $locale;
		$this->processed_locale = true;

		if (!$this->languages) return $locale;
		
		if (is_admin() || $gengo_use_default_language) {
			get_currentuserinfo();
			$this->add_language_preference($this->get_default_language_id());
		}
		
		// Don't do this for the admin section.
		if ($this->is_excluded_url($_SERVER['REQUEST_URI']) || is_admin()) {
	 		if ($this->language_preference_id) {
			 	$locale = $this->language_preference[0]->locale;
	 		} elseif ($cookie = trim($_COOKIE[$this->language_cookie])) {
	 			$codes = explode(GENGO_LANGUAGE_DIVIDER, $cookie);
	 			foreach ($codes as $code) {
	 				$this->add_language_preference($this->codes2ids[$code]);
	 			}
	 			$locale = $this->language_preference[0]->locale;
	 		}
			return $locale;
		}

		if (strstr($_SERVER['PHP_SELF'], 'wp-comments-post.php') || strstr($_SERVER['REQUEST_URI'], 'wp-trackback.php')) {
			$this->strict_links = false;
			return $locale;
		}
		
		$parser = new WP();
		$parser->parse_request();

		if (isset($parser->query_vars['preview'])) {
			$this->preview = true;
		}

		if (isset($parser->query_vars['s'])) {
			$this->searching = true;
		}

		if (isset($parser->query_vars['language'])) {
			$specified_codes = explode(' ', $parser->query_vars['language']);
			foreach ($specified_codes as $code) {
				if ($this->is_defined_code($code)) {
					$codes[] = $code;
					$this->language_supplied = true;
				}
			}
		} elseif (isset($parser->query_vars['error'])) {
			$this->error = true;
		}

		if ($codes) {
			$this->store_language_preference($codes);
			if (!$this->append_urls) {
				if ($parser->matched_query) {
				  $specified_codes = implode('\\' . GENGO_LANGUAGE_DIVIDER, $specified_codes);
					$location = preg_replace("/$specified_codes\/?$/", '', $_SERVER['REQUEST_URI']);
				} else {
					$location = remove_query_arg('language', $_SERVER['REQUEST_URI']);
				}
				if ("index.php/" == substr($location, -10)) $location = substr($location, 0, -10);
				header('Location: ' . $location);
				die();
			}
		} else {
			if ($parser->query_vars['p'] || $parser->query_vars['name'] || $parser->query_vars['pagename'] || $parser->query_vars['page_id']) {
				global $wpdb;
				if (($page_for_posts = get_option('page_for_posts')) && $parser->query_vars['pagename']) {
					$requested_page = get_page_by_path($parser->query_vars['pagename']);
					if ($requested_page->ID == $page_for_posts) {
						$this->page_for_posts = $page_for_posts;
					}
				}
				if (!$this->page_for_posts) {
					if ($parser->query_vars['p']) {
					  $value = $parser->query_vars['p'];
						$where = "p2l.post_id = $value";
					} elseif ($parser->query_vars['page_id']) {
					  $value = $parser->query_vars['page_id'];
						$where = "p2l.post_id = $value";
					} elseif ($parser->query_vars['name']) {
					  $value = $parser->query_vars['name'];
						$where = "p.post_name = '$value'";
						$join = "INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id";
					} else {
					  $value = $parser->query_vars['pagename'];
					  if (($position = strpos($parser->query_vars['pagename'], '/')) !== false) {
							$value = substr($value, $position + 1);
						}
						$where = "p.post_name = '$value'";
						$join = "INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id";
					}
					$language_ids = $wpdb->get_col("SELECT language_id FROM $this->post2lang_table AS p2l $join WHERE $where");
					if (!$cookie = trim($_COOKIE[$this->language_cookie])) {
						$codes = array($this->languages[$language_ids[0]]->code);
					} else {
						$cookie_codes = explode(GENGO_LANGUAGE_DIVIDER, $cookie);
						if (is_array($language_ids)) {
							foreach ($cookie_codes as $cookie_code) {
								if (in_array($this->codes2ids[$cookie_code], $language_ids)) {
									$codes[] = $cookie_code;
									break;
								}
							}
						}
					}
					if (!$codes && $language_ids[0]) {
						$codes = array($this->languages[$language_ids[0]]->code);
					}
				}
				$this->single_post_corrected = true;
			} 
			if (!$codes) {
				if ($cookie = trim($_COOKIE[$this->language_cookie])) {
					$codes = explode(GENGO_LANGUAGE_DIVIDER, $cookie);
				} else {
					$reading_language = get_option('gengo_default_reading_language');
				  switch ($reading_language) {
						case 0:
							preg_match_all('([a-z\-]{2,}+)', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
							if ($accepted_languages = $matches[0]) {
								foreach($accepted_languages as $accepted_language) {
								  if (false === ($position = strpos($accepted_language, '-'))) $position = strlen($accepted_language);
									if ($this->is_defined_code($user_agent_code = substr($accepted_language, 0, $position))) $codes[] = $user_agent_code;
								}
							}
							break;
	
							case -1:
							  foreach ($this->languages as $language_id => $entry) {
									$codes[] = $entry->code;
								}
								break;
	
							default:
								$codes[] = $this->languages[$reading_language]->code;
								break;
					}
					if ($codes) {
						$codes = array_unique(array_diff($codes, array('')));
					}
					if (!$codes) {
						$codes[] = $this->languages[$this->blog_default_language_id]->code;
					}
				}
			}
			$this->store_language_preference($codes);
			if (!$this->searching && $this->append_urls && !$this->error) {
				$code_string = implode(GENGO_LANGUAGE_DIVIDER, $codes);
			  if ($wp_rewrite->using_index_permalinks()) {
					$slashed = trailingslashit($_SERVER['REQUEST_URI']);
					if ($slashed == substr(trailingslashit($this->site_home), -strlen($slashed))) $_SERVER['REQUEST_URI'] = $slashed . "index.php/";
			  }
				header('Location: ' . $this->append_link_language($_SERVER['REQUEST_URI'], $code_string, true));
				die();
			}
		}
		return $this->language_preference[0]->locale;
	}

	/**
	 * Translate a text snippet into a given language.
	 *
	 * @param string $blockname
	 * @param int $language_id
	 * @return string
	 */
	function get_synblock($blockname, $language_id) {
		if(empty($language_id)) $language_id = get_option('gengo_blog_default_language');
		if (($cache = wp_cache_get($language_id, 'synblock')) && isset($cache[$blockname])) return $cache[$blockname];
		$cache = $this->get_synblocks_by_language($language_id);
		wp_cache_set($language_id, $cache, 'synblock');
		return $cache[$blockname];
	}

	/**
	 * Get an array of text snippets for a given language.
	 *
	 * @param int $language_id
	 * @return array
	 */
	function get_synblocks_by_language($language_id) {
		global $wpdb;
		if ($synblocks = $wpdb->get_results("SELECT block_name, text FROM $this->synblock_table WHERE language_id = $language_id")) {
			foreach ($synblocks as $synblock) {
				$return[$synblock->block_name] = $synblock->text;
			}
			return $return;
		}
		return array();
	}

	/**
	 * Outputs the language widget
	 *
	 * @param array $args
	 */
	function gengo_language_widget($args) {
		extract($args);
		$options = get_option('gengo_widget');
		echo $before_widget;
		echo $before_title . __('Languages', GENGO_DOMAIN) . $after_title;
		?>
		<ul><?php if(!is_admin()) { gengo_list_languages("show_count=".$options['show_count']."&show_current=".$options['show_current'].""); } ?></ul>		
		<?php
		echo $after_widget;
	}

	/**
	 * Adds widget control panel in the administration area
	 *
	 */
	function gengo_language_widget_control() {
		$options = get_option('gengo_widget');
		if ($_POST['save-widgets']) {
			$updated_options['show_count'] = isset($_POST['gengo_widget_count']) ? 'true' : 'false';
			$updated_options['show_current'] = isset($_POST['gengo_widget_showcurrent']) ? 'true' : 'false';
			if ($options != $updated_options) {
				$options = $updated_options;
				update_option('gengo_widget', $options);
			}
		}
		if ($options['show_count'] == "true") $count_checked = 'checked="checked" ';
		if ($options['show_current'] == "true") $showcurrent_checked = 'checked="checked" ';
		?>
		<div>
		<label for="gengo_widget_count">
			<input class="checkbox" type="checkbox" id="gengo_widget_count" name="gengo_widget_count" value="1" <?php echo $count_checked ?>/>
			<?php _e('Show Post Count', GENGO_DOMAIN) ?>
		</label>
		<br>
		<label for="gengo_widget_showcurrent">
			<input class="checkbox" type="checkbox" id="gengo_widget_showcurrent" name="gengo_widget_showcurrent" value="1" <?php echo $showcurrent_checked ?>/>
			<?php _e('Show current/active language', GENGO_DOMAIN) ?>
		</label>
		</div>
		<?php
	}

	/**
	 * Outputs the language widget
	 *
	 * @param array $args
	 */
	function gengo_language_command_widget($args) {
		extract($args);
		$options = get_option('gengo_command_widget');
		echo $before_widget;
#		_e('Language Commands', GENGO_DOMAIN);
		if(!is_admin()) {
		gengo_viewing_languages();
		gengo_available_languages();
		gengo_language_set();
		}
		echo $after_widget;
	}

	/**
	 * Adds widget control panel in the administration area
	 *
	 */
	function gengo_language_command_widget_control() {
		$options = get_option('gengo_command_widget');
		?>
		<div>
		</div>
		<?php
	}

	/**
	 * Hook widgets to the proper admin action 
	 *
	 */
	function plugins_loaded() {
		if (function_exists('register_sidebar_widget') && function_exists('register_widget_control')) {
		// default widget
		$widget_ops = array('classname' => 'widget_gengo_languages', 'description' => __( "A customizable list of available languages.", GENGO_DOMAIN) );
		wp_register_sidebar_widget('gengo_lang', __('Languages:',GENGO_DOMAIN), array(& $this, 'gengo_language_widget'), $widget_ops);
		wp_register_widget_control('gengo_lang', __('Languages',GENGO_DOMAIN), array(& $this, 'gengo_language_widget_control'));

		// js widget
		$widget_ops2 = array('classname' => 'widget_gengo_command_languages', 'description' => __( "Javascript language controls", GENGO_DOMAIN) );
		wp_register_sidebar_widget('gengo_command', __('Language Controls',GENGO_DOMAIN), array(& $this, 'gengo_language_command_widget'), $widget_ops2);
		wp_register_widget_control('gengo_command', __('Language Controls',GENGO_DOMAIN), array(& $this, 'gengo_language_command_widget_control'));

		}
	}

	/**
	 * Outputs a formatted error message
	 *
	 * @param string $message
	 * @return bool
	 */
	function error_message($message) {
	  ?><div class="error"><p><strong><?php _e('Error: ', GENGO_DOMAIN) ?></strong><?php echo $message ?></p></div><?php
		return false;
	}

	/**
	 * Outputs a formatted update message
	 *
	 * @param string $message
	 * @return bool
	 */
	function update_message($message) {
	  ?><div class="updated fade"><p><?php echo $message ?></p></div><?php
		return true;
	}

	/**
	 * Check if a language code is definied or not
	 *
	 * @param string $code
	 * @return bool
	 */
	function is_defined_code($code) {
		foreach ($this->languages as $language) if ($language->code == $code) return true;
		return false;
	}

	/**
	 * Checks if a language code is recognised.
	 *
	 * @param string $code
	 * @return bool
	 */
	function is_recognised_code($code) {
		include_once (ABSPATH . GENGO_DIR . 'gengo_languages.php');
		if (isset($gengo_recognised_languages[$code])) return true;
		return false;
	}

	/**
	 * Returns the order of preference for the given language id, or false if it is not set.
	 *
	 * @param int $language_id
	 * @return bool
	 */
	function is_set_language($language_id) {
		foreach ($this->language_preference_id as $order => $id) if ($id == $language_id) return $order;
		return false;
	}

	/**
	 * Returns the order of preference for given language, including languages not being displayed.
	 *
	 * @param int $language_id
	 * @return int
	 */
	function is_viewable_language($language_id) {
		$viewable_codes = explode(GENGO_LANGUAGE_DIVIDER, $this->viewable_code_string);
		foreach ($viewable_codes as $viewable_code) $ids[] = $this->codes2ids[$viewable_code];
		foreach ($ids as $order => $id) if ($id == $language_id) return $order;
		return false;
	}

	/**
	 * Determines which URLs should never have language codes appended to them.
	 * 
	 * @return bool
	 */
	function is_excluded_url($url) {
		if (defined('DOING_AJAX')) return true;
		foreach ($this->default_url_exclusions as $exclusion) if (strstr($url, $exclusion)) return true;
    if (($manual_exclusions = get_option('gengo_url_exclusions')) && ($exclusions = explode("\n", $manual_exclusions))) foreach ($exclusions as $exclusion) if (strstr($url, $exclusion)) return true;
		return false;
	}

	/**
	 * Check if gengo is installed or needs upgrading.
	 * 
	 * @return bool
	 */
	function is_installed() {
		return version_compare(get_option('gengo_version'), $this->version, '==');
	}

	/**
	 * Remove information from the database.
	 */
	function uninstall() {
		global $wpdb, $wp_rewrite;
		// Remove database tables.
		$wpdb->query("DROP TABLE $this->post2lang_table");
		$wpdb->query("DROP TABLE $this->language_table");
		$wpdb->query("DROP TABLE $this->summary_table");
		$wpdb->query("DROP TABLE $this->term2syn_table");
		$wpdb->query("DROP TABLE $this->synblock_table");
		// Remove user options.
		$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key = 'gengo_default_language'");
		// Remove options.
		delete_option('gengo_blog_default_language');
		delete_option('gengo_version');
		delete_option('gengo_url_exclusions');
		delete_option('gengo_append_urls');
		delete_option('gengo_allow_multiread');
		delete_option('gengo_default_reading_language');
		$new = $active = get_option('active_plugins');
		unset($new[array_search('gengo/gengo.php',$active)]);
		update_option('active_plugins',$new);
		header("Location: $this->site_url/wp-admin/plugins.php?deactivate=true");
		die();
	}

	/**
	 * Add Gengo's tables to the database and create the blog default language option.
	 * 
	 * @param int $current_version
	 * @return string
	 */
	function install($current_version = 0) {
		global $wpdb, $wp_rewrite, $wp_roles;

		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		require_once(ABSPATH . GENGO_DIR . 'gengo_schema.php');

		dbdelta($schema);

		if ($current_version) {
		  global $table_prefix;

		  if (version_compare($current_version, '0.5', '<=')) {
			  // Consolidate post2sum and post2lang.
				$post2sum_table = $wpdb->prefix . "post2sum";
			  if ($summaries = $wpdb->get_results("SELECT * FROM $post2sum_table")) foreach ($summaries as $sum) $wpdb->query("UPDATE $this->post2lang_table SET summary_group = $sum->summary_group WHERE post_id = $sum->post_id");
				$wpdb->query("DROP TABLE $post2sum_table");

				// Split synonyms into normalised form.
				$synonym_table = $wpdb->prefix . "synonyms";
				$wpdb->query("INSERT INTO $this->term2syn_table(term_id, language_id, synonym, sanitised) SELECT item_id, language_id, synonym, sanitised FROM $synonym_table WHERE item_id != 0");
				$wpdb->query("INSERT INTO $this->synblock_table(block_name, language_id, text) SELECT item_type, language_id, synonym FROM $synonym_table WHERE item_id = 0");
				$wpdb->query("DROP TABLE $synonym_table");
			}
		}
		
		if( version_compare($current_version, '0.5', '>') && version_compare($current_version, '2.5', '<=')){
			$cat2syn = $wpdb->prefix."cat2syn";
			$wpdb->query("INSERT INTO $this->term2syn_table(term_id,language_id,synonym,sanitised) SELECT cat_id, language_id, synonym, sanitised FROM $cat2syn;");
			$wpdb->query("ALTER TABLE $this->language_table ADD 'charset' varchar(16) NOT NULL;");
			$wpdb->query("UPDATE $this->language_table SET charset='UTF-8' WHERE charset='';");
			$wpdb->query("DROP TABLE $cat2syn");
		}
		
		$this->set_defined_languages();
		if ($current_version) $wp_rewrite->flush_rules();

		add_option('gengo_blog_default_language', '', 'Sets the default language code for the blog.', 'yes');
		add_option('gengo_url_exclusions', '', 'URL language code exclusions.', 'yes');
		add_option('gengo_append_urls', 1, 'Append URLs.', 'yes');
		add_option('gengo_default_reading_language', 0, 'The language to show users when they visit for the first time.', 'yes');

		$role = $wp_roles->get_role('administrator');
		$role->add_cap('configure_gengo');
		$role->add_cap('delete_languages');
		$role->add_cap('modify_gengo_settings');
		$role->add_cap('set_blog_default_language');
		$role->add_cap('uninstall_gengo');
		$role->add_cap('modify_gengo_exclusions');
		$role = $wp_roles->get_role('author');
		$role->add_cap('configure_gengo');
		$role = $wp_roles->get_role('editor');
		$role->add_cap('configure_gengo');

		if ($this->mysql_int_version() > 40100) {
			add_option('gengo_allow_multiread', 1, 'Controls whether users can read in multiple languages.', 'yes');
		} else {
			update_option('gengo_allow_multiread', 'x', 'Reading in multiple languages is disabled because your MySQL version is less than 4.1.', 'yes');
		}
		update_option('gengo_version', $this->version, 'Current version of Gengo.', 'yes');

		$message = ($current_version) ? __('Gengo successfully upgraded.', GENGO_DOMAIN) : __('Gengo installed.', GENGO_DOMAIN);
		return $this->update_message($message);
	}

	/**
	 * Return an integer representing MySQL version. Maybe stolen from phpMyAdmin.
	 * 
	 * @return int
	 */
	function mysql_int_version() {
	  global $wpdb;
	  $version = (explode('.', $wpdb->get_var('SELECT VERSION()')));
	  return (int) sprintf('%d%02d%02d', $version[0], $version[1], intval($version[2]));
	}

	/**
	 * Return the user's default language.
	 * 
	 * @return int
	 */
	function get_default_language_id() {
		global $current_user;
	 	if ($default = get_usermeta($current_user->id, 'gengo_default_language')) return $default;
	 	else return $this->blog_default_language_id;
	}

	/**
	 * Return the blog default language.
	 * 
	 * @return int
	 */
	function set_blog_default_language() {
		$this->blog_default_language_id = get_option('gengo_blog_default_language');
	}

	/**
	 * Set WordPRess default language.
	 * 
	 * @param string $locale
	 * @return bool
	 */
	function set_wplang($locale) {
		if (WPLANG) return true;
		if (!is_writeable(ABSPATH . 'wp-config.php')) {
			return false;
		}
		$config = str_replace("define ('WPLANG', '');", "define ('WPLANG', '$locale');", implode('', file(ABSPATH . 'wp-config.php')));
		$handle = fopen(ABSPATH . 'wp-config.php', 'w');
		fwrite($handle, $config);
		fclose($handle);
		$this->wplang = $locale;
		$this->update_message(sprintf(__("WPLANG automatically updated to '%s'.", GENGO_DOMAIN), $locale));
		return true;
	}

	/**
	 * Set all posts with no language to the default language.
	 * 
	 * @param bool $silent
	 * @return bool
	 */
	function set_no_lang_posts_default($silent = false) {
		global $wpdb;
		if (!$default = $this->blog_default_language_id) return $this->error_message(__("Please set the default language first.", GENGO_DOMAIN));

		// Add entries to the language table for all those posts that don't have an entry.
		$wpdb->query("INSERT INTO $this->post2lang_table(post_id, language_id, translation_group, summary_group) SELECT p.ID, 1, 0, 0 FROM $wpdb->posts AS p LEFT JOIN $this->post2lang_table AS p2l ON p2l.post_id = p.ID WHERE p2l.post_id IS NULL;");

		$language = $this->languages[$default]->language;
		if (!$silent) $this->update_message(sprintf(__("Posts with no language have been set to '%s'.", GENGO_DOMAIN), $language));
		return true;
	}

	/**
	 * Set a user's default language.
	 * 
	 * @param int $id
	 * @return string
	 */
	function set_default_language($id) {
		global $current_user;
		$language = $this->languages[$id]->language;
		update_usermeta($current_user->id, 'gengo_default_language', $id);
		return $this->update_message(sprintf(__("Default language set to '%s'.", GENGO_DOMAIN), $language));
	}

	/**
	 * Set the blog default language.
	 * 
	 * @param int $id
	 * @return string
	 */
	function save_blog_default_language($id) {
		$language = $this->languages[$id]->language;
		update_option('gengo_blog_default_language', $id);
		$this->set_blog_default_language($id);
		return $this->update_message(sprintf(__("Default blog language set to '%s'.", GENGO_DOMAIN), $language));
	}

	/**
	 * Gets language data associated with a post.
	 * 
	 * @param int $post_id The post ID
	 * @param string $fields Comma delimited string of fields to query
	 */
	function get_post_language_data($post_id, $fields) {
		global $wpdb;
		if (count(explode(',', $fields)) > 1) return $wpdb->get_row("SELECT $fields FROM $this->post2lang_table AS p2l INNER JOIN $this->language_table AS l ON p2l.language_id = l.language_id WHERE post_id = $post_id LIMIT 1", ARRAY_A);
		else return $wpdb->get_var("SELECT $fields FROM $this->post2lang_table AS p2l INNER JOIN $this->language_table AS l ON p2l.language_id = l.language_id WHERE post_id = $post_id LIMIT 1");
	}

	/**
	 * Fetch an array of languages and codes from the database.
	 * 
	 */
	function set_defined_languages() {
		global $wpdb;
		$this->languages = array();
		$wpdb->hide_errors();
		if ($defined_languages = $wpdb->get_results("SELECT * FROM $this->language_table ORDER BY language_id", ARRAY_A)) {
			foreach ($defined_languages as $defined_language) {
				extract ($defined_language, EXTR_OVERWRITE);
				$this->languages[$language_id] = new Language($code, $language, $locale, $rtl, $charset);
				$this->codes2ids[$code] = $language_id;
			}
		}
		$wpdb->show_errors();
	}

	/**
	 * Return counts for the number of posts, pages or all, in all languages.
	 * 
	 * @param string $type
	 */
	function get_totals($type = 'all') {
		global $wpdb;
		if (!$this->languages) return array();
		switch ($type) {
			case 'all':
		    $results = $wpdb->get_results("SELECT language_id, COUNT(*) AS total FROM $this->post2lang_table GROUP BY language_id");
		    break;

			default:
				$results = $wpdb->get_results("SELECT p2l.language_id, COUNT(*) AS total FROM $this->post2lang_table AS p2l INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id WHERE p.post_status = 'publish' AND p.post_type = '$type' GROUP BY p2l.language_id");
				break;
		}
		if ($results) {
			foreach ($results as $result) {
				$return[$result->language_id] = $result->total;
			}
			return $return;
		} else {
			return array();
		}
	}

	/**
	 * Return counts for the number of posts, pages or all of a certain language, for a given language id.
	 * 
	 * @param int $id
	 * @param string $type
	 */
	function get_total_by_language_id($id, $type = 'all') {
		global $wpdb;
		if (!$this->languages) return 0;
		switch ($type) {
			case 'all':
		    return $wpdb->get_var("SELECT COUNT(*) FROM $this->post2lang_table WHERE language_id = '$id'");

			default:
				return $wpdb->get_var("SELECT COUNT(*) FROM $this->post2lang_table AS p2l INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id WHERE p2l.language_id = '$id' AND p.post_status = 'publish' AND p.post_type = '$type'");
		}
		return 0;
	}

	/**
	 * Return one complete translation link.
	 * 
	 * @param array $translation_data
	 * @param string $type	Can be a, link, comments, home_link
	 * @param string $text
	 * @return string
	 */
	function translation_link($translation_data, $type = 'a', $text = '') {
		global $wp_rewrite;

		$post_id = $translation_data['translation_id'];
		$code = $this->languages[$translation_data['translation_language']]->code;
		$language = $this->languages[$translation_data['translation_language']]->language;
		if (!$text) $text = $language;
		switch ($type) {
			case 'link':
				return "<link rel=\"alternate\" title=\"" . sprintf(__('This page in %s.', GENGO_DOMAIN), $language) . "\" type=\"text/html\" hreflang=\"$code\" href=\"" . get_permalink($post_id) . "\" />";

			case 'a':
				return "<a class=\"gengo_lang_$code\" rel=\"alternate\" hreflang=\"$code\" href=\"" . get_permalink($post_id) . "\" title=\"" . sprintf(__('View the %s translation of this article.', GENGO_DOMAIN), $language) . "\">$text</a>";
				
			case 'comments':
				return "<a class=\"gengo_lang_$code\" rel=\"alternate\" hreflang=\"$code\" href=\"" . get_permalink($post_id) . "#comments\" title=\"" . sprintf(__('View the %s comments for this article.', GENGO_DOMAIN), $language) . "\">$text</a> ";
				
			case 'home_link':
				$home = $this->site_home . '/';
				if ($wp_rewrite->using_index_permalinks()) $home .= $wp_rewrite->index . '/';
				$home = $this->append_link_language($home, $code);
				return "<link rel=\"alternate\" title=\"" . sprintf(__('Homepage in %s.', GENGO_DOMAIN), $language) . "\" type=\"text/html\" hreflang=\"$code\" href=\"$home\" />";
		}
		return '';
	}

	/**
	 * Given a post_id, returns an array of posts that are a translation of this one.
	 * With no post_id, assumes we are in The Loop and uses $post->translation_group.
	 *
	 * @param int $id
	 * @return array
	 */
	function get_the_translations($id = 0) {
		global $wpdb, $post;
		if (isset($this->post_language_cache[$id])) {
			return $this->post_language_cache[$id]->translations;
		} else {
			$translation_group = ($id) ? $wpdb->get_var("SELECT translation_group FROM $this->post2lang_table WHERE post_id = $id LIMIT 1") : $post->translation_group;
			if ($translation_group) {
				if ($translations = $wpdb->get_results("SELECT language_id AS translation_language, post_id AS translation_id, p.post_date AS translation_date, u.display_name AS translation_author FROM $this->post2lang_table AS p2l INNER JOIN $wpdb->posts AS p ON p.ID = p2l.post_id INNER JOIN $wpdb->users AS u ON p.post_author = u.ID WHERE p2l.translation_group = $translation_group AND p2l.post_ID != $post->ID ORDER BY language_id", ARRAY_A)) {
					return $translations;
				}
			}
		}
		return array();
	}

	/**
	 * Get all the summaries in a specified group.
	 *
	 * @param int $summary_group
	 * @return array
	 */
	function get_the_summaries_by_summary_group($summary_group) {
		global $wpdb;
		if (!$this->languages) return array();
		if ($summaries = $wpdb->get_results("SELECT language_id, summary_id, summary, summary_group FROM $this->summary_table WHERE summary_group = $summary_group", ARRAY_A)) {
			return $summaries;
		}
		return array();
	}

	/**
	 * Given a post_id, returns an array of summaries of that post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	function get_the_summaries($post_id = false) {
		global $wpdb, $post;
		if (!$post_id) $post_id = $post->ID;
		if (!$post_id || !$this->languages) return array();
		if (is_array($post_id)) $post_id = implode(',', $post_id);
		if ($summaries = $wpdb->get_results("SELECT post_id, s.language_id AS language_id, summary_id, summary, s.summary_group AS summary_group FROM $this->post2lang_table p2l INNER JOIN $this->summary_table s ON s.summary_group = p2l.summary_group WHERE post_id IN ($post_id) ORDER BY language_id, summary_group", ARRAY_A)) {
			return $summaries;
		}
		return array();
	}

	/**
	 * Ajax: Returns JSON formatted information about potential translation group components.
	 *
	 * @param array $posts
	 */
	function get_group_components($posts) {
	  global $wpdb;

		if (!$summaries = $this->get_the_summaries($posts)) return;

		if (is_array($posts)) $posts = implode(',', $posts);
		$post_languages = $wpdb->get_results("SELECT post_id, language_id FROM $this->post2lang_table WHERE post_id in ($posts)", ARRAY_A);
		foreach ($post_languages as $entry) $lookup[$entry['post_id']] = $entry['language_id'];

		foreach ($summaries as $key => $summary) {
		  $post_language_id = $lookup[$summary['post_id']];
		  $summary_language_id = $summary['language_id'];
			if (($summaries[$key + 1]['language_id'] == $summary['language_id']) || ($summaries[$key - 1]['language_id'] == $summary['language_id'])) {
				$clashes[$summary['language_id']] = true;
				ob_start();
				$this->print_summary($summary, 'clash', false, 55, true);
				$summary = '"' . htmlspecialchars(ob_get_clean()) . '"';
			} else {
			  ob_start();
				$this->print_summary($summary, 'existing', false); ?>
				<input type="hidden" name="gengo_summary_ids[]" value="<?php echo $summary['summary_id'] ?>" /><?php
				$summary = '"' . htmlspecialchars(ob_get_clean()) . '"';
			}
			$components[] = "{post_language: $post_language_id, summary_language: $summary_language_id, summary: $summary}";
		}

		if ($components) {
			$components = '[' . implode(', ', $components) . ']';
			$return[] = "summaries: $components";
			if ($clashes) {
			  $clashes = '[' . implode(', ', array_keys($clashes)) . ']';
				$return[] = "clashes: $clashes";
			}
			$success_message = __("Post information retrieved.", GENGO_DOMAIN);
			header("X-JSON: {success: '$success_message'}");
			echo '{' . implode(', ', $return) . '}';
		}
	}

	/**
	 * Get synonyms for a given snippet name
	 *
	 * @param string $block_name
	 * @return string
	 */
	function get_synblocks($block_name) {
		global $wpdb;
		$responses = array();
		if ($synonyms = $wpdb->get_results("SELECT language_id, text FROM $this->synblock_table WHERE block_name = '$block_name'")) {
			foreach ($synonyms as $synonym) {
			  $synblocks[$synonym->language_id] = $synonym->text;
			}
			if ($unspecified_languages = array_diff(array_keys($this->languages), array_keys($synblocks))) {
				foreach ($unspecified_languages as $unspecified) {
					$synblocks[$unspecified] = '';
				}
			}
		}
		return $synblocks;
	}

	/**
	 * Ajax: Returns a JSON formatted object of synonym blocks.
	 *
	 * @param string $block_name
	 */
	function get_synblocks_by_name($block_name) {
		$responses = array();
		$synblocks = $this->get_synblocks($block_name);
		$count = 0;
		foreach ($synblocks as $language_id => $synblock) {
			$responses[] = "synblock_$language_id: " . '"' . htmlspecialchars($synblock) . '"';
			if ($synblock) $count++;
		}
		$success_message = sprintf(__("<strong>%d</strong> Snippet(s) for <strong>%s</strong> retrieved.", GENGO_DOMAIN), $count, $block_name);
		header("X-JSON: {success: '$success_message'}");
		echo '{' . implode(', ', $responses) . '}';
	}
	
	/**
	 * Ajax: Return JSON formatted translation information for the specified post id.
	 *
	 * @param int $post_id
	 */
	function get_translation_content($post_id) {
		global $wpdb;
		$readonly = current_user_can('edit_post', $post_id) ? 'false' : 'true';
		$post_data = $wpdb->get_row("SELECT post_content, post_title, l.rtl FROM $wpdb->posts p INNER JOIN $this->post2lang_table p2l ON p.ID = p2l.post_id INNER JOIN $this->language_table l ON p2l.language_id = l.language_id WHERE ID = $post_id LIMIT 1", ARRAY_A);
		$post_content = '"' . htmlspecialchars($post_data['post_content']) . '"';
		$post_title = '"' . htmlspecialchars($post_data['post_title']) . '"';
		$direction = $post_data['rtl'] ? '"rtl"' : '"ltr"';
		$success_message = __('Translation content retrieved.', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
		echo "{title: $post_title, readonly: $readonly, content: $post_content, direction: $direction}";
	}

	/**
	 * Ajax: Return JSON formatted summary content.
	 *
	 * @param int $summary_id
	 */
	function get_summary_content($summary_id) {
		global $wpdb;
		$summary_data = $wpdb->get_row("SELECT language_id, summary FROM $this->summary_table WHERE summary_id = $summary_id", ARRAY_A);
		$content = '"' . htmlspecialchars($summary_data['summary']) . '"';
		$success_message = __('Summary content retrieved', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
		echo "{language_id: $summary_data[language_id], content: $content}";
	}

	/**
	 * Ajax: Save the translation from the second translation pane.
	 *
	 * @param int $translation_id
	 * @param string $translation_content
	 */
	function update_translation_content($translation_id, $translation_content) {
		global $wpdb;
		if (!current_user_can('unfiltered_html')) $translation_content = wp_filter_post_kses($translation_content);
		$wpdb->query("UPDATE $wpdb->posts SET post_content = '$translation_content', post_modified = '" . current_time('mysql') . "', post_modified_gmt = '" . current_time('mysql', true) . "' WHERE ID = $translation_id");
		$success_message = __('Translation updated.', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
	}

	/**
	 * Ajax: Update a summary with the given content.
	 *
	 * @param int $summary_id
	 * @param string $summary_content
	 */
	function update_summary_content($summary_id, $summary_content) {
		global $wpdb;
		if (!current_user_can('unfiltered_html')) $summary_content = wp_filter_post_kses($summary_content);
		$wpdb->query("UPDATE $this->summary_table SET summary = '$summary_content' WHERE summary_id = $summary_id");
		$success_message = __('Summary successfully updated.', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
	}

	/**
	 * Ajax: Add a new summary to a post.
	 *
	 * @param int $post_id
	 * @param int $summary_group
	 * @param int $language_id
	 * @param string $summary_content
	 * @param int $translation_group
	 */
	function add_summary_content($post_id, $summary_group, $language_id, $summary_content, $translation_group) {
		global $wpdb;
		if (!current_user_can('unfiltered_html')) $summary_content = wp_filter_post_kses($summary_content);
		if (!$summary_group) $summary_group = $wpdb->get_var("SELECT MAX(summary_group) FROM $this->post2lang_table") + 1;
		$wpdb->query("INSERT INTO $this->summary_table(summary_group, language_id, summary) VALUES ($summary_group, $language_id, '$summary_content')");
		$posts = ($translation_group && ($post_ids = $wpdb->get_col("SELECT post_id FROM $this->post2lang_table WHERE translation_group = $translation_group"))) ? implode(',', $post_ids) : $post_id;
		$wpdb->query("UPDATE $this->post2lang_table SET summary_group = $summary_group WHERE post_id IN ($posts)");
		$success_message = __('Summary successfully added.', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
		echo $summary_group;
	}

	/**
	 * Ajax: 	Deletes a summary.
	 *
	 * @params int $summary_id
	 * @params int $summary_group
	 */
	function delete_summary($summary_id, $summary_group) {
		global $wpdb;

		// Remove the summary.
		$wpdb->query("DELETE FROM $this->summary_table WHERE summary_id = $summary_id");

		// Remove the summary group if this was the last one.
		if (!$wpdb->get_var("SELECT COUNT(*) FROM $this->summary_table WHERE summary_group = $summary_group")) {
			$wpdb->query("UPDATE $this->post2lang_table SET summary_group = 0 WHERE summary_group = $summary_group");
		}
		$success_message = __('Summary successfully deleted.', GENGO_DOMAIN);
		header("X-JSON: {success: '$success_message'}");
	}

	/**
	 * Ajax: Prints a single block summary.
	 *
	 * @params int $summary_info
	 * @params string $highlight
	 * @params int $edit
	 * @params int $max_width
	 * @params bool $check
	 */
	function print_summary($summary_info, $highlight, $edit, $max_width = 55, $check = false) {
	  extract($summary_info, EXTR_OVERWRITE);
		if ('clash' == $highlight) {
			$background = '#ffebeb';
		} elseif ('added' == $highlight) {
			$background = '#ebffeb';
		} else {
			$background = '#e9e9e9';
		}
		?>
		<p id="sum_block_<?php echo $summary_id ?>" style="font-size: 11px; background: <?= $background ?>;">
			<?php
			if ($edit) {
				?>
				<a href="" onclick="gengo_delete_summary(<?= "$summary_id, $summary_group"; ?>); return false;" style="float: right; color: #000; margin-bottom: -2px;"><?php _e('Delete', GENGO_DOMAIN) ?></a>
				<a href="" onclick="gengo_show_summary_content_block(<?= $summary_id ?>); return false" style="float: right; margin-right: 1px; color: #000; margin-bottom: -2px;"><?php _e('Edit', GENGO_DOMAIN) ?></a>
				<?php
			} elseif (('clash' == $highlight) && $check) {
				?><input type="radio" name="lang_clash_<?php echo $language_id ?>" value="<?php echo $summary_id ?>" style="float: right; margin: 0" onclick="gengo_resolve_clashes(<?php echo $language_id ?>)" /><?php
			}
			printf(__('in %s:', GENGO_DOMAIN), $this->languages[$language_id]->language);
			?>
			<br />
			<em><?php echo (strlen($summary) > $max_width) ? substr($summary, 0, $max_width - 2) . ".." : $summary; ?></em>
		</p>
		<?php
	}

	/**
	 * Ajax: Prints a single block summary.
	 *
	 * @params array $summaries
	 * @params array $excluded
	 * @params string $highlight
	 * @params bool $edit
	 */
	function list_summaries($summaries, & $excluded, $highlight = 'existing', $edit = true) {
		$excluded = array();
		foreach ($summaries as $sum) {
		  $this->print_summary($sum, $highlight, $edit);
			$excluded[] = $sum['language_id'];
		}
	}


	/**
	 * (Maybe) Ajax: Generate a list of summaries
	 *
	 * @params int $post_id
	 * @params int $existing_summary_group
	 * @params string $is_in_group
	 * @params int $translation_number
	 */
	function generate_summary_lists($post_id, $existing_summary_group, $is_in_group, $translation_number = 0, $existing_translation_group = 0) {
		global $wpdb;

		if ($this->ajax) {
			$success_message = __('Summary list retrieved.', GENGO_DOMAIN);
			header("X-JSON: {success: '$success_message'}");
		}

		$post_summaries = $this->get_the_summaries_by_summary_group($existing_summary_group);

		if ($translation_number) {
			if ('true' == $is_in_group) {
				if (!$summary_group = $wpdb->get_var("SELECT summary_group FROM $this->post2lang_table WHERE translation_group = $translation_number LIMIT 1")) {
					$summary_group = 0;
				}
				if ($summary_group != $existing_summary_group) {
					$translation_summaries = $this->get_the_summaries_by_summary_group($summary_group);
				}
			} else {
				$translation_summaries = $this->get_the_summaries($translation_number);
				if (!$summary_group = $wpdb->get_var("SELECT summary_group FROM $this->post2lang_table WHERE post_id = $translation_number LIMIT 1")) {
					$summary_group = 0;
				}
			}
		} else {
		  $summary_group = ($existing_translation_group) ? 0 : $existing_summary_group;
		}

		$excluded = array();
		if ($summary_group == $existing_summary_group) {
			$this->list_summaries($post_summaries, $excluded);
		} else {
			// We have changed the summary group and this posts' summaries will be unlinked.
			$changed = true;
			if ($translation_summaries) {
				?><p style="font-size: 11px;"><em><?php _e('The following summaries will be linked:', GENGO_DOMAIN) ?></em></p><?php
				$this->list_summaries($translation_summaries, $excluded, 'added');
			}
			if ($post_summaries) {
				?><p style="font-size: 11px;"><strong><?php _e('Warning: The following summaries will be unlinked.', GENGO_DOMAIN) ?></strong></p><?php
				$this->list_summaries($post_summaries, $dummy, 'clash', false);
			}
		}
		foreach ($this->languages as $language_id => $entry) if (!in_array($language_id, $excluded)) $summary_language .= "<option value=\"$language_id\">$entry->language</option>";
		?><input type="hidden" id="gengo_summary_group" name="gengo_summary_group" value="<?php echo $summary_group ?>" /><?php
		if ($changed) return;
		if ($post_id) {
			if ($summary_language) {
				?>
				<label for="gengo_new_summary" class="selectit"><?php _e('Add a summary:', GENGO_DOMAIN) ?></label>
				<select name="gengo_new_summary" id="gengo_new_summary"><?php echo $summary_language; ?></select>
				<p class="submit"><input style="float: right" type="button" value="<?php _e('Add Summary', GENGO_DOMAIN) ?>" onclick="gengo_show_summary_content_block(0)" /></p>
				<?php
			}
		} else {
			?><p style="font-size: 11px"><?php _e('Please save the post before adding summaries.', GENGO_DOMAIN) ?></p><?php
		}
	}

	/**
	 * Return the possible translation options for a given post.
	 * Elite SQL queries in this function after great advice from Rudy at r937.com
	 *
	 * @params int $post_language_id
	 * @params int $post_translation_group
	 * @params int $existing_post_id
	 * @params int $first_post
	 * @params int $first_group
	 */
	function generate_translation_lists($post_language_id, $post_translation_group, $existing_post_id, $first_post = 0, $first_group = 0) {
		global $wpdb;

		if ($this->ajax) {
			$success_message = __('Translation options updated.', GENGO_DOMAIN);
			header("X-JSON: {success: '$success_message'}");
		}

		// First display the current group if we are in a group and we haven't changed the language.
		if ($post_translation_group) {
			$existing_language_id = $this->get_post_language_data($existing_post_id, 'p2l.language_id');
			if ($existing_language_id == $post_language_id || !$wpdb->get_var("SELECT post_id FROM $this->post2lang_table WHERE language_id = $post_language_id AND translation_group = $post_translation_group LIMIT 1")) {
				?>
				<span style="float: right; color: #000; font-size: 11px;">(<?php _e('Current', GENGO_DOMAIN) ?>)</span>
				<label for="gengo_translation_group_<?php echo $post_translation_group ?>" class="selectit">
				<input type="radio" id="gengo_translation_group_<?php echo $post_translation_group ?>" name="gengo_translation_group" onclick="gengo_lock_controls(); gengo_refresh_summary_list();" value="<?php echo $post_translation_group ?>" checked="checked" />
				<?php _e('of this group:', GENGO_DOMAIN); ?></label>
				<p style="margin: 0 0 10px 0; background: #e9e9e9;">
				<?php
				$group_info = $wpdb->get_results("SELECT ID as post_id, post_title, translation_group FROM $this->post2lang_table p2l INNER JOIN $wpdb->posts p ON p.ID = p2l.post_id WHERE translation_group = $post_translation_group", ARRAY_A);
				foreach ($group_info as $group) {
					extract($group, EXTR_OVERWRITE);
					$title = (strlen($post_title) > GENGO_SIDEBAR_TITLE_LENGTH) ? substr($post_title, 0, GENGO_SIDEBAR_TITLE_LENGTH - 2) . ".." : $post_title;
					if ($existing_post_id != $post_id) {
						?>
						<a href="" onclick="gengo_show_translation_content_block(<?php echo $post_id ?>); return false;"><?php echo $title ?></a><br />
						<?php
					} else {
						echo $title?><br /><?php
					}
				}
				?>
				</p>
				<?php
			}
		}
		if ($inclusions = $wpdb->get_col("SELECT DISTINCT translation_group FROM $this->post2lang_table GROUP BY translation_group HAVING 0 = COUNT(CASE WHEN language_id = $post_language_id OR translation_group = 0 OR translation_group = $post_translation_group THEN 37337 END) ORDER BY translation_group DESC LIMIT $first_group, 3")) {
			$included_groups = implode(',', $inclusions);
			if ($group_info = $wpdb->get_results("SELECT p.ID AS post_id, p.post_title AS post_title, p2l.translation_group AS translation_group FROM $this->post2lang_table p2l INNER JOIN $wpdb->posts p ON p.ID = p2l.post_id WHERE translation_group IN ($included_groups) ORDER BY translation_group", ARRAY_A)) {
				foreach ($group_info as $group) {
					extract($group, EXTR_OVERWRITE);
					if ($translation_group != $previous_translation_group) {
						if ($previous_translation_group){
							?></p><?php
						}
						?>
						<label for="gengo_translation_group_<?php echo $translation_group ?>" class="selectit">
						<input type="radio" id="gengo_translation_group_<?php echo $translation_group ?>" name="gengo_translation_group" onclick="gengo_lock_controls(); gengo_refresh_summary_list();" value="<?php echo $translation_group ?>" />
						<?php _e('of this group:', GENGO_DOMAIN); ?></label>
						<p style="margin: 0 0 10px 0; background: #e9e9e9;">
						<?php
					}
					$previous_translation_group = $translation_group;
					$title = (strlen($post_title) > GENGO_SIDEBAR_TITLE_LENGTH) ? substr($post_title, 0, GENGO_SIDEBAR_TITLE_LENGTH - 2) . ".." : $post_title;
					?>
					<a href="" onclick="gengo_show_translation_content_block(<?php echo $post_id ?>); return false;"><?php echo $title ?></a><br />
					<?php
				}
				?>
				</p>
				<?php
			}
			?>
			<p class="submit" id="gengo_group_navigation">
			<?php
			if ($first_group) {
				$previous = ($first_group < GENGO_GROUPS_LIMIT) ? $first_group : GENGO_GROUPS_LIMIT;
				?>
				<input type="button" style="float: left" value="&laquo; Prev <?php echo $previous ?>" onclick="gengo_update_translation_options(<?php echo $first_post . ', ' . ($first_group - $previous) ?>)" />
				<?php
			}
			$group_count = count($wpdb->get_col("SELECT DISTINCT translation_group FROM $this->post2lang_table GROUP BY translation_group HAVING 0 = COUNT(CASE WHEN language_id = $post_language_id OR translation_group = 0 OR translation_group = $post_translation_group THEN 37337 END)"));
			if ($group_count > ($first_group + GENGO_GROUPS_LIMIT)) {
				$remaining = $group_count - ($first_group + GENGO_GROUPS_LIMIT);
				if ($remaining > GENGO_GROUPS_LIMIT) $remaining = GENGO_GROUPS_LIMIT;
				?>
				<input type="button" style="float: right" value="Next <?php echo $remaining ?> &raquo;" onclick="gengo_update_translation_options(<?php echo $first_post . ', ' . ($first_group + GENGO_GROUPS_LIMIT) ?>)" />
				<?php
			}
			?>
			</p>
			<?php
		}
		if ($post_info = $wpdb->get_results("SELECT DISTINCT p.ID AS post_id, p.post_title AS post_title, p.post_date AS post_date FROM $wpdb->posts AS p INNER JOIN $this->post2lang_table p2l ON p.ID = p2l.post_ID WHERE p2l.language_id != $post_language_id AND p2l.post_id != $existing_post_id AND p2l.translation_group = 0 AND (p.post_type = 'page' OR p.post_type = 'post') ORDER BY ID DESC LIMIT $first_post, " . GENGO_POSTS_LIMIT, ARRAY_A)) {
			if (!$group_info) {
				// Hack to force an array if we can only select a post for the translation.
				?>
				<input type="hidden" value="" name="gengo_translation_group" />
				<?php
			}

			foreach ($post_info as $info) {
				extract($info, EXTR_OVERWRITE);
				$title = (strlen($post_title) > GENGO_SIDEBAR_SELECT_LENGTH) ? substr($post_title, 0, GENGO_SIDEBAR_SELECT_LENGTH - 2) . ".." : $post_title;
				$label = $title." (".date("j M",strtotime($post_date)).")";
				$translation_list .= "<option value='".$post_id."' label='".$label."' >".$label."</option>";
			}
			?>
			<label for="gengo_post_translation" style="clear: both" class="selectit"><input type="radio" name="gengo_translation_group" id="gengo_post_translation" value="0"<?php if (!$post_translation_group) echo ' checked="checked"' ?> onclick="gengo_show_translation_content_block(document.getElementById('gengo_translation_post').value);"> <?php _e('of the following post/page:', GENGO_DOMAIN) ?></label>
			<select name="gengo_translation_post" id="gengo_translation_post" onchange="document.getElementById('gengo_post_translation').checked = true; gengo_show_translation_content_block(this.value);"><?php echo $translation_list; ?></select>
			<p class="submit" id="gengo_post_navigation">
			<?php
			if ($first_post) {
				$previous = ($first_post < GENGO_POSTS_LIMIT) ? $first_post : GENGO_POSTS_LIMIT;
				?>
				<input type="button" style="float: left" value="&laquo; Prev <?php echo $previous ?>" onclick="gengo_update_translation_options(<?php echo ($first_post - $previous) . ', ' . $first_group ?>)" />
				<?php
			}
			$post_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts AS p INNER JOIN $this->post2lang_table p2l ON p.ID = p2l.post_ID WHERE p2l.language_id != $post_language_id AND p2l.post_id != $existing_post_id AND p2l.translation_group = 0");
			if ($post_count > ($first_post + GENGO_POSTS_LIMIT)) {
				$remaining = $post_count - ($first_post + GENGO_POSTS_LIMIT);
				if ($remaining > GENGO_POSTS_LIMIT) $remaining = GENGO_POSTS_LIMIT;
				?>
				<input type="button" style="float: right" value="Next <?php echo $remaining ?> &raquo;" onclick="gengo_update_translation_options(<?php echo ($first_post + GENGO_POSTS_LIMIT) . ', ' . $first_group ?>)" />
				<?php
			}
			?>
			</p>
			<?php
		}
		if (!$post_info && !$group_info) {
		  $language = $this->languages[$post_language_id]->language;
			// Nasty hack to prevent javascript errors.
			?>
			<input type="hidden" value="" name="gengo_translation_group" />
			<p style="font-size: 11px;"><?php printf(__('No translation options available. Write a post or page in a language other than %s.', GENGO_DOMAIN), $language) ?></p>
			<?php
		}
	}
}

if (!is_admin()) require_once(ABSPATH . GENGO_DIR . 'gengo_template_functions.php');

/**
 * 	Handle AJAX Callbacks.
 *
 */
if (isset($_POST['gengo_ajax'])) {

	$gengo = new Gengo(true);
	switch ($_POST['gengo_action']) {
		case 'ajax_update_translation_options':
		$gengo->generate_translation_lists($_POST['post_language_id'], $_POST['post_translation_group'],  $_POST['post_id'], $_POST['first_post'], $_POST['first_group']);
		break;

		case 'ajax_get_translation_post':
		$gengo->get_translation_content($_POST['post_id']);
		break;

		case 'ajax_update_translation_content':
		$gengo->update_translation_content($_POST['translation_id'], $_POST['translation_content']);
		break;

		case 'ajax_get_summary_content':
		$gengo->get_summary_content($_POST['summary_id']);
		break;

		case 'ajax_refresh_summary_lists':
		$gengo->generate_summary_lists($_POST['post_id'], $_POST['summary_group'], $_POST['is_in_group'], $_POST['translation_number'], $_POST['existing_translation_group']);
		break;

		case 'ajax_update_summary_content':
		$gengo->update_summary_content($_POST['summary_id'], $_POST['summary_content']);
		break;

		case 'ajax_add_summary_content':
		$gengo->add_summary_content($_POST['post_id'], $_POST['summary_group'], $_POST['language_id'], $_POST['summary_content'], $_POST['translation_group']);
		break;

		case 'ajax_delete_summary':
		$gengo->delete_summary($_POST['summary_id'], $_POST['summary_group']);
		break;

		case 'ajax_get_group_components':
		$gengo->get_group_components($_POST['post_ids']);
		break;

		case 'ajax_get_synblock':
		$gengo->get_synblocks_by_name($_POST['block_name']);
		break;
	}
	exit;
}

$gengo = new Gengo();