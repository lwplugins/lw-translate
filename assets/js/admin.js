/**
 * LW Translate - Admin JavaScript
 *
 * @package LightweightPlugins\Translate
 */

(function ($) {
	'use strict';

	/**
	 * Initialize admin functionality.
	 */
	function init() {
		initTabs();
		initInstallButtons();
		initDeleteButtons();
		initRefreshCache();
	}

	/**
	 * Initialize settings page tabs.
	 */
	function initTabs() {
		var tabLinks  = document.querySelectorAll( '.lw-translate-tabs a' );
		var tabPanels = document.querySelectorAll( '.lw-translate-tab-panel' );

		if ( ! tabLinks.length || ! tabPanels.length) {
			return;
		}

		// Get active tab from URL hash or default to first tab.
		var hash     = window.location.hash.substring( 1 );
		var firstTab = tabLinks[0].getAttribute( 'href' ).substring( 1 );
		var validTab = false;

		tabLinks.forEach(
			function (link) {
				if (link.getAttribute( 'href' ).substring( 1 ) === hash) {
					validTab = true;
				}
			}
		);

		activateTab( validTab ? hash : firstTab );

		// Handle tab clicks.
		tabLinks.forEach(
			function (link) {
				link.addEventListener(
					'click',
					function (e) {
						e.preventDefault();
						var tabId = this.getAttribute( 'href' ).substring( 1 );
						activateTab( tabId );
						history.replaceState( null, '', '#' + tabId );
					}
				);
			}
		);

		// Preserve active tab on form submit.
		var form = document.querySelector( '.lw-translate-settings' );
		if (form) {
			form = form.closest( 'form' );
		}
		if (form) {
			form.addEventListener(
				'submit',
				function () {
					var activeLink = document.querySelector( '.lw-translate-tabs a.active' );
					if ( ! activeLink) {
						return;
					}
					var tabSlug  = activeLink.getAttribute( 'href' ).substring( 1 );
					var tabInput = form.querySelector( 'input[name="lw_translate_active_tab"]' );
					if (tabInput) {
						tabInput.value = tabSlug;
					}
				}
			);
		}

		function activateTab(tabId) {
			tabLinks.forEach(
				function (link) {
					var linkTabId = link.getAttribute( 'href' ).substring( 1 );
					if (linkTabId === tabId) {
						link.classList.add( 'active' );
					} else {
						link.classList.remove( 'active' );
					}
				}
			);

			// Update tab panels.
			tabPanels.forEach(
				function (panel) {
					if (panel.id === 'tab-' + tabId) {
						panel.classList.add( 'active' );
					} else {
						panel.classList.remove( 'active' );
					}
				}
			);
		}
	}

	/**
	 * Initialize install/update buttons.
	 */
	function initInstallButtons() {
		$( document ).on(
			'click',
			'.lw-translate-install',
			function () {
				var $btn = $( this );
				var slug = $btn.data( 'slug' );
				var type = $btn.data( 'type' );

				if ( ! slug || ! type) {
					return;
				}

				$btn.addClass( 'lw-translate-loading' )
					.text( lwTranslate.i18n.installing );

				$.post(
					lwTranslate.ajaxUrl,
					{
						action: 'lw_translate_install',
						nonce: lwTranslate.nonce,
						slug: slug,
						type: type
					},
					function (response) {
						if (response.success) {
							$btn.text( lwTranslate.i18n.success );
							setTimeout(
								function () {
									window.location.reload();
								},
								800
							);
						} else {
							$btn.removeClass( 'lw-translate-loading' )
								.text( response.data.message || lwTranslate.i18n.error );
						}
					}
				).fail(
					function () {
						$btn.removeClass( 'lw-translate-loading' )
							.text( lwTranslate.i18n.error );
					}
				);
			}
		);
	}

	/**
	 * Initialize delete buttons.
	 */
	function initDeleteButtons() {
		$( document ).on(
			'click',
			'.lw-translate-delete',
			function () {
				var $btn = $( this );

				if ( ! confirm( lwTranslate.i18n.confirmDelete )) {
					return;
				}

				$btn.addClass( 'lw-translate-loading' )
					.text( lwTranslate.i18n.deleting );

				$.post(
					lwTranslate.ajaxUrl,
					{
						action: 'lw_translate_delete',
						nonce: lwTranslate.nonce,
						slug: $btn.data( 'slug' ),
						type: $btn.data( 'type' )
					},
					function (response) {
						if (response.success) {
							$btn.text( lwTranslate.i18n.success );
							setTimeout(
								function () {
									window.location.reload();
								},
								800
							);
						} else {
							$btn.removeClass( 'lw-translate-loading' )
								.text( lwTranslate.i18n.error );
						}
					}
				).fail(
					function () {
						$btn.removeClass( 'lw-translate-loading' )
							.text( lwTranslate.i18n.error );
					}
				);
			}
		);
	}

	/**
	 * Initialize cache refresh button.
	 */
	function initRefreshCache() {
		$( document ).on(
			'click',
			'.lw-translate-refresh-cache',
			function () {
				var $btn = $( this );

				$btn.addClass( 'lw-translate-loading' );

				$.post(
					lwTranslate.ajaxUrl,
					{
						action: 'lw_translate_refresh_cache',
						nonce: lwTranslate.nonce
					},
					function (response) {
						if (response.success) {
							window.location.reload();
						} else {
							$btn.removeClass( 'lw-translate-loading' );
						}
					}
				).fail(
					function () {
						$btn.removeClass( 'lw-translate-loading' );
					}
				);
			}
		);
	}

	// Initialize on document ready.
	$( document ).ready( init );

})( jQuery );
