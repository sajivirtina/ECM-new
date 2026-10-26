(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.querySelector( '.ecm-verify-wrap' );
		if ( ! wrap ) return;

		// ── Relocate WordPress/plugin site notices ────────────────────────
		// WP prints these as siblings directly above this page's own markup
		// (staging-URL warnings, plugin update nags, etc.) — not part of this
		// page, but visually indistinguishable from it since both use a
		// white card look. Move them below the dashboard instead of hiding
		// them, so they're still fully visible, just out of the way.
		var bodyContent = document.getElementById( 'wpbody-content' );
		if ( bodyContent ) {
			var stray = bodyContent.querySelectorAll( ':scope > .notice, :scope > div.updated, :scope > div.error' );
			if ( stray.length ) {
				var holder = document.createElement( 'div' );
				holder.className = 'ecm-verify-other-notices';
				var label = document.createElement( 'p' );
				label.className = 'ecm-verify-other-notices-label';
				label.textContent = 'Other WordPress notices (not related to this page):';
				holder.appendChild( label );
				stray.forEach( function ( n ) { holder.appendChild( n ); } );
				wrap.parentNode.insertBefore( holder, wrap.nextSibling );
			}
		}

		// Filtering, search, tier scope and pagination on the list screen are
		// all real server-side navigation now (plain links/GET form) — no JS
		// needed for them. That's deliberate: the old client-side version
		// rendered every eligible listing into the DOM on every page load and
		// paged through them with JS, which stops scaling well past a few
		// hundred listings. See ecm_verification_render_list() in
		// inc/ecm-verification.php.

		// ── Detail-page tabs (Log check / History) ────────────────────────
		var tabBtns   = wrap.querySelectorAll( '.ecm-verify-tab' );
		var tabPanels = wrap.querySelectorAll( '.ecm-verify-tab-panel' );
		tabBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var target = btn.getAttribute( 'data-tab' );
				tabBtns.forEach( function ( b ) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );
				tabPanels.forEach( function ( panel ) {
					panel.style.display = panel.getAttribute( 'data-tab-panel' ) === target ? '' : 'none';
				} );
			} );
		} );

		// ── Live result preview (non-interactive — reflects the 3 checks) ──
		var resultBlock = wrap.querySelector( '.ecm-verify-result-preview' );
		if ( resultBlock ) {
			var entityBox  = wrap.querySelector( '#entity_ok' );
			var licenseBox = wrap.querySelector( '#license_ok' );
			var contactBox = wrap.querySelector( '#contact_ok' );
			var toggleEl   = resultBlock.querySelector( '.ecm-verify-result-toggle' );
			var textEl     = resultBlock.querySelector( '.ecm-verify-result-text' );
			var licenseRequired = resultBlock.getAttribute( 'data-license-required' ) === '1';

			var updatePreview = function () {
				var pass = !! ( entityBox && entityBox.checked )
					&& ( ! licenseRequired || ( licenseBox && licenseBox.checked ) )
					&& !! ( contactBox && contactBox.checked );
				toggleEl.classList.toggle( 'is-pass', pass );
				textEl.classList.toggle( 'is-pass', pass );
				textEl.innerHTML = pass
					? 'All applicable checks are ticked — submitting now <strong>would grant</strong> the badge.'
					: 'Not all checks are ticked yet — submitting now would <strong>not</strong> grant the badge.';
			};

			[ entityBox, licenseBox, contactBox ].forEach( function ( box ) {
				if ( box ) box.addEventListener( 'change', updatePreview );
			} );
			updatePreview();
		}

		// ── Confirm before submitting a check cycle ───────────────────────
		// Recording a cycle is consequential (can grant or immediately revoke
		// a live badge) and, unlike everything else on this screen, isn't
		// undoable by re-editing — so confirm before it submits, and call
		// out explicitly when it's being attributed to someone other than
		// whoever is actually clicking submit.
		var logForm = document.getElementById( 'ecm-verify-log-form' );
		if ( logForm ) {
			logForm.addEventListener( 'submit', function ( e ) {
				var performerSelect = logForm.querySelector( '#verify_performed_by' );
				var currentUserId   = logForm.getAttribute( 'data-current-user-id' );
				var lines = [ 'Record this check cycle?' ];

				if ( performerSelect && currentUserId && performerSelect.value !== currentUserId ) {
					var name = performerSelect.options[ performerSelect.selectedIndex ].text;
					lines.push( 'This will be recorded as performed by ' + name + ', not you.' );
				}

				var untilInput = logForm.querySelector( '#verified_until' );
				if ( untilInput && untilInput.value ) {
					lines.push( 'If it passes, the badge will be valid until ' + untilInput.value + '.' );
				}

				if ( ! window.confirm( lines.join( '\n' ) ) ) {
					e.preventDefault();
				}
			} );
		}

		// ── Confirm before removing the current verification ───────────────
		wrap.querySelectorAll( '.ecm-verify-remove-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( ! window.confirm( form.getAttribute( 'data-confirm' ) ) ) {
					e.preventDefault();
				}
			} );
		} );

		// ── Attachment picker (detail/log page only) ──────────────────────
		var addFilesBtn = wrap.querySelector( '#ecm-verify-add-files' );
		var idsInput     = wrap.querySelector( '#ecm-verify-attachment-ids' );
		var fileList     = wrap.querySelector( '#ecm-verify-attachment-list' );
		if ( addFilesBtn && idsInput && fileList && window.wp && window.wp.media ) {
			var selected = [];
			var frame;

			function renderList() {
				fileList.innerHTML = '';
				selected.forEach( function ( file ) {
					var li = document.createElement( 'li' );
					li.textContent = file.title || file.filename || ( '#' + file.id );
					fileList.appendChild( li );
				} );
				idsInput.value = selected.map( function ( f ) { return f.id; } ).join( ',' );
			}

			addFilesBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ! frame ) {
					frame = window.wp.media( {
						title: 'Add supporting files',
						button: { text: 'Add to check cycle' },
						multiple: true,
					} );
					frame.on( 'select', function () {
						selected = frame.state().get( 'selection' ).map( function ( att ) {
							var a = att.toJSON();
							return { id: a.id, title: a.title, filename: a.filename };
						} );
						renderList();
					} );
				}
				frame.open();
			} );
		}
	} );
} )();
