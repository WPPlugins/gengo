Array.prototype.in_array = function(search_term) {
  var i = this.length;
	do {
		if (this[i] === search_term) return true;
	} while (i--);
  return false;
}

// Returns all the elements of second_array that are not in this array.
Array.prototype.diff = function(second_array) {
  var return_array = Array();
	var i = second_array.length;
	var j = 0;
	do {
		if (!this.in_array(second_array[i])) {
			return_array[j++] = second_array[i];
		}
	} while (i--);
	return return_array;
}