function gengo_update_definition(append_urls) {
	var def = $('language_def');
	var code = $('language_code');
	var locale = $('language_locale');
	var charset = $('language_charset');	// pixline: need more charset?
	def.innerHTML = $('gengo_language').value;
	if (append_urls) code.innerHTML = $('gengo_language_code').value;
	locale.innerHTML = $('gengo_language_locale').value;
	if ('' == def.innerHTML || '' == $('gengo_language_code').value || '' == locale.innerHTML) {
		$('language_box').style.display = 'none';
	} else {
		$('language_box').style.display = 'block';
	}
	if (rtl_scripts.in_array($('gengo_language_code').value)) {
		$('gengo_language_rtl').checked = true;
		var dir = 'rtl';
	} else {
		$('gengo_language_rtl').checked = false;
		var dir = 'ltr';
	}
	gengo_set_dir(dir);
}

function gengo_change_dir() {
	var dir = (document.getElementById('gengo_language_rtl').checked == true) ? 'rtl' : 'ltr';
	gengo_set_dir(dir);
}

function gengo_set_dir(dir) {
	$('gengo_language').dir = dir;
	$('gengo_language_blog_title').dir = dir;
	$('gengo_language_blog_tagline').dir = dir;
}

function gengo_add_delete_all() {
	addLoadEvent (
		function() {
			var deletelink = $$('#submitpost p a')[0];
			var newlinkhref = deletelink.href + '&gengo_delete_all_translations=true';
			var newlink = $('gengo_delete_translations');
			newlink.href = newlinkhref;
			deletelink.up().insertBefore(newlink.up(), deletelink.next())
		}
	);
}

function gengo_resolve_clashes(language_id) {
  var this_clash_array = document.forms['gengo_group_form']['lang_clash_' + language_id];
	for (i = 0; i < this_clash_array.length; i++) {
		var clash_id = this_clash_array[i].value;
		if (this_clash_array[i].checked == true) $('sum_block_' + clash_id).style.background = '#e9e9e9';
		else $('sum_block_' + clash_id).style.background = '#ffebeb';
	}
	var resolved_clash_ids = '';
	var removed_clash_ids = '';
	for (i = 0; i < languages.length; i++) {
		if (clashes[languages[i]] == false) continue;
		var resolved = false;
		for (j = 0; j < document.forms['gengo_group_form']['lang_clash_' + languages[i]].length; j++) {
			if (document.forms['gengo_group_form']['lang_clash_' + languages[i]][j].checked == true) {
			  if (resolved_clash_ids != '') resolved_clash_ids += ',';
			  resolved_clash_ids += document.forms['gengo_group_form']['lang_clash_' + languages[i]][j].value;
				resolved = true;
			} else {
			  if (removed_clash_ids != '') removed_clash_ids += ',';
				removed_clash_ids += document.forms['gengo_group_form']['lang_clash_' + languages[i]][j].value;
			}
		}
		if (resolved == false) return false;
	}
	$('gengo_resolved_summary_clashes').value = resolved_clash_ids;
	$('gengo_removed_summary_clashes').value = removed_clash_ids;
	$('gengo_create_group_submit').disabled = false;
}

function gengo_get_translation_group_components(post_id) {
	var post_id_string = '';
	var selectors = document.getElementsByName('gengo_constituent_posts[]');
	var selected = 0;
	for (i = 0; i < selectors.length; i++) {
		var lang_id = selectors[i].id.substring(11);
		for (j = 0; j < languages.length; j++) $(lang_id + '_' + languages[j]).innerHTML = '';
		if (selectors[i].value != 0) {
			post_id_string += '&post_ids[]=' + selectors[i].value;
			selected++;
		}
	}
	$('gengo_create_group_submit').disabled = true;
	if (!selected) return false;
	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_get_group_components' + post_id_string,
			onSuccess: function(request) {
				var response = eval('(' + request.responseText.gsub("\n", "\\n").gsub("\r", "\\r") + ')');
				for (i = 0; i < clashes.length; i++) clashes[i] = false;

				if (response.summaries) {
					response.summaries.each( function(summary) {
						$(summary.post_language + '_' + summary.summary_language).innerHTML = summary.summary.unescapeHTML();
					});
				}
				
				if (response.clashes) {
					response.clashes.each( function(clash) {
						clashes[clash] = true;
					});
				}
				
				for (i = 0; i < languages.length; i++) if (clashes[languages[i]] == true) return;
				if (selected > 1) $('gengo_create_group_submit').disabled = false;
			}
		}
	);
}

function gengo_set_synonym_checkbox(id) {
	$('check_' + id).checked = true;
}

function gengo_get_synblock(block_name) {
	if (block_name == -1) {
		for (i = 0; i < languages.length; i++) $('synblock_' + languages[i]).innerHTML = '';
		$('gengo_new_synblock_block').style.display = 'inline';
		$('gengo_synblock_submit').value = add_button_text;
		$('deletepost').style.display = 'none';
		$('gengo_new_synblock').value = '';
		return true;
	}
	$('gengo_new_synblock_block').style.display = 'none';
	$('deletepost').style.display = 'inline';
	$('gengo_synblock_submit').value = update_button_text;
	$('gengo_synblock_name').disabled = true;

	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_get_synblock&block_name=' + block_name,
			onSuccess: function(request) {
		    $H(eval('(' + request.responseText.gsub("\n", "\\n").gsub("\r", "\\r") + ')')).each( function (synblock) {
					$(synblock.key).innerHTML = synblock.value.unescapeHTML();
				});
				$('gengo_synblock_name').disabled = false;
			}
		}
	);
}

function gengo_select_language(append_urls) {
	var codes = $('gengo_recognised_languages');
	if (codes.value == -1) {
		return;
	} else {
		var name = $('gengo_language');
		var langname = codes.options[codes.selectedIndex].text
		var length = codes.value.indexOf('-');
		name.value = (-1 != (position = langname.indexOf('/'))) ? langname.substr(0, position) : langname;
		var code = codes.value.substr(0, length);
		$('gengo_language_code').value = code;
		$('gengo_language_charset').value = "UTF-8"; // pixline: need better fix
		$('gengo_language_locale').value = codes.value.substr(length + 1);
	}
	gengo_update_definition(append_urls);
	$('gengo_language_blog_title').focus();
}

function gengo_delete_summary(summary_id, summary_group) {
	if (!summary_group || !summary_id) return false;
	if (!confirm(delete_summary_message)) return false;
	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_delete_summary&summary_group=' + summary_group + '&summary_id=' + summary_id,
			onComplete: gengo_refresh_summary_list
		}
	);
}

function gengo_update_summary() {
	var summary_id = $('gengo_summary_id').value;
	var summary_content = $('gengo_summary_content').value;
	var summary_group = $('gengo_summary_group').value;
	if (summary_id != 0) {
		var params = 'gengo_ajax=true&gengo_action=ajax_update_summary_content&summary_id= ' + summary_id + '&summary_content=' + summary_content;
	} else {
		var language_id = $('gengo_summary_language_id').value;
		var params = 'gengo_ajax=true&gengo_action=ajax_add_summary_content&post_id=' + existing_post_id + '&summary_group=' + summary_group + '&language_id=' + language_id + '&summary_content=' + summary_content + '&translation_group=' + existing_translation_group;
	}
	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: params,
			onComplete: function(request) {
				$('gengo_summary_message').innerHTML = '<em>' + updated_summary_message + '</em>';
				$('gengo_summary_update').style.display = 'none';
				if (summary_id == 0 && summary_group == 0) $('gengo_existing_summary_group').value = request.responseText;
				gengo_refresh_summary_list();
			}
		}
	);
}

function gengo_refresh_summary_list() {
	var existing_summary_group = $('gengo_existing_summary_group').value;
	var existing_translation_group = $('gengo_existing_translation_group').value;
	var is_in_group = 'false';
	var translation_number = 0;
	if ($('gengo_translation').checked == true) {
		for (i = 0; i < document.post.gengo_translation_group.length; i++) {
			if (document.post.gengo_translation_group[i].checked) {
				val = document.post.gengo_translation_group[i].value;
				if (val != 0) {
					is_in_group = 'true';
					translation_number = val;
					$('gengo_translation_content_block').style.display = 'none';
				} else {
					is_in_group = 'false';
					translation_number = $('gengo_translation_post').value;
				}
				break;
			}
		}
	}
	var ajax_request = new Ajax.Updater(
	  'gengo_summary_block',
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_refresh_summary_lists&post_id=' + existing_post_id + '&summary_group=' + existing_summary_group + '&is_in_group=' + is_in_group + '&translation_number=' + translation_number + '&existing_translation_group=' + existing_translation_group,
			onComplete: function(request) {
				$('gengo_summary_content_block').style.display = 'none';
				gengo_unlock_controls();
			}
		}
	);
}

function gengo_lock_controls() {
	if (null != (save = $('save'))) save.disabled = true;
	if (null != (savepage = $('savepage'))) savepage.disabled = true;
	if (null != (publish = $('publish'))) publish.disabled = true;

	$('gengo_sidebar_group').style.display = 'none';
	$('gengo_sidebar_updating').style.display = 'block';
}

function gengo_unlock_controls() {
	if (null != (save = $('save'))) save.disabled = false;
	if (null != (savepage = $('savepage'))) savepage.disabled = false;
	if (null != (publish = $('publish'))) publish.disabled = false;

	$('gengo_sidebar_updating').style.display = 'none';
	$('gengo_sidebar_group').style.display = 'block';
}

