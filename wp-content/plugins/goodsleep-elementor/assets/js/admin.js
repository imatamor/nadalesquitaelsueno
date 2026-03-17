document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.goodsleep-admin-picklist').forEach(function(picklist) {
		var search = picklist.querySelector('.goodsleep-admin-picklist__search');
		var items = Array.from(picklist.querySelectorAll('.goodsleep-admin-picklist__item'));

		if (!search) {
			return;
		}

		search.addEventListener('input', function() {
			var query = search.value.trim().toLowerCase();

			items.forEach(function(item) {
				item.style.display = !query || item.textContent.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
			});
		});
	});

	var syncButton = document.querySelector('[data-goodsleep-sync-voices]');
	var feedback = document.querySelector('[data-goodsleep-sync-feedback]');

	if (syncButton && window.goodsleepAdmin) {
		syncButton.addEventListener('click', function() {
			syncButton.disabled = true;

			if (feedback) {
				feedback.textContent = 'Sincronizando...';
			}

			fetch(goodsleepAdmin.restUrl + 'catalog/voices/sync', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': goodsleepAdmin.nonce
				},
				body: '{}'
			}).then(function(response) {
				return response.json().then(function(payload) {
					if (!response.ok) {
						throw new Error(payload.message || 'No se pudieron sincronizar las voces.');
					}

					return payload;
				});
			}).then(function(payload) {
				if (feedback) {
					feedback.textContent = 'Voces sincronizadas: ' + payload.length + '. Recarga la pagina para verlas.';
				}
			}).catch(function(error) {
				if (feedback) {
					feedback.textContent = error.message;
				}
			}).finally(function() {
				syncButton.disabled = false;
			});
		});
	}
});
