( function() {
	'use strict';

	const storiesWidgets = [];
	const iconMoon = '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="none" stroke="currentColor" d="M8.09 14.41c-.36 0-.75-.03-1.12-.09-2.75-.43-4.99-2.55-5.56-5.27C.79 6.13 2.05 3.25 4.62 1.71l.21-.13.87.38-.19.45C4.46 4.73 4.97 7.36 6.79 9.1c1.76 1.69 4.38 2.07 6.67.98l1.29-.61-.65 1.27c-1.19 2.31-3.48 3.67-6.01 3.67"></path></svg>';
	const iconShare = '<svg viewBox="0 0 16 16" aria-hidden="true"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38-.03-.24.16-.51.43-.51l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M8.48 10.08c0 .28-.23.45-.44.46-.18.01-.44-.16-.44-.4V1.65L5.4 3.77c-.16.16-.49.11-.62-.05-.12-.14-.13-.45.03-.6L7.7.29c.18-.18.5-.3.71-.09l2.89 2.89c.16.16.19.41.08.6-.08.16-.45.26-.61.1L8.48 1.53v8.55Z"></path></g></svg>';
	const iconDownload = '<svg viewBox="0 0 16 16" aria-hidden="true"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38C9.67 5.37 9.86 5.1 10.13 5.1l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M7.66.55c0-.28.23-.45.44-.46.18-.01.44.16.44.4v8.49l2.2-2.12c.16-.16.49-.11.62.05.12.14.13.45-.03.6l-2.89 2.83c-.18.18-.5.3-.71.09L4.84 7.54c-.16-.16-.19-.41-.08-.6.08-.16.45-.26.61-.1L7.66 9.1V.55Z"></path></g></svg>';
	const iconFavorite = '<svg viewBox="0 0 16 16" aria-hidden="true"><polygon fill="none" stroke="currentColor" stroke-miterlimit="10" points="8.02,11.38 4.1,14.36 5.61,9.51 1.75,6.55 6.44,6.55 8.01,1.86 9.57,6.55 14.26,6.55 10.41,9.52 11.92,14.36"></polygon></svg>';

	function formatWhatsAppUrl( template, name, shareUrl ) {
		const message = ( template || 'Nada le quita el sueno a %s. Escucha esta historia: %s' )
			.replace( '%s', name || '' )
			.replace( '%s', shareUrl || '' );

		return 'https://wa.me/?text=' + encodeURIComponent( message );
	}

	function escapeHtml( value ) {
		return String( value || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function formatRatingSummary( story ) {
		const voteCount = Number( story.voteCount || 0 );
		const voteAverage = Number( story.voteAverage || 0 );
		const hasRating = voteCount > 0 || voteAverage > 0;

		return hasRating ? `${ voteAverage.toFixed( 1 ) }/5` : 'Sin votos';
	}

	function buildRatingMarkup( story ) {
		const ratingValue = Number( story.voteAverage || 0 );
		const voteCount = Number( story.voteCount || 0 );
		const hasRating = voteCount > 0 || ratingValue > 0;
		const moonCount = hasRating ? Math.min( 5, Math.max( 0, Math.round( ratingValue ) ) ) : 0;
		const isReadonly = !! story.userHasVoted;
		const tooltipBase = isReadonly ? 'Ya votaste hoy.' : 'Haz clic para votar una vez hoy.';
		let markup = `<div class="goodsleep-story-card__rating${ isReadonly ? ' is-readonly' : '' }" data-rating-group data-readonly="${ isReadonly ? 'true' : 'false' }" aria-label="Promedio ${ ratingValue.toFixed( 1 ) } de 5 basado en ${ Number( story.voteCount || 0 ) } votos">`;

		for ( let index = 1; index <= 5; index += 1 ) {
			const isActive = index <= moonCount;
			const tooltip = isReadonly ? tooltipBase : `Votar con ${ index } ${ 1 === index ? 'luna' : 'lunas' }.`;
			markup += `
				<button
					type="button"
					class="goodsleep-story-card__moon${ isActive ? ' is-active' : '' }"
					data-action="vote"
					data-rating="${ index }"
					data-tooltip="${ escapeHtml( tooltip ) }"
					aria-label="${ escapeHtml( tooltip ) }"
					${ isReadonly ? 'disabled' : '' }
				>
					${ iconMoon }
				</button>
			`;
		}

		markup += '</div>';

		return markup;
	}

	function buildMediaMarkup( story ) {
		if ( 'video' === story.mediaType && story.videoUrl ) {
			return `
				<div class="goodsleep-story-card__media-wrap" hidden data-video-wrap>
					<video controls playsinline preload="none" data-video-src="${ escapeHtml( story.videoUrl ) }"></video>
				</div>
			`;
		}

		if ( 'audio' === story.mediaType && story.audioUrl && ! goodsleepElementor.videoPublicOnly ) {
			return `<audio controls preload="metadata" src="${ escapeHtml( story.audioUrl ) }"></audio>`;
		}

		return '';
	}

	function buildPrimaryAction( story ) {
		if ( 'video' === story.mediaType && story.videoUrl ) {
			return `
				<button
					type="button"
					class="goodsleep-story-card__action-button"
					data-action="toggle-video"
					data-tooltip="Ver video"
					aria-label="Ver video"
				>
					<span class="goodsleep-story-card__action-icon">${ iconDownload }</span>
					<span class="goodsleep-story-card__action-label">Ver video</span>
				</button>
			`;
		}

		if ( story.downloadUrl ) {
			return `
				<a
					href="${ escapeHtml( story.downloadUrl ) }"
					class="goodsleep-story-card__action-button"
					download
					data-tooltip="${ 'video' === story.mediaType ? 'Descargar video' : 'Descargar audio' }"
					aria-label="${ 'video' === story.mediaType ? 'Descargar video' : 'Descargar audio' }"
				>
					<span class="goodsleep-story-card__action-icon">${ iconDownload }</span>
					<span class="goodsleep-story-card__action-label">Descargar</span>
				</a>
			`;
		}

		return '';
	}

	function renderStoryCard( story ) {
		const title = escapeHtml( story.title || '' );
		const text = escapeHtml( story.text || '' );
		const publishedLabel = escapeHtml( story.publishedLabel || '' );
		const shareUrl = formatWhatsAppUrl( goodsleepElementor.whatsappTemplate, story.title, story.shareUrl || '' );
		const favoriteLabel = story.favorite ? 'Quitar de favoritos' : 'Agregar a favoritos';
		const ratingSummary = formatRatingSummary( story );

		return `
			<article class="goodsleep-story-card" data-story-id="${ story.id }">
				<div class="goodsleep-story-card__topline">
					<span class="goodsleep-story-card__title">${ title }</span>
					<time class="goodsleep-story-card__date" datetime="${ escapeHtml( story.createdAt || '' ) }">${ publishedLabel }</time>
				</div>
				<p class="goodsleep-story-card__text">${ text }</p>
				${ buildMediaMarkup( story ) }
				<div class="goodsleep-story-card__actions">
					<div class="goodsleep-story-card__action-group">
						<button
							type="button"
							class="goodsleep-story-card__action-button${ story.favorite ? ' is-active' : '' }"
							data-action="favorite"
							data-tooltip="${ favoriteLabel }"
							aria-label="${ favoriteLabel }"
							aria-pressed="${ story.favorite ? 'true' : 'false' }"
						>
							<span class="goodsleep-story-card__action-icon">${ iconFavorite }</span>
							<span class="goodsleep-story-card__action-label">Favorito</span>
						</button>
						${ buildPrimaryAction( story ) }
						<a
							href="${ escapeHtml( shareUrl ) }"
							class="goodsleep-story-card__action-button"
							target="_blank"
							rel="noopener noreferrer"
							data-tooltip="Compartir historia"
							aria-label="Compartir historia"
						>
							<span class="goodsleep-story-card__action-icon">${ iconShare }</span>
							<span class="goodsleep-story-card__action-label">Compartir</span>
						</a>
					</div>
					<div class="goodsleep-story-card__rating-wrap">
						<span class="goodsleep-story-card__rating-summary">${ ratingSummary }</span>
						${ buildRatingMarkup( story ) }
					</div>
				</div>
			</article>
		`;
	}

	function syncRatingCard( card, payload ) {
		if ( ! card || ! payload ) {
			return;
		}

		const summary = card.querySelector( '.goodsleep-story-card__rating-summary' );
		const ratingWrap = card.querySelector( '.goodsleep-story-card__rating-wrap' );
		const story = {
			voteAverage: Number( payload.voteAverage || 0 ),
			voteCount: Number( payload.voteCount || 0 ),
			userHasVoted: !! payload.userHasVoted
		};

		if ( summary ) {
			summary.textContent = formatRatingSummary( story );
		}

		if ( ratingWrap ) {
			const currentGroup = ratingWrap.querySelector( '[data-rating-group]' );
			if ( currentGroup ) {
				currentGroup.outerHTML = buildRatingMarkup( story );
			}
		}
	}

	function bindRatingInteractions( root ) {
		if ( ! root ) {
			return;
		}

		root.addEventListener( 'click', async function( event ) {
			const button = event.target.closest( '[data-action="vote"]' );
			const card = button ? button.closest( '[data-story-id]' ) : null;
			const storyId = card ? card.dataset.storyId : '';

			if ( ! button || ! storyId ) {
				return;
			}

			try {
				const rating = Number( button.dataset.rating || 0 );
				const payload = await requestJson( `stories/${ storyId }/vote`, {
					method: 'POST',
					body: JSON.stringify( { rating } )
				} );

				syncRatingCard( card, payload );
			} catch ( error ) {
				window.alert( error.message );
			}
		} );
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

	function sanitizeFeedbackMessage( message ) {
		const wrapper = document.createElement( 'div' );
		wrapper.innerHTML = message || '';

		return ( wrapper.textContent || wrapper.innerText || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function clearPageHash() {
		if ( ! window.location.hash || ! window.history || ! window.history.replaceState ) {
			return;
		}

		window.history.replaceState( null, document.title, window.location.pathname + window.location.search );
	}

	async function pollStoryStatus( storyId, attempts ) {
		let remaining = attempts;
		const interval = Number( goodsleepElementor.pollInterval || 5 ) * 1000;

		while ( remaining > 0 ) {
			const payload = await requestJson( `stories/${ storyId }/status`, { method: 'GET' } );
			if ( payload.isReady ) {
				return payload;
			}

			if ( payload.error ) {
				throw new Error( payload.error );
			}

			remaining -= 1;
			if ( remaining <= 0 ) {
				return payload;
			}

			await new Promise( function( resolve ) {
				window.setTimeout( resolve, interval );
			} );
		}

		return null;
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
		const videoNode = container.querySelector( '[data-result-video]' );
		const resultCopy = container.querySelector( '[data-result-copy]' );
		const resultActions = container.querySelector( '[data-result-actions]' );
		const downloadLink = container.querySelector( '[data-download-link]' );
		const shareLink = container.querySelector( '[data-share-link]' );
		const phraseTemplate = container.dataset.phraseTemplate || '';
		const loaderTemplate = container.dataset.loaderTemplate || '';

		if ( ! form ) {
			return;
		}

		function resetGeneratorState() {
			form.reset();
			formSurface.hidden = false;
			loadingSurface.hidden = true;
			resultSurface.hidden = true;
			feedback.textContent = '';
			loaderText.textContent = '';
			if ( resultCopy ) {
				resultCopy.textContent = 'Tu video se esta procesando. Te enviaremos el link por correo cuando este listo.';
			}
			if ( resultActions ) {
				resultActions.hidden = true;
			}
			videoNode.hidden = true;
			videoNode.removeAttribute( 'src' );
			videoNode.load();
			if ( downloadLink ) {
				downloadLink.href = '#';
			}
			if ( shareLink ) {
				shareLink.href = '#';
			}
			charNode.textContent = '0';
			phraseNode.textContent = phraseTemplate ? phraseTemplate.replace( '%s', '' ) : '';
		}

		function syncSurfaceMinHeight() {
			const formHeight = formSurface ? formSurface.offsetHeight : 0;

			if ( formHeight > 0 ) {
				container.style.minHeight = `${ formHeight }px`;
				loadingSurface.style.minHeight = `${ formHeight }px`;
				resultSurface.style.minHeight = `${ formHeight }px`;
			}
		}

		resetGeneratorState();
		syncSurfaceMinHeight();
		window.addEventListener( 'resize', syncSurfaceMinHeight );

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
					return 'Ingresa tu correo electronico.';
				}

				if ( field.validity.typeMismatch ) {
					return 'Ingresa un correo electronico valido.';
				}
			}

			if ( 'story_text' === field.name && ! field.value.trim() ) {
				return 'Escribe tu historia.';
			}

			if ( 'track_id' === field.name && ! field.value ) {
				return 'Selecciona una musica.';
			}

			if ( 'accepted_terms' === field.name && ! field.checked ) {
				return 'Debes aceptar los terminos y condiciones.';
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
			const trackSelect = form.querySelector( '[name="track_id"]' );
			const trackLabel = trackSelect && trackSelect.selectedOptions[0] ? trackSelect.selectedOptions[0].dataset.label || trackSelect.selectedOptions[0].textContent : '';

			formSurface.hidden = true;
			loadingSurface.hidden = false;
			loaderText.textContent = ( loaderTemplate || 'Nada le quita el sueno a %s' ).replace( '%s', name );
			syncSurfaceMinHeight();

			try {
				const created = await requestJson( 'generate-story', {
					method: 'POST',
					body: JSON.stringify( {
						name: name,
						email: ( formData.get( 'email' ) || '' ).toString(),
						story_text: ( formData.get( 'story_text' ) || '' ).toString(),
						phrase_template: phraseTemplate,
						track_id: ( formData.get( 'track_id' ) || '' ).toString(),
						track_label: trackLabel,
						accepted_terms: !! formData.get( 'accepted_terms' )
					} )
				} );

				loadingSurface.hidden = true;
				resultSurface.hidden = false;
				if ( resultCopy ) {
					resultCopy.textContent = 'Tu video se esta procesando. Te enviaremos el link por correo cuando este listo.';
				}
				if ( resultActions ) {
					resultActions.hidden = true;
				}
				videoNode.hidden = true;
				document.dispatchEvent( new CustomEvent( 'goodsleep:story-created', { detail: created } ) );
				syncSurfaceMinHeight();
			} catch ( error ) {
				loadingSurface.hidden = true;
				formSurface.hidden = false;
				feedback.textContent = sanitizeFeedbackMessage( error.message ) || 'Ocurrio un error al generar el video.';
				syncSurfaceMinHeight();
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
				clearPageHash();
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
				clearPageHash();
				query = search.value.trim();
				loadStories( true );
			} );
		}

		list.addEventListener( 'click', async function( event ) {
			clearPageHash();
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
					button.classList.toggle( 'is-active', !! payload.favorite );
					button.setAttribute( 'aria-pressed', payload.favorite ? 'true' : 'false' );
					button.setAttribute( 'aria-label', payload.favorite ? 'Quitar de favoritos' : 'Agregar a favoritos' );
					button.dataset.tooltip = payload.favorite ? 'Quitar de favoritos' : 'Agregar a favoritos';
				}

				if ( 'vote' === button.dataset.action ) {
					const rating = Number( button.dataset.rating || 0 );
					const payload = await requestJson( `stories/${ storyId }/vote`, {
						method: 'POST',
						body: JSON.stringify( { rating } )
					} );

					syncRatingCard( card, payload );
					loadStories( true );
				}

				if ( 'toggle-video' === button.dataset.action ) {
					const wrap = card.querySelector( '[data-video-wrap]' );
					const video = wrap ? wrap.querySelector( 'video' ) : null;
					if ( ! wrap || ! video ) {
						return;
					}

					const isHidden = wrap.hidden;
					if ( isHidden && ! video.getAttribute( 'src' ) ) {
						video.setAttribute( 'src', video.dataset.videoSrc || '' );
						video.load();
					}

					wrap.hidden = ! isHidden;
					button.querySelector( '.goodsleep-story-card__action-label' ).textContent = isHidden ? 'Ocultar video' : 'Ver video';
				}
			} catch ( error ) {
				window.alert( error.message );
			}
		} );

		bindRatingInteractions( list );

		storiesWidgets.push( {
			container,
			reload: function() {
				sort = 'recent';
				query = '';

				if ( search ) {
					search.value = '';
				}

				filterButtons.forEach( function( item ) {
					item.classList.toggle( 'is-active', item.dataset.sort === 'recent' );
				} );

				loadStories( true );
			}
		} );

		loadStories( true );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.goodsleep-generator' ).forEach( initGenerator );
		document.querySelectorAll( '.goodsleep-stories' ).forEach( initStories );
		document.querySelectorAll( '[data-story-detail]' ).forEach( bindRatingInteractions );
	} );

	document.addEventListener( 'goodsleep:story-created', function() {
		storiesWidgets.forEach( function( widget ) {
			widget.reload();
		} );
	} );
}() );
