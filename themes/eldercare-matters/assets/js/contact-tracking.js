/**
 * Tracks "contact intent" clicks on the real single-listing page — the
 * "Request a match" (data-open-form-modal) button. This replaced WP Job
 * Manager's own Apply button in the real template, so WPJM Stats' own click listener
 * (.application_button.button) never fires here. Reuses WPJM Stats'
 * existing, already-working AJAX endpoint (wpjms_stat_apply_button_click)
 * so the numbers land in the same meta the dashboard's Stats page already
 * reads — no new backend needed.
 */
( function () {
	console.log( 'PD_DEBUG: contact-tracking.js parsed, readyState=', document.readyState );
	function init() {
		console.log( 'PD_DEBUG: init() called' );
		if ( typeof wpjmStats === 'undefined' || ! wpjmStats.post_id || typeof wp === 'undefined' || ! wp.ajax ) {
			console.log( 'PD_DEBUG: init() bailed on precondition', typeof wpjmStats, typeof wp );
			return;
		}
		var fired = false;
		var btns = document.querySelectorAll( '[data-open-form-modal]' );
		console.log( 'PD_DEBUG: binding to', btns.length, 'buttons' );
		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				console.log( 'PD_DEBUG: click fired' );
				if ( fired ) {
					return; // one contact-intent signal per pageview is enough
				}
				fired = true;
				wp.ajax.post( 'wpjms_stat_apply_button_click', { post_id: wpjmStats.post_id } )
					.done( function( r ) { console.log( 'PD_DEBUG: ajax done', r ); } )
					.fail( function( r ) { console.log( 'PD_DEBUG: ajax fail', r ); } );
			} );
		} );
	}
	// This script is footer-enqueued, so DOMContentLoaded has often already
	// fired by the time it runs — a plain addEventListener would silently
	// never call init(). Run immediately if the DOM is already ready.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
