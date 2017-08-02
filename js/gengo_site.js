function gengo_language_remove(id) {
	$('gengo_available_languages').appendChild(document.getElementById('gengo_language_' + id));
	$('gengo_control_set_' + id).innerHTML = '<a class="gengo_element_control" href="#" onclick="return gengo_language_add(' + id + ');">' + add_message + '</a>';
	return false;
}

function gengo_language_add(id) {
  var node = document.getElementById('gengo_language_' + id)
	$('gengo_viewing_languages').appendChild(node);
	$('gengo_control_set_' + id).innerHTML = '<a class="gengo_element_control" name="up" href="#" onclick="return gengo_language_move_up(' + id + ');">' + up_message + '</a> <a class="gengo_element_control" href="#" onclick="return gengo_language_move_down(' + id + ');">' + down_message + '</a> <a class="gengo_element_control" href="#" onclick="return gengo_language_remove(' + id + ');">' + x_message + '</a>';
	return false;
}

// gengo_create_cookie modified from code found at quirksmode.org
function gengo_create_cookie(name, value) {
	var date = new Date();
	date.setTime(date.getTime() + 30000000);
	var expires = "; expires=" + date.toGMTString();
	document.cookie = name + "=" + value + expires + "; path=" + cookie_path;
}

function gengo_language_save() {
  var nodes = $('gengo_viewing_languages').childNodes;
	var code_string = '';
	var relocation_string = '';
	for (var i = 0; i < nodes.length; i++) {
    if (nodes[i].tagName == 'LI') {
			var id = nodes[i].id.substr('gengo_language_'.length);
			if (code_string != '') code_string += encoded_divider;
			if (relocation_string != '') relocation_string += language_divider;
			code_string += language_codes[id];
			relocation_string += language_codes[id];
		}
	}
	if ('' == code_string) {
		alert(save_error_message);
	} else {
		gengo_create_cookie(cookie_name, code_string);
		window.location = site_home + '/' + relocation_string + '/';
	}
	return false;
}

function gengo_language_reset() {
  var viewable_codes = original_codes.split(language_divider);
  var other_codes = viewable_codes.diff(language_codes);
	var language_id;
	for (var i = 0; i < viewable_codes.length; i++) {
		language_id = language_ids[viewable_codes[i]];
		gengo_language_add(language_id);
	}
	for (var i = 0; i < other_codes.length; i++) {
		language_id = language_ids[other_codes[i]];
		gengo_language_remove(language_id);
	}
	return false;
}

function gengo_switch_summary(summary_id) {
	if (current_id == summary_id) return false;
	$('gengo_summary_' + current_id).style.display = 'none';
	$('gengo_summary_' + summary_id).style.display = 'block';
	current_id = summary_id;
}

// From: http://www.mozilla.org/docs/dom/technote/whitespace/
function is_all_ws(nod) {
  return !(/[^\t\n\r ]/.test(nod.data));
}

function is_ignorable(nod) {
  return (nod.nodeType == 8) || ((nod.nodeType == 3) && is_all_ws(nod));
}

function findPrevNode(node) {
  while ((node = node.previousSibling)) {
    if (!is_ignorable(node)) return node;
  }
  return null;
}

function findNextNode(node) {
  while ((node = node.nextSibling)) {
    if (!is_ignorable(node)) return node;
  }
  return null;
}

// from: http://www.xs4all.nl/~zanstra/logs/jsLog.htm
function DOMNode_swapNode(n1,n2) {
  n1.parentNode.insertBefore(n2.parentNode.removeChild(n2),n1);
}

function gengo_language_move_up(id) {
  var node = $('gengo_language_' + id);
  var previous_node = findPrevNode(node);
  if (previous_node) {
   DOMNode_swapNode(previous_node, node);
 	}
	return false;
}

function gengo_language_move_down(id) {
  var node = $('gengo_language_' + id);
  var next_node = findNextNode(node);
  if (next_node) {
    DOMNode_swapNode(node, next_node);
  }
	return false;
}