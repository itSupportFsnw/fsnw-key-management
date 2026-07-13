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

		// Inventar (Verwaltungs-Seite): Wohnungs-Karten sind standardmäßig
		// eingeklappt; der Toggle-Button zeigt/verbirgt die Bund-Tabelle.
		document.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '.fsnw-km-toggle' );

			if ( ! toggle ) {
				return;
			}

			var card = toggle.closest( '.fsnw-km-apartment' );
			var body = card ? card.querySelector( '.fsnw-km-apartment__body' ) : null;

			if ( ! body ) {
				return;
			}

			var isHidden = body.classList.toggle( 'fsnw-hidden' );
			toggle.setAttribute( 'aria-expanded', isHidden ? 'false' : 'true' );
			toggle.textContent = isHidden ? toggle.getAttribute( 'data-label-show' ) || 'Bunde anzeigen' : toggle.getAttribute( 'data-label-hide' ) || 'Bunde verbergen';
		} );

		// Inventar-Suche: filtert die Wohnungs-Karten live.
		var inventorySearch = document.getElementById( 'fsnw-inventory-search' );

		if ( inventorySearch ) {
			inventorySearch.addEventListener( 'input', function () {
				var query = inventorySearch.value.toLowerCase();

				document.querySelectorAll( '.fsnw-km-apartment' ).forEach( function ( card ) {
					card.classList.toggle( 'fsnw-hidden', -1 === card.textContent.toLowerCase().indexOf( query ) );
				} );
			} );
		}

		// Schlüsselliste (Mitarbeiter-Ansicht): Live-Filter über die Tabellenzeilen.
		var listSearch = document.getElementById( 'fsnw-key-list-search' );
		var listTable = document.getElementById( 'fsnw-key-list-table' );
		var listEmpty = document.getElementById( 'fsnw-key-list-empty' );

		if ( listSearch && listTable ) {
			listSearch.addEventListener( 'input', function () {
				var query = listSearch.value.toLowerCase();
				var visible = 0;

				listTable.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
					var match = -1 !== row.textContent.toLowerCase().indexOf( query );
					row.classList.toggle( 'fsnw-hidden', ! match );

					if ( match ) {
						visible += 1;
					}
				} );

				if ( listEmpty ) {
					listEmpty.classList.toggle( 'fsnw-hidden', visible > 0 );
				}
			} );
		}

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

		// Suchfeld-Auswahl (Combobox): Text-Eingabe filtert die Liste, Klick auf
		// einen Eintrag setzt das versteckte Wertefeld. Genutzt für die
		// Bund-Auswahl bei der Ausgabe und die Wohnungs-Auswahl beim Bund-Anlegen.
		document.querySelectorAll( '.fsnw-combobox' ).forEach( function ( combobox ) {
			var input = combobox.querySelector( '.fsnw-combobox__input' );
			var hidden = combobox.querySelector( 'input[type="hidden"]' );
			var list = combobox.querySelector( '.fsnw-combobox__list' );
			var items = list.querySelectorAll( 'li' );

			var clearSelection = function () {
				hidden.value = '';
				delete hidden.dataset.last;
				hidden.dispatchEvent( new Event( 'change' ) );
			};

			var filterList = function () {
				var query = input.value.toLowerCase();
				var visible = 0;

				items.forEach( function ( item ) {
					var match = -1 !== item.textContent.toLowerCase().indexOf( query );
					item.classList.toggle( 'fsnw-hidden', ! match );

					if ( match ) {
						visible += 1;
					}
				} );

				list.classList.toggle( 'fsnw-hidden', 0 === visible );
			};

			input.addEventListener( 'input', function () {
				clearSelection();
				input.classList.remove( 'fsnw-combobox__input--invalid' );
				filterList();
			} );

			input.addEventListener( 'focus', filterList );

			list.addEventListener( 'click', function ( event ) {
				var item = event.target.closest( 'li' );

				if ( ! item ) {
					return;
				}

				input.value = item.getAttribute( 'data-label' ) || item.textContent.trim();
				hidden.value = item.getAttribute( 'data-value' );

				if ( item.hasAttribute( 'data-last' ) ) {
					hidden.dataset.last = item.getAttribute( 'data-last' );
				}

				input.classList.remove( 'fsnw-combobox__input--invalid' );
				list.classList.add( 'fsnw-hidden' );
				hidden.dispatchEvent( new Event( 'change' ) );
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! combobox.contains( event.target ) ) {
					list.classList.add( 'fsnw-hidden' );
				}
			} );

			// Ohne gültige Auswahl darf das Formular nicht abgeschickt werden.
			var form = combobox.closest( 'form' );

			if ( form ) {
				form.addEventListener( 'submit', function ( event ) {
					if ( '' === hidden.value ) {
						event.preventDefault();
						input.classList.add( 'fsnw-combobox__input--invalid' );
						input.focus();
					}
				} );
			}
		} );

		// Ausgabe-Formular: Warnung "letzter Bund" nur zeigen, wenn der gewählte
		// Bund der letzte verfügbare seiner Wohnung ist; die Checkbox muss dann
		// bewusst gesetzt werden, bevor das Formular abgeschickt werden kann.
		var issueForm = document.getElementById( 'fsnw-issue-form' );
		var bundleHidden = document.getElementById( 'fsnw-issue-bundle-id' );
		var warningBox = document.getElementById( 'fsnw-last-bundle-warning' );
		var confirmBox = document.getElementById( 'fsnw-last-bundle-confirm' );

		if ( issueForm && bundleHidden && warningBox && confirmBox ) {
			var updateWarning = function () {
				var isLast = '1' === bundleHidden.dataset.last;

				warningBox.classList.toggle( 'fsnw-hidden', ! isLast );
				warningBox.classList.remove( 'fsnw-shake' );

				if ( ! isLast ) {
					confirmBox.checked = false;
				}
			};

			bundleHidden.addEventListener( 'change', updateWarning );
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
