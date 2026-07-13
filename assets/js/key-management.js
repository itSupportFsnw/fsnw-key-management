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

		// Dynamische Einzelschlüssel-Felder im Bund-Formular.
		var keysContainer = document.getElementById( 'fsnw-bundle-keys' );
		var addKeyButton = document.getElementById( 'fsnw-add-key' );

		if ( keysContainer && addKeyButton ) {
			addKeyButton.addEventListener( 'click', function () {
				var row = keysContainer.querySelector( '.fsnw-key-row' );

				if ( ! row ) {
					return;
				}

				var clone = row.cloneNode( true );
				clone.querySelector( 'input' ).value = '';
				keysContainer.appendChild( clone );
			} );

			keysContainer.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.fsnw-remove-key' );

				if ( ! button ) {
					return;
				}

				if ( keysContainer.querySelectorAll( '.fsnw-key-row' ).length > 1 ) {
					button.closest( '.fsnw-key-row' ).remove();
				} else {
					keysContainer.querySelector( '.fsnw-key-row input' ).value = '';
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
