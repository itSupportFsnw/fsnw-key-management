( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		// Erfolgs-/Fehlermeldungen nur kurz anzeigen und die auslösenden
		// URL-Parameter entfernen, damit ein Reload sie nicht erneut zeigt.
		var notices = document.querySelectorAll( '.fsnw-key-management .fsnw-notice' );

		if ( notices.length ) {
			try {
				var cleanUrl = new URL( window.location.href );
				[ 'fsnw_saved', 'fsnw_error' ].forEach( function ( param ) {
					cleanUrl.searchParams.delete( param );
				} );
				window.history.replaceState( null, '', cleanUrl.toString() );
			} catch ( e ) {}

			setTimeout( function () {
				var n;

				for ( n = 0; n < notices.length; n++ ) {
					notices[ n ].style.transition = 'opacity 300ms ease';
					notices[ n ].style.opacity = '0';
				}

				setTimeout( function () {
					for ( n = 0; n < notices.length; n++ ) {
						if ( notices[ n ].parentNode ) {
							notices[ n ].parentNode.removeChild( notices[ n ] );
						}
					}
				}, 350 );
			}, 4000 );
		}

		// Dynamische Einzelschlüssel-Felder in den Bund-Formularen
		// (Anlegen-Karte und Bearbeiten-Modal, daher klassenbasiert delegiert).
		document.addEventListener( 'click', function ( event ) {
			var addButton = event.target.closest( '.fsnw-add-key' );

			if ( addButton ) {
				var form = addButton.closest( 'form' );
				var container = form ? form.querySelector( '.fsnw-bundle-keys' ) : null;
				var row = container ? container.querySelector( '.fsnw-key-row' ) : null;

				if ( row ) {
					var clone = row.cloneNode( true );
					clone.querySelector( 'input' ).value = '';
					container.appendChild( clone );
					clone.querySelector( 'input' ).focus();
				}

				return;
			}

			var removeButton = event.target.closest( '.fsnw-remove-key' );

			if ( removeButton ) {
				var keysContainer = removeButton.closest( '.fsnw-bundle-keys' );

				if ( keysContainer.querySelectorAll( '.fsnw-key-row' ).length > 1 ) {
					removeButton.closest( '.fsnw-key-row' ).remove();
				} else {
					keysContainer.querySelector( '.fsnw-key-row input' ).value = '';
				}
			}
		} );

		// Bearbeiten-/Historie-Popup: Klick auf den Hintergrund oder Escape
		// schließt das Modal (Navigation zurück zur Seite ohne GET-Parameter).
		var overlay = document.querySelector( '.fsnw-km-modal-overlay' );

		if ( overlay ) {
			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay ) {
					window.location.href = overlay.getAttribute( 'data-close-url' );
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					window.location.href = overlay.getAttribute( 'data-close-url' );
				}
			} );
		}

		// Ausgabe-Formular: Warnung "letzter Bund" nur zeigen, wenn der gewählte
		// Bund der letzte verfügbare seiner Wohnung ist; die Checkbox muss dann
		// bewusst gesetzt werden, bevor das Formular abgeschickt werden kann.
		var issueForm = document.getElementById( 'fsnw-issue-form' );
		var bundleSelect = document.getElementById( 'fsnw-issue-bundle' );
		var warningBox = document.getElementById( 'fsnw-last-bundle-warning' );
		var confirmBox = document.getElementById( 'fsnw-last-bundle-confirm' );

		if ( issueForm && bundleSelect && warningBox && confirmBox ) {
			var updateWarning = function () {
				var option = bundleSelect.options[ bundleSelect.selectedIndex ];
				var isLast = option && '1' === option.getAttribute( 'data-last' );

				warningBox.classList.toggle( 'fsnw-hidden', ! isLast );
				warningBox.classList.remove( 'fsnw-shake' );

				if ( ! isLast ) {
					confirmBox.checked = false;
				}
			};

			bundleSelect.addEventListener( 'change', updateWarning );
			updateWarning();

			issueForm.addEventListener( 'submit', function ( event ) {
				if ( ! warningBox.classList.contains( 'fsnw-hidden' ) && ! confirmBox.checked ) {
					event.preventDefault();
					warningBox.classList.remove( 'fsnw-shake' );
					// Reflow erzwingen, damit die Animation erneut startet.
					void warningBox.offsetWidth;
					warningBox.classList.add( 'fsnw-shake' );
				}
			} );
		}
	} );
} )();
