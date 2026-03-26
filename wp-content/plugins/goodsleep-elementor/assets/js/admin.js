document.addEventListener('DOMContentLoaded', function() {
	function createTrackRow(track) {
		var wrapper = document.createElement('div');
		wrapper.className = 'goodsleep-admin-track-row';
		wrapper.innerHTML = [
			'<div class="goodsleep-admin-track-row__field">',
			'<label>Nombre del track</label>',
			'<input type="text" class="regular-text" name="goodsleep_elementor_settings[tracks_catalog][__INDEX__][label]" value="">',
			'<input type="hidden" name="goodsleep_elementor_settings[tracks_catalog][__INDEX__][id]" value="">',
			'<input type="hidden" name="goodsleep_elementor_settings[tracks_catalog][__INDEX__][attachment_id]" value="">',
			'</div>',
			'<div class="goodsleep-admin-track-row__field">',
			'<label>Audio</label>',
			'<div class="goodsleep-admin-track-row__audio">',
			'<input type="text" class="regular-text goodsleep-admin-track-row__audio-url" name="goodsleep_elementor_settings[tracks_catalog][__INDEX__][url]" value="" readonly>',
			'<button type="button" class="button" data-select-track>Seleccionar audio</button>',
			'</div>',
			'</div>',
			'<div class="goodsleep-admin-track-row__remove">',
			'<button type="button" class="button-link-delete" data-remove-track>' + (goodsleepAdmin.removeTrackLabel || 'Eliminar') + '</button>',
			'</div>'
		].join('');

		if (track) {
			wrapper.querySelector('[name*="[label]"]').value = track.label || '';
			wrapper.querySelector('[name*="[id]"]').value = track.id || '';
			wrapper.querySelector('[name*="[attachment_id]"]').value = track.attachment_id || '';
			wrapper.querySelector('[name*="[url]"]').value = track.url || '';
		}

		return wrapper;
	}

	document.querySelectorAll('[data-goodsleep-track-manager]').forEach(function(manager) {
		var list = manager.querySelector('[data-track-list]');
		var addButton = manager.querySelector('[data-add-track]');

		function reindexRows() {
			Array.from(list.querySelectorAll('.goodsleep-admin-track-row')).forEach(function(row, index) {
				row.querySelectorAll('input').forEach(function(input) {
					input.name = input.name.replace(/\[tracks_catalog]\[(?:\d+|__INDEX__)]/, '[tracks_catalog][' + index + ']');
				});
			});
		}

		if (addButton) {
			addButton.addEventListener('click', function() {
				list.appendChild(createTrackRow());
				reindexRows();
			});
		}

		list.addEventListener('click', function(event) {
			var removeButton = event.target.closest('[data-remove-track]');
			var selectButton = event.target.closest('[data-select-track]');
			var row = event.target.closest('.goodsleep-admin-track-row');

			if (removeButton && row) {
				if (!window.confirm(goodsleepAdmin.confirmRemoveTrack || '¿Realmente deseas eliminar este track?')) {
					return;
				}

				row.remove();
				reindexRows();
				return;
			}

			if (selectButton && row && window.wp && wp.media) {
				var frame = wp.media({
					title: goodsleepAdmin.trackTitle || 'Seleccionar track de audio',
					button: { text: goodsleepAdmin.trackButton || 'Usar este audio' },
					multiple: false,
					library: { type: ['audio'] }
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					row.querySelector('[name*="[attachment_id]"]').value = attachment.id || '';
					row.querySelector('[name*="[id]"]').value = 'track-' + (attachment.id || Date.now());
					row.querySelector('[name*="[url]"]').value = attachment.url || '';

					var labelInput = row.querySelector('[name*="[label]"]');
					if (labelInput && !labelInput.value) {
						labelInput.value = attachment.title || attachment.filename || '';
					}
				});

				frame.open();
			}
		});

		reindexRows();
	});

	document.querySelectorAll('[data-goodsleep-media-field]').forEach(function(field) {
		var selectButton = field.querySelector('[data-select-media]');
		var clearButton = field.querySelector('[data-clear-media]');
		var idInput = field.querySelector('[data-media-id]');
		var urlInput = field.querySelector('[data-media-url]');

		function syncClearState() {
			if (clearButton) {
				clearButton.disabled = !urlInput || !urlInput.value;
			}
		}

		if (selectButton && window.wp && wp.media) {
			selectButton.addEventListener('click', function() {
				var frame = wp.media({
					title: goodsleepAdmin.imageTitle || 'Seleccionar imagen del producto',
					button: { text: goodsleepAdmin.imageButton || 'Usar esta imagen' },
					multiple: false,
					library: { type: ['image'] }
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();

					if (idInput) {
						idInput.value = attachment.id || '';
					}

					if (urlInput) {
						urlInput.value = attachment.url || '';
					}

					syncClearState();
				});

				frame.open();
			});
		}

		if (clearButton) {
			clearButton.addEventListener('click', function() {
				if (idInput) {
					idInput.value = '';
				}

				if (urlInput) {
					urlInput.value = '';
				}

				syncClearState();
			});
		}

		syncClearState();
	});

	document.querySelectorAll('.goodsleep-admin-picklist').forEach(function(picklist) {
		var search = picklist.querySelector('.goodsleep-admin-picklist__search');
		var languageFilter = picklist.querySelector('[data-picklist-languages]');
		var selectAllButton = picklist.querySelector('[data-picklist-select-all]');
		var clearButton = picklist.querySelector('[data-picklist-clear]');
		var items = Array.from(picklist.querySelectorAll('.goodsleep-admin-picklist__item'));

		if (!search) {
			return;
		}

		function filterItems() {
			var query = search.value.trim().toLocaleLowerCase();
			var selectedLanguages = languageFilter ? Array.from(languageFilter.selectedOptions).map(function(option) {
				return option.value.toLocaleLowerCase();
			}) : [];

			items.forEach(function(item) {
				var searchText = (item.getAttribute('data-search-text') || item.textContent || '').toLocaleLowerCase();
				var itemLanguage = (item.getAttribute('data-language') || '').toLocaleLowerCase();
				var matchesQuery = !query || searchText.indexOf(query) !== -1;
				var matchesLanguage = !selectedLanguages.length || selectedLanguages.indexOf(itemLanguage) !== -1;

				item.style.display = matchesQuery && matchesLanguage ? '' : 'none';
			});
		}

		search.addEventListener('input', filterItems);

		if (languageFilter) {
			languageFilter.addEventListener('mousedown', function(event) {
				var option = event.target;

				if (option.tagName !== 'OPTION') {
					return;
				}

				event.preventDefault();
				option.selected = !option.selected;
				filterItems();
			});

			languageFilter.addEventListener('change', filterItems);
		}

		if (selectAllButton && languageFilter) {
			selectAllButton.addEventListener('click', function() {
				Array.from(languageFilter.options).forEach(function(option) {
					option.selected = true;
				});

				filterItems();
			});
		}

		if (clearButton && languageFilter) {
			clearButton.addEventListener('click', function() {
				Array.from(languageFilter.options).forEach(function(option) {
					option.selected = false;
				});

				filterItems();
			});
		}

		filterItems();
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
