( function() {
	'use strict';

	function formatWhatsAppUrl( template, name, shareUrl ) {
		const message = ( template || 'Nada le quita el sueno a %s. Escucha esta historia: %s' )
			.replace( '%s', name || '' )
			.replace( '%s', shareUrl || '' );

		return 'https://wa.me/?text=' + encodeURIComponent( message );
	}

	function renderStoryCard( story ) {
		const moons = Array.from( { length: 4 }, function( _, index ) {
			return index < story.moonCount ? 'O' : 'o';
		} ).join( ' ' );

		return `
			<article class="goodsleep-story-card" data-story-id="${ story.id }">
				<div class="goodsleep-story-card__topline">
					<span class="goodsleep-story-card__title">${ story.title }</span>
					<span class="goodsleep-story-card__moons">${ moons }</span>
				</div>
				<p>${ story.text || '' }</p>
				<audio controls preload="metadata" src="${ story.audioUrl || '' }"></audio>
				<div class="goodsleep-story-card__actions">
					<div>
						<button type="button" data-action="favorite">${ story.favorite ? 'Quitar favorito' : 'Favorito' }</button>
						<button type="button" data-action="vote">Votar</button>
					</div>
					<div>
						<a href="${ story.downloadUrl || '#' }" download title="Descargar">Descargar</a>
					</div>
				</div>
			</article>
		`;
	}

	async function requestJson( path, options ) {
		const response = await fetch( goodsleepElementor.restUrl + path, {
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': goodsleepElementor.nonce
			},
			...options
		} );

		const payload = await response.json().catch( function() {
			return {};
		} );

		if ( ! response.ok ) {
			throw new Error( payload.message || 'Error en la solicitud.' );
		}

		return payload;
	}

	function initGenerator( container ) {
		const formSurface = container.querySelector( '.goodsleep-generator__surface--form' );
		const loadingSurface = container.querySelector( '.goodsleep-generator__surface--loading' );
		const resultSurface = container.querySelector( '.goodsleep-generator__surface--result' );
		const form = container.querySelector( 'form' );
		const feedback = container.querySelector( '[data-feedback]' );
		const phraseNode = container.querySelector( '[data-dynamic-phrase]' );
		const charNode = container.querySelector( '[data-char-count]' );
		const loaderText = container.querySelector( '[data-loader-text]' );
		const audioNode = container.querySelector( '[data-result-audio]' );
		const downloadLink = container.querySelector( '[data-download-link]' );
		const shareLink = container.querySelector( '[data-share-link]' );
		const phraseTemplate = container.dataset.phraseTemplate || '';
		const loaderTemplate = container.dataset.loaderTemplate || '';

		if ( ! form ) {
			return;
		}

		function getValidationMessage( field ) {
			if ( ! field ) {
				return '';
			}

			if ( 'name' === field.name ) {
				if ( field.value !== field.value.replace( /\s+/g, '' ) ) {
					return 'El nombre no puede contener espacios.';
				}

				if ( ! field.value.trim() ) {
					return 'Ingresa tu nombre.';
				}
			}

			if ( 'email' === field.name ) {
				if ( ! field.value.trim() ) {
					return 'Ingresa tu correo electrónico.';
				}

				if ( field.validity.typeMismatch ) {
					return 'Ingresa un correo electrónico válido.';
				}
			}

			if ( 'story_text' === field.name && ! field.value.trim() ) {
				return 'Escribe tu historia.';
			}

			if ( 'voice_id' === field.name && ! field.value ) {
				return 'Selecciona una voz.';
			}

			if ( 'track_id' === field.name && ! field.value ) {
				return 'Selecciona una música.';
			}

			if ( 'accepted_terms' === field.name && ! field.checked ) {
				return 'Debes aceptar los términos y condiciones.';
			}

			return '';
		}

		form.querySelectorAll( 'input, textarea, select' ).forEach( function( field ) {
			field.addEventListener( 'invalid', function() {
				field.setCustomValidity( getValidationMessage( field ) );
			} );

			field.addEventListener( 'input', function() {
				field.setCustomValidity( '' );
			} );

			field.addEventListener( 'change', function() {
				field.setCustomValidity( '' );
			} );
		} );

		const nameField = form.querySelector( '[name="name"]' );
		if ( nameField ) {
			nameField.addEventListener( 'keydown', function( event ) {
				if ( ' ' === event.key ) {
					event.preventDefault();
				}
			} );
		}

		form.addEventListener( 'input', function( event ) {
			if ( event.target.name === 'name' ) {
				event.target.value = event.target.value.replace( /\s+/g, '' );
				const value = event.target.value.trim();
				phraseNode.textContent = phraseTemplate ? phraseTemplate.replace( '%s', value ) : '';
			}

			if ( event.target.name === 'story_text' ) {
				charNode.textContent = event.target.value.length;
			}
		} );

		form.addEventListener( 'submit', async function( event ) {
			event.preventDefault();
			feedback.textContent = '';

			if ( ! form.reportValidity() ) {
				return;
			}

			const formData = new FormData( form );
			const name = ( formData.get( 'name' ) || '' ).toString().trim();
			const voiceSelect = form.querySelector( '[name="voice_id"]' );
			const trackSelect = form.querySelector( '[name="track_id"]' );
			const voiceLabel = voiceSelect && voiceSelect.selectedOptions[0] ? voiceSelect.selectedOptions[0].dataset.label || voiceSelect.selectedOptions[0].textContent : '';
			const trackLabel = trackSelect && trackSelect.selectedOptions[0] ? trackSelect.selectedOptions[0].dataset.label || trackSelect.selectedOptions[0].textContent : '';

			formSurface.hidden = true;
			loadingSurface.hidden = false;
			loaderText.textContent = ( loaderTemplate || 'Nada le quita el sueno a %s' ).replace( '%s', name );

			try {
				const payload = await requestJson( 'generate-story', {
					method: 'POST',
					body: JSON.stringify( {
						name: name,
						email: ( formData.get( 'email' ) || '' ).toString(),
						story_text: ( formData.get( 'story_text' ) || '' ).toString(),
						phrase_template: phraseTemplate,
						voice_id: ( formData.get( 'voice_id' ) || '' ).toString(),
						voice_label: voiceLabel,
						track_id: ( formData.get( 'track_id' ) || '' ).toString(),
						track_label: trackLabel,
						accepted_terms: !! formData.get( 'accepted_terms' )
					} )
				} );

				audioNode.src = payload.audioUrl || '';
				downloadLink.href = payload.downloadUrl || '#';
				shareLink.href = formatWhatsAppUrl( goodsleepElementor.whatsappTemplate, name, payload.shareUrl || '' );

				loadingSurface.hidden = true;
				resultSurface.hidden = false;
			} catch ( error ) {
				loadingSurface.hidden = true;
				formSurface.hidden = false;
				feedback.textContent = error.message;
			}
		} );
	}

	function initStories( container ) {
		const viewport = container.querySelector( '[data-viewport]' );
		const list = container.querySelector( '[data-list]' );
		const sentinel = container.querySelector( '[data-sentinel]' );
		const search = container.querySelector( '[data-search]' );
		const filterButtons = Array.from( container.querySelectorAll( '[data-sort]' ) );
		let page = 1;
		let maxPages = 1;
		let sort = 'recent';
		let query = '';
		let loading = false;

		if ( ! viewport || ! list || ! sentinel ) {
			return;
		}

		async function loadStories( reset ) {
			if ( loading ) {
				return;
			}

			loading = true;

			if ( reset ) {
				page = 1;
				list.innerHTML = '';
			}

			try {
				const payload = await requestJson( `stories?page=${ page }&sort=${ encodeURIComponent( sort ) }&search=${ encodeURIComponent( query ) }`, { method: 'GET' } );
				maxPages = payload.maxPages || 1;

				if ( ! payload.items.length && 1 === page ) {
					list.innerHTML = `<p>${ container.dataset.emptyState || 'Todavia no hay historias.' }</p>`;
				} else {
					list.insertAdjacentHTML( 'beforeend', payload.items.map( renderStoryCard ).join( '' ) );
				}
			} catch ( error ) {
				if ( 1 === page ) {
					list.innerHTML = `<p>${ error.message }</p>`;
				}
			}

			loading = false;
		}

		const observer = new IntersectionObserver( function( entries ) {
			entries.forEach( function( entry ) {
				if ( entry.isIntersecting && page < maxPages && ! loading ) {
					page += 1;
					loadStories( false );
				}
			} );
		}, { root: viewport, threshold: 0.2 } );

		observer.observe( sentinel );

		filterButtons.forEach( function( button ) {
			button.addEventListener( 'click', function() {
				filterButtons.forEach( function( item ) {
					item.classList.remove( 'is-active' );
				} );
				button.classList.add( 'is-active' );
				sort = button.dataset.sort || 'recent';
				loadStories( true );
			} );
		} );

		if ( search ) {
			search.addEventListener( 'input', function() {
				query = search.value.trim();
				loadStories( true );
			} );
		}

		list.addEventListener( 'click', async function( event ) {
			const button = event.target.closest( '[data-action]' );

			if ( ! button ) {
				return;
			}

			const card = button.closest( '[data-story-id]' );
			const storyId = card ? card.dataset.storyId : '';

			if ( ! storyId ) {
				return;
			}

			try {
				if ( 'favorite' === button.dataset.action ) {
					const payload = await requestJson( `stories/${ storyId }/favorite`, { method: 'POST', body: '{}' } );
					button.textContent = payload.favorite ? 'Quitar favorito' : 'Favorito';
				}

				if ( 'vote' === button.dataset.action ) {
					await requestJson( `stories/${ storyId }/vote`, { method: 'POST', body: '{}' } );
					loadStories( true );
				}
			} catch ( error ) {
				window.alert( error.message );
			}
		} );

		loadStories( true );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.goodsleep-generator' ).forEach( initGenerator );
		document.querySelectorAll( '.goodsleep-stories' ).forEach( initStories );
	} );
}() );