function gengo_show_summary_content_block(summary_id) {
	$('gengo_summary_id').value = summary_id;
	if (summary_id == 0) {
		var language_id = $('gengo_new_summary').value;
		$('gengo_summary_legend').innerHTML = '<legend>' + summary_title + ' (' + language_names[language_id] + ')</legend>';
	  $('gengo_summary_content').value = '';
		$('gengo_summary_language_id').value = language_id;
		$('gengo_summary_message').innerHTML = '';
		$('gengo_summary_content_block').style.display = 'block';
		return;
	}

	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_get_summary_content&summary_id=' + summary_id,
			onComplete: function(request) {
				var summary = eval('(' + request.responseText.gsub("\n", "\\n").gsub("\r", "\\r") + ')');
				$('gengo_summary_content').value = summary.content.unescapeHTML();
				$('gengo_summary_legend').innerHTML = '<legend>' + summary_title + ' (' + language_names[summary.language_id] + ')</legend>';
				$('gengo_summary_language_id').value = summary.language_id;
				$('gengo_summary_message').innerHTML = '';
				$('gengo_summary_content_block').style.display = 'block';
			}
		}
	);
}

function gengo_show_translation_content_block(translation_id) {
	gengo_lock_controls();
	if (isNaN(translation_id)) {
		$('gengo_translation_content_block').style.display = 'none';
		gengo_refresh_summary_list();
		return;
	}

	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_get_translation_post&post_id=' + translation_id,
			onSuccess: function(request) {
		    var translation = eval('(' + request.responseText.gsub("\n", "\\n").gsub("\r", "\\r") + ')');
				$('gengo_translation_content').readOnly = translation.readonly;
				readonly = (translation.readonly) ? ' <em>(' + readonly_message + ')</em>' : '';
				$('gengo_translation_legend').innerHTML = translation_title + ' (' + translation.title + ')' + readonly;
				$('gengo_translation_content').dir = translation.direction;
				$('gengo_translation_content').value = translation.content.unescapeHTML();
				gengo_refresh_summary_list();
				$('gengo_translation_content_block').style.display = 'block';
			}
		}
	);
}

function gengo_update_translation_options(first_post, first_group) {
	gengo_lock_controls();
	var post_language_id = $('gengo_use_language').value;
	if (!first_post) first_post = 0;
	if (!first_group) first_group = 0;
	$('gengo_translation_content_block').style.display = 'none';
	var ajax_request = new Ajax.Updater(
		'gengo_translation_block',
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_update_translation_options&post_language_id=' + post_language_id + '&post_id=' + existing_post_id + '&post_translation_group=' + existing_translation_group + '&first_post=' + first_post + '&first_group=' + first_group,
			onComplete: gengo_refresh_summary_list
		}
	);
}

function gengo_update_translation_content() {
	var translation_id = $('gengo_translation_post').value;
	var translation_content = $('gengo_translation_content').value;
	var ajax_request = new Ajax.Request(
		script_uri, {
			parameters: 'gengo_ajax=true&gengo_action=ajax_update_translation_content&translation_id=' + translation_id + '&translation_content=' + translation_content,
			onComplete: function(request) {
				$('gengo_translation_message').innerHTML = '<em>' + translation_updated_message + '</em>';
				$('gengo_update_button').style.display = 'none';
			}
		}
	);
}

function gengo_change_language() {
	var new_language = $('gengo_use_language');
	$('content').dir = rtl[new_language.value];
	$('title').dir = rtl[new_language.value];
	gengo_update_translation_options();
}

var Gengo = window.Gengo || {};

Gengo.NotificationHandler = {
	onCreate: function() {
	  var s = (Ajax.activeRequestCount > 1) ? 's' : '';
	  $('gengo_ajax_notification_message').innerHTML = 'Working on <strong>' + Ajax.activeRequestCount + '</strong> request' + s + '...';
		$('gengo_ajax_notification').style.display = 'block';
	},

	onComplete: function(request, transport, json) {
		if (0 == Ajax.activeRequestCount) {
			$('gengo_ajax_notification').style.display = 'none';
		} else {
		  var s = (Ajax.activeRequestCount > 1) ? 's' : '';
		  $('gengo_ajax_notification_message').innerHTML = 'Working on <strong>' + Ajax.activeRequestCount + '</strong> request' + s + '...';
		}
		
		if (json.success) {
			$('gengo_ajax_feedback').style.background = '#C7DAE2';
			$('gengo_ajax_feedback').style.border = '2px solid #448ABD';
			$('gengo_ajax_feedback').innerHTML = json.success;
		} else if (json.fail) {
			$('gengo_ajax_feedback').style.background = '#FFEFF7';
			$('gengo_ajax_feedback').style.border = '2px solid solid #c69';
			$('gengo_ajax_feedback').innerHTML = '<strong>Error</strong> ' + json.fail;
		}
		$('gengo_ajax_feedback').style.display = 'block';
		window.setTimeout(function () { $('gengo_ajax_feedback').style.display = 'none'; }, 3000);
	}
};

Ajax.Responders.register(Gengo.NotificationHandler);