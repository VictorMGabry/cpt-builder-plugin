(function () {
	'use strict';

	function normalize(value) {
		return (value || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
	}

	document.addEventListener('click', function (event) {
		if (event.target && event.target.id === 'vcomint-add-meta-row') {
			var tableBody = document.querySelector('#vcomint-meta-table tbody');
			var template = document.querySelector('#vcomint-meta-row-template');
			if (!tableBody || !template) return;
			var index = Date.now().toString();
			var html = template.innerHTML.replace(/__INDEX__/g, index);
			var temp = document.createElement('tbody');
			temp.innerHTML = html.trim();
			tableBody.appendChild(temp.firstElementChild);
		}

		if (event.target && event.target.classList.contains('vcomint-remove-meta-row')) {
			var row = event.target.closest('tr');
			if (row) row.remove();
		}
	});

	document.addEventListener('input', function (event) {
		if (!event.target || !event.target.classList.contains('vcomint-cpt-relationship-search')) {
			return;
		}

		var field = event.target.closest('[data-vcomint-relationship-field]');
		if (!field) return;

		var query = normalize(event.target.value);
		var options = field.querySelectorAll('[data-vcomint-relationship-option]');
		options.forEach(function (option) {
			var title = option.getAttribute('data-title') || '';
			option.hidden = query && title.indexOf(query) === -1;
		});
	});
})();
