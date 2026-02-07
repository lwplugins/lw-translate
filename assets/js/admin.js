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
		initFormHashPreserver();
		initInstallButtons();
		initDeleteButtons();
		initRefreshCache();
	}

	/**
	 * Initialize tab navigation.
	 */
	function initTabs() {
		var $tabs   = $( '.lw-translate-tabs a' );
		var $panels = $( '.lw-translate-tab-panel' );

		$tabs.on(
			'click',
			function (e) {
				e.preventDefault();

				var target = $( this ).attr( 'href' ).replace( '#', '' );

				$tabs.removeClass( 'active' );
				$( this ).addClass( 'active' );

				$panels.removeClass( 'active' );
				$( '#tab-' + target ).addClass( 'active' );

				if (history.pushState) {
					history.pushState( null, null, '#' + target );
				}
			}
		);

		var hash = window.location.hash.replace( '#', '' );
		if (hash) {
			var $targetTab = $tabs.filter( '[href="#' + hash + '"]' );
			if ($targetTab.length) {
				$targetTab.trigger( 'click' );
			}
		}
	}

	/**
	 * Preserve the active tab hash across form save.
	 */
	function initFormHashPreserver() {
		$( '.lw-translate-settings' ).closest( 'form' ).on(
			'submit',
			function () {
				var hash     = window.location.hash;
				var $referer = $( this ).find( 'input[name="_wp_http_referer"]' );

				if (hash && $referer.length) {
					$referer.val( $referer.val().replace( /#.*$/, '' ) + hash );
				}
			}
		);
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
