( function() {
	'use strict';

	function initRulesScenes() {
		var scenes = document.querySelectorAll( '.goodsleep-rules-scene' );

		if ( ! scenes.length || typeof IntersectionObserver === 'undefined' ) {
			return;
		}

		var timers = new WeakMap();
		var observer = new IntersectionObserver( function( entries ) {
			entries.forEach( function( entry ) {
				var scene = entry.target;
				var delay = parseInt( scene.getAttribute( 'data-rules-delay' ), 10 );

				if ( Number.isNaN( delay ) || delay < 0 ) {
					delay = 1200;
				}

				if ( entry.isIntersecting ) {
					if ( timers.has( scene ) ) {
						window.clearTimeout( timers.get( scene ) );
					}

					timers.set(
						scene,
						window.setTimeout( function() {
							scene.classList.add( 'is-on' );
						}, delay )
					);
				} else {
					if ( timers.has( scene ) ) {
						window.clearTimeout( timers.get( scene ) );
						timers.delete( scene );
					}

					scene.classList.remove( 'is-on' );
				}
			} );
		}, {
			threshold: 0.45
		} );

		scenes.forEach( function( scene ) {
			observer.observe( scene );
		} );
	}

	function stabilizeSmartSliderCopy() {
		document.querySelectorAll( '.goodsleep-static-copy' ).forEach( function( slider ) {
			slider.querySelectorAll( '.n2-ss-layer' ).forEach( function( layer ) {
				layer.style.opacity = '1';
				layer.style.transform = 'none';
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		initRulesScenes();
		stabilizeSmartSliderCopy();
	} );
}() );
