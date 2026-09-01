/**
 * Restaurant Location Order Redirect — frontend controller.
 *
 * Priority: saved location (cookie/localStorage) > IP geolocation (only if
 * confident) > manual selection via popup. Never throws uncaught errors —
 * a broken geolocation call must never break the rest of the site.
 *
 * @package Restaurant_Location_Redirect
 */

/* eslint-disable no-console */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		// CommonJS (Jest tests import the pure helpers this way).
		module.exports = factory( root );
	} else {
		factory( root );
	}
} )( typeof window !== 'undefined' ? window : this, function ( win ) {
	'use strict';

	var STORAGE_KEY_LS     = 'rlr_location';
	var STORAGE_KEY_COOKIE = 'rlr_selected_location';
	var SESSION_KEY        = 'rlr_session_id';

	// ---------------------------------------------------------------------
	// Pure helpers (no DOM/global access) — unit tested directly.
	// ---------------------------------------------------------------------

	/**
	 * Whether a stored localStorage record has expired.
	 *
	 * @param {number} savedAtMs   Epoch ms when saved.
	 * @param {number} durationDays Configured storage duration in days.
	 * @param {number} nowMs        Current epoch ms.
	 * @return {boolean}
	 */
	function isExpired( savedAtMs, durationDays, nowMs ) {
		if ( ! savedAtMs || ! durationDays ) {
			return true;
		}
		var maxAge = durationDays * 24 * 60 * 60 * 1000;
		return ( nowMs - savedAtMs ) > maxAge;
	}

	/**
	 * Find a location object in a locations array by slug.
	 *
	 * @param {Array} locations
	 * @param {string} slug
	 * @return {Object|null}
	 */
	function findLocationBySlug( locations, slug ) {
		if ( ! Array.isArray( locations ) || ! slug ) {
			return null;
		}
		for ( var i = 0; i < locations.length; i++ ) {
			if ( locations[ i ] && locations[ i ].slug === slug ) {
				return locations[ i ];
			}
		}
		return null;
	}

	/**
	 * Decide whether analytics consent has been granted, given plugin
	 * config and the raw document.cookie string. Pure function so it is
	 * unit-testable without a real document.
	 *
	 * A site integrator can override this entirely by defining
	 * window.rlrConsentCheck = function() { return true/false; }.
	 *
	 * @param {Object} analyticsConfig {requireConsent, consentCookieName, consentCookieValue}
	 * @param {string} cookieString    Raw document.cookie value.
	 * @param {Function|undefined} overrideFn Optional window.rlrConsentCheck.
	 * @return {boolean}
	 */
	function hasConsent( analyticsConfig, cookieString, overrideFn ) {
		if ( typeof overrideFn === 'function' ) {
			try {
				return !! overrideFn();
			} catch ( e ) {
				return false;
			}
		}

		if ( ! analyticsConfig || ! analyticsConfig.requireConsent ) {
			return true;
		}

		var name = analyticsConfig.consentCookieName;
		var expected = analyticsConfig.consentCookieValue;

		if ( ! name ) {
			// Consent is required but nothing is configured to detect it — default closed.
			return false;
		}

		var pattern = new RegExp( '(?:^|; )' + name.replace( /[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&' ) + '=([^;]*)' );
		var match = pattern.exec( cookieString || '' );
		if ( ! match ) {
			return false;
		}

		var value = decodeURIComponent( match[ 1 ] );
		if ( ! expected ) {
			return true; // Cookie merely needs to exist.
		}

		return value.indexOf( expected ) !== -1;
	}

	/**
	 * Apply a matched location's order URL to every element matching the
	 * configured selector within a given document/root element.
	 *
	 * @param {Document|Element} doc      Root to query within.
	 * @param {string} selector           CSS selector for Order Now buttons.
	 * @param {Object} location           {orderUrl, slug, name}.
	 * @return {number} Number of elements updated.
	 */
	function applyLocationToButtons( doc, selector, location ) {
		if ( ! doc || ! selector || ! location || ! location.orderUrl ) {
			return 0;
		}

		var elements;
		try {
			elements = doc.querySelectorAll( selector );
		} catch ( e ) {
			return 0; // Invalid selector — fail silently rather than throw.
		}

		var code = stateAbbreviation( location.state );

		var count = 0;
		for ( var i = 0; i < elements.length; i++ ) {
			var el = elements[ i ];
			el.setAttribute( 'href', location.orderUrl );
			el.setAttribute( 'data-rlr-applied', '1' );
			el.setAttribute( 'data-rlr-location-slug', location.slug || '' );

			var badge = el.querySelector( '.rlr-location-badge' );
			if ( code ) {
				var desired = ' (' + code + ')';
				if ( ! badge ) {
					// Set text before insertion: a detached node's textContent
					// isn't a DOM mutation, so appending it is a single mutation
					// record instead of two.
					badge = doc.createElement( 'span' );
					badge.className = 'rlr-location-badge';
					badge.textContent = desired;
					el.appendChild( badge );
				} else if ( badge.textContent !== desired ) {
					// Only write if the value actually changed: this function
					// is re-invoked by the MutationObserver in
					// watchForNewButtons() on every DOM change anywhere on the
					// page, and assigning textContent unconditionally -- even
					// to an unchanged value -- creates a new Text node, which
					// is itself an observed childList mutation. That fed the
					// observer its own output and looped forever, hanging the
					// page for every visitor.
					badge.textContent = desired;
				}
			} else if ( badge ) {
				badge.parentNode.removeChild( badge );
			}

			count++;
		}
		return count;
	}

	/**
	 * Categorize the current referrer for privacy-light analytics.
	 *
	 * @param {string} referrer Raw document.referrer.
	 * @param {string} siteHost Current site hostname.
	 * @return {string}
	 */
	function categorizeReferrer( referrer, siteHost ) {
		if ( ! referrer ) {
			return 'direct';
		}
		var host;
		try {
			host = new URL( referrer ).hostname;
		} catch ( e ) {
			return 'direct';
		}
		if ( host === siteHost ) {
			return 'internal';
		}
		if ( /google\.|bing\.|yahoo\.|duckduckgo\.|baidu\./.test( host ) ) {
			return 'search';
		}
		if ( /facebook\.|instagram\.|twitter\.|x\.com|tiktok\.|linkedin\.|pinterest\./.test( host ) ) {
			return 'social';
		}
		return 'referral';
	}

	/**
	 * Generate a random, non-identifying session id.
	 *
	 * @return {string}
	 */
	function generateSessionId() {
		if ( win && win.crypto && typeof win.crypto.randomUUID === 'function' ) {
			return win.crypto.randomUUID();
		}
		return 'rlr-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2 );
	}

	/**
	 * US state/territory full name (lowercased) to 2-letter code, for the
	 * order-button location badge. Only US names are recognized; a state
	 * value for any other country simply produces no badge.
	 *
	 * @type {Object<string,string>}
	 */
	var US_STATE_ABBREVIATIONS = {
		'alabama': 'AL', 'alaska': 'AK', 'arizona': 'AZ', 'arkansas': 'AR',
		'california': 'CA', 'colorado': 'CO', 'connecticut': 'CT', 'delaware': 'DE',
		'florida': 'FL', 'georgia': 'GA', 'hawaii': 'HI', 'idaho': 'ID',
		'illinois': 'IL', 'indiana': 'IN', 'iowa': 'IA', 'kansas': 'KS',
		'kentucky': 'KY', 'louisiana': 'LA', 'maine': 'ME', 'maryland': 'MD',
		'massachusetts': 'MA', 'michigan': 'MI', 'minnesota': 'MN', 'mississippi': 'MS',
		'missouri': 'MO', 'montana': 'MT', 'nebraska': 'NE', 'nevada': 'NV',
		'new hampshire': 'NH', 'new jersey': 'NJ', 'new mexico': 'NM', 'new york': 'NY',
		'north carolina': 'NC', 'north dakota': 'ND', 'ohio': 'OH', 'oklahoma': 'OK',
		'oregon': 'OR', 'pennsylvania': 'PA', 'rhode island': 'RI', 'south carolina': 'SC',
		'south dakota': 'SD', 'tennessee': 'TN', 'texas': 'TX', 'utah': 'UT',
		'vermont': 'VT', 'virginia': 'VA', 'washington': 'WA', 'west virginia': 'WV',
		'wisconsin': 'WI', 'wyoming': 'WY',
		'district of columbia': 'DC', 'washington dc': 'DC', 'washington d.c.': 'DC',
		'puerto rico': 'PR', 'guam': 'GU', 'american samoa': 'AS',
		'u.s. virgin islands': 'VI', 'virgin islands': 'VI',
		'northern mariana islands': 'MP'
	};

	/**
	 * Resolve a location's free-text state/region into a short badge code
	 * (e.g. "Arizona" -> "AZ"). Already-2-letter input passes through
	 * uppercased. Unrecognized/empty input yields '' (no badge shown).
	 *
	 * @param {string} stateText
	 * @return {string}
	 */
	function stateAbbreviation( stateText ) {
		var raw = ( stateText || '' ).trim();
		if ( ! raw ) {
			return '';
		}
		if ( /^[A-Za-z]{2}$/.test( raw ) ) {
			return raw.toUpperCase();
		}
		return US_STATE_ABBREVIATIONS[ raw.toLowerCase() ] || '';
	}

	var pureHelpers = {
		isExpired: isExpired,
		findLocationBySlug: findLocationBySlug,
		hasConsent: hasConsent,
		applyLocationToButtons: applyLocationToButtons,
		categorizeReferrer: categorizeReferrer,
		generateSessionId: generateSessionId,
		stateAbbreviation: stateAbbreviation,
	};

	// In a non-browser (test) context, just expose the pure helpers and stop.
	if ( typeof win === 'undefined' || typeof win.document === 'undefined' ) {
		return pureHelpers;
	}

	// ---------------------------------------------------------------------
	// Browser runtime.
	// ---------------------------------------------------------------------

	var doc = win.document;

	function ready( fn ) {
		if ( doc.readyState === 'loading' ) {
			doc.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	function setCookie( name, value, days ) {
		try {
			var expires = '';
			if ( days ) {
				var d = new Date();
				d.setTime( d.getTime() + days * 24 * 60 * 60 * 1000 );
				expires = '; expires=' + d.toUTCString();
			}
			var secure = win.location.protocol === 'https:' ? '; Secure' : '';
			doc.cookie = name + '=' + encodeURIComponent( value ) + expires + '; path=/; SameSite=Lax' + secure;
		} catch ( e ) {
			// Cookies unavailable — ignore, localStorage may still work.
		}
	}

	function getCookie( name ) {
		try {
			var pattern = new RegExp( '(?:^|; )' + name.replace( /[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&' ) + '=([^;]*)' );
			var match = pattern.exec( doc.cookie );
			return match ? decodeURIComponent( match[ 1 ] ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function deleteCookie( name ) {
		setCookie( name, '', -1 );
	}

	function lsGet( key ) {
		try {
			var raw = win.localStorage.getItem( key );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function lsSet( key, value ) {
		try {
			win.localStorage.setItem( key, JSON.stringify( value ) );
		} catch ( e ) {
			// Storage unavailable (private mode, quota) — degrade to cookie-only.
		}
	}

	function lsRemove( key ) {
		try {
			win.localStorage.removeItem( key );
		} catch ( e ) {
			// Ignore.
		}
	}

	/**
	 * Controller — encapsulates all mutable state for this page load.
	 */
	function RlrController( config ) {
		this.config = config || {};
		this.locations = Array.isArray( this.config.locations ) ? this.config.locations : [];
		this.currentLocation = null; // {id, slug, name, orderUrl, source, matchMethod, confidence}
		this.modalOverlay = doc.getElementById( 'rlr-modal-overlay' );
		this.lastFocusedEl = null;
		this.observer = null;
	}

	RlrController.prototype.log = function ( msg ) {
		if ( this.config.settings && this.config.settings.debugMode && win.console ) {
			console.log( '[RLR]', msg );
		}
	};

	RlrController.prototype.getSessionId = function () {
		var existing = null;
		try {
			existing = win.sessionStorage.getItem( SESSION_KEY );
		} catch ( e ) {
			existing = null;
		}
		if ( existing ) {
			return existing;
		}
		var id = generateSessionId();
		try {
			win.sessionStorage.setItem( SESSION_KEY, id );
		} catch ( e ) {
			// Ignore — tracking will just use a fresh id next call.
		}
		return id;
	};

	/**
	 * Read a previously saved location, respecting the configured storage
	 * method and expiry window. Returns null if nothing valid is stored.
	 */
	RlrController.prototype.getSavedLocation = function () {
		var method = ( this.config.settings && this.config.settings.storageMethod ) || 'both';
		var duration = ( this.config.settings && this.config.settings.storageDurationDays ) || 30;

		if ( method === 'localstorage' || method === 'both' ) {
			var record = lsGet( STORAGE_KEY_LS );
			if ( record && record.slug && ! isExpired( record.ts, duration, Date.now() ) ) {
				var loc = findLocationBySlug( this.locations, record.slug );
				if ( loc ) {
					return { id: loc.id, slug: loc.slug, name: loc.name, orderUrl: loc.orderUrl, state: loc.state, source: record.source || 'manual' };
				}
			} else if ( record ) {
				lsRemove( STORAGE_KEY_LS ); // Expired or now-invalid (location removed/deactivated).
			}
		}

		if ( method === 'cookie' || method === 'both' ) {
			var slug = getCookie( STORAGE_KEY_COOKIE );
			if ( slug ) {
				var locByCookie = findLocationBySlug( this.locations, slug );
				if ( locByCookie ) {
					return { id: locByCookie.id, slug: locByCookie.slug, name: locByCookie.name, orderUrl: locByCookie.orderUrl, state: locByCookie.state, source: 'manual' };
				}
				deleteCookie( STORAGE_KEY_COOKIE );
			}
		}

		return null;
	};

	/**
	 * Persist the given location as the visitor's selection.
	 */
	RlrController.prototype.saveLocation = function ( location, source ) {
		var method = ( this.config.settings && this.config.settings.storageMethod ) || 'both';
		var duration = ( this.config.settings && this.config.settings.storageDurationDays ) || 30;

		if ( method === 'localstorage' || method === 'both' ) {
			lsSet( STORAGE_KEY_LS, { slug: location.slug, source: source, ts: Date.now() } );
		}
		if ( method === 'cookie' || method === 'both' ) {
			setCookie( STORAGE_KEY_COOKIE, location.slug, duration );
		}
	};

	RlrController.prototype.clearSavedLocation = function () {
		lsRemove( STORAGE_KEY_LS );
		deleteCookie( STORAGE_KEY_COOKIE );
	};

	/**
	 * Apply a location to the page: update buttons, the switcher label,
	 * and remember it as "current" for later click tracking.
	 */
	RlrController.prototype.applyLocation = function ( location, matchMethod, confidence ) {
		this.currentLocation = {
			id: location.id,
			slug: location.slug,
			name: location.name,
			orderUrl: location.orderUrl,
			state: location.state,
			matchMethod: matchMethod || 'manual',
			confidence: confidence || 'high',
		};

		var selector = ( this.config.settings && this.config.settings.buttonSelector ) || '.order-now';
		var updated = applyLocationToButtons( doc, selector, location );
		this.log( 'Applied location "' + location.name + '" to ' + updated + ' button(s).' );

		var labels = doc.querySelectorAll( '[data-rlr-current-location]' );
		for ( var i = 0; i < labels.length; i++ ) {
			labels[ i ].textContent = location.name;
		}

		try {
			var evt = new CustomEvent( 'rlr:locationApplied', { detail: this.currentLocation } );
			doc.dispatchEvent( evt );
		} catch ( e ) {
			// Older browsers without CustomEvent support — non-fatal.
		}
	};

	/**
	 * Re-apply the current location to any newly-added buttons (Elementor
	 * lazy content, AJAX-loaded sections, etc.) without re-tracking events.
	 */
	RlrController.prototype.watchForNewButtons = function () {
		if ( ! win.MutationObserver || ! this.currentLocation ) {
			return;
		}
		var self = this;
		this.observer = new MutationObserver( function () {
			if ( ! self.currentLocation ) {
				return;
			}
			var selector = ( self.config.settings && self.config.settings.buttonSelector ) || '.order-now';
			applyLocationToButtons( doc, selector, self.currentLocation );
		} );
		this.observer.observe( doc.body, { childList: true, subtree: true } );
	};

	// -- Modal ---------------------------------------------------------

	RlrController.prototype.openModal = function ( triggerEl ) {
		if ( ! this.modalOverlay ) {
			return;
		}
		this.lastFocusedEl = triggerEl || doc.activeElement;
		this.modalOverlay.hidden = false;
		this.modalOverlay.setAttribute( 'aria-hidden', 'false' );
		doc.body.classList.add( 'rlr-modal-open' );

		var firstOption = this.modalOverlay.querySelector( '.rlr-location-option, .rlr-modal-close' );
		if ( firstOption ) {
			firstOption.focus();
		}

		this.trackEvent( 'selector_opened' );
	};

	RlrController.prototype.closeModal = function () {
		if ( ! this.modalOverlay ) {
			return;
		}
		this.modalOverlay.hidden = true;
		this.modalOverlay.setAttribute( 'aria-hidden', 'true' );
		doc.body.classList.remove( 'rlr-modal-open' );

		if ( this.lastFocusedEl && typeof this.lastFocusedEl.focus === 'function' ) {
			this.lastFocusedEl.focus();
		}
	};

	RlrController.prototype.showModalNotice = function ( message ) {
		if ( ! this.modalOverlay ) {
			return;
		}
		var notice = this.modalOverlay.querySelector( '[data-rlr-modal-notice]' );
		if ( notice ) {
			notice.textContent = message;
			notice.hidden = ! message;
		}
	};

	/**
	 * Handle the visitor picking a location from the modal.
	 */
	RlrController.prototype.selectLocation = function ( location ) {
		var hadPrevious = !! this.currentLocation;
		var isChange = hadPrevious && this.currentLocation.slug !== location.slug;
		var isFirstPick = ! hadPrevious;

		this.applyLocation( location, 'manual', 'high' );
		this.saveLocation( location, 'manual' );
		this.closeModal();

		if ( isChange ) {
			this.trackEvent( 'location_changed' );
		} else if ( isFirstPick ) {
			this.trackEvent( 'manual_selection' );
		}
	};

	// -- Analytics -------------------------------------------------------

	RlrController.prototype.trackEvent = function ( eventType, extra ) {
		try {
			var analytics = this.config.analytics || {};
			if ( ! analytics.mode || analytics.mode === 'disabled' ) {
				return;
			}

			if ( ! hasConsent( analytics, doc.cookie, win.rlrConsentCheck ) ) {
				return;
			}

			var payload = Object.assign(
				{
					event: eventType,
					location_id: this.currentLocation ? this.currentLocation.id : null,
					match_method: this.currentLocation ? this.currentLocation.matchMethod : null,
					confidence: this.currentLocation ? this.currentLocation.confidence : null,
					session_id: this.getSessionId(),
					referrer_category: categorizeReferrer( doc.referrer, this.config.siteHost ),
				},
				extra || {}
			);

			if ( this.config.restUrl && win.fetch ) {
				win.fetch( this.config.restUrl + '/track', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
					body: JSON.stringify( payload ),
					keepalive: true,
					credentials: 'same-origin',
				} ).catch( function () {} );
			}

			if ( analytics.ga4Enabled && typeof win.gtag === 'function' ) {
				win.gtag( 'event', 'rlr_' + eventType, {
					location_id: payload.location_id,
					match_method: payload.match_method,
				} );
			}
		} catch ( e ) {
			// Analytics must never break the core redirect flow.
		}
	};

	// -- Geolocation flow --------------------------------------------------

	RlrController.prototype.runDetection = function () {
		var self = this;

		if ( ! this.config.settings.enableGeolocation || ! this.config.restUrl || ! win.fetch ) {
			this.fallbackToManual( 'geolocation_unavailable' );
			return;
		}

		win.fetch( this.config.restUrl + '/detect', {
			credentials: 'same-origin',
			// The X-WP-Nonce header is what lets WordPress's REST API
			// recognize the currently logged-in user on a cookie-authenticated
			// request; without it the request is treated as anonymous
			// regardless of the session cookie, and admin-only debug data
			// would never be returned even with Debug Mode enabled.
			headers: { 'X-WP-Nonce': this.config.nonce },
		} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				return res.json();
			} )
			.then( function ( data ) {
				self.log( 'Detect response: ' + JSON.stringify( data ) );

				if ( data && data.matched && data.location ) {
					self.applyLocation( data.location, data.match_method, data.confidence );
					self.saveLocation( data.location, 'auto' );
					self.trackEvent( 'auto_match', { match_method: data.match_method, confidence: data.confidence } );
					self.renderDebugPanel( data );
					return;
				}

				if ( data && data.success ) {
					self.trackEvent( 'no_confident_match', { match_method: data.match_method, confidence: data.confidence } );
				} else {
					self.trackEvent( 'geolocation_failure' );
				}

				self.renderDebugPanel( data );
				self.fallbackToManual( data && data.reason ? data.reason : 'no_match' );
			} )
			.catch( function () {
				self.trackEvent( 'geolocation_failure' );
				self.fallbackToManual( 'network_error' );
			} );
	};

	/**
	 * No confident automatic match was possible — show the popup so the
	 * visitor can choose, or otherwise leave the site fully functional.
	 */
	RlrController.prototype.fallbackToManual = function ( reason ) {
		this.log( 'Falling back to manual selection: ' + reason );
		if ( this.config.settings.enablePopup ) {
			this.openModal();
		}
	};

	RlrController.prototype.renderDebugPanel = function ( data ) {
		if ( ! this.config.settings.debugMode || ! data || ! data.debug ) {
			return;
		}

		var panel = doc.getElementById( 'rlr-debug-panel' );
		if ( ! panel ) {
			panel = doc.createElement( 'div' );
			panel.id = 'rlr-debug-panel';
			panel.className = 'rlr-debug-panel';
			doc.body.appendChild( panel );
		}

		var d = data.debug;
		var lines = [
			'<strong>RLR Debug</strong>',
			'IP: ' + ( d.ip || 'n/a' ),
			'Country: ' + ( d.detected_country || 'n/a' ),
			'State: ' + ( d.detected_state || 'n/a' ),
			'City: ' + ( d.detected_city || 'n/a' ),
			'Match method: ' + ( data.match_method || 'none' ),
			'Confidence: ' + ( data.confidence || 'none' ),
			'Threshold: ' + ( d.threshold || 'n/a' ),
			'Meets threshold: ' + ( d.meets_threshold ? 'yes' : 'no' ),
			'Matched: ' + ( data.matched ? ( data.location ? data.location.name : 'yes' ) : 'no' ),
			'Reason: ' + ( data.reason || 'n/a' ),
		];
		if ( d.error ) {
			lines.push( 'Error: ' + d.error );
		}

		panel.innerHTML = lines.map( function ( l ) { return '<div>' + l + '</div>'; } ).join( '' );
	};

	// -- Wiring --------------------------------------------------------

	RlrController.prototype.bindEvents = function () {
		var self = this;

		doc.addEventListener( 'click', function ( evt ) {
			var changeTrigger = evt.target.closest && evt.target.closest( '[data-rlr-change-location], ' + ( self.config.settings.changeLocationSelector || '.rlr-change-location' ) );
			if ( changeTrigger ) {
				evt.preventDefault();
				self.openModal( changeTrigger );
				return;
			}

			var closeTrigger = evt.target.closest && evt.target.closest( '[data-rlr-close-modal]' );
			if ( closeTrigger ) {
				evt.preventDefault();
				self.closeModal();
				return;
			}

			var overlay = evt.target.closest && evt.target.closest( '#rlr-modal-overlay' );
			if ( overlay && evt.target === self.modalOverlay ) {
				self.closeModal();
				return;
			}

			var option = evt.target.closest && evt.target.closest( '.rlr-location-option' );
			if ( option ) {
				evt.preventDefault();
				var optionSlug = option.getAttribute( 'data-rlr-location-slug' );
				var picked = findLocationBySlug( self.locations, optionSlug ) || {
					id: parseInt( option.getAttribute( 'data-rlr-location-id' ), 10 ),
					slug: optionSlug,
					name: option.getAttribute( 'data-rlr-location-name' ),
					orderUrl: option.getAttribute( 'data-rlr-location-url' ),
				};
				self.selectLocation( picked );
				return;
			}

			var orderButton = evt.target.closest && self.config.settings.buttonSelector ? evt.target.closest( self.config.settings.buttonSelector ) : null;
			if ( orderButton && orderButton.getAttribute( 'data-rlr-applied' ) ) {
				self.trackEvent( 'order_click' );
			}
		} );

		doc.addEventListener( 'keydown', function ( evt ) {
			if ( evt.key === 'Escape' && self.modalOverlay && ! self.modalOverlay.hidden ) {
				self.closeModal();
			}
		} );
	};

	RlrController.prototype.init = function () {
		this.bindEvents();

		var saved = this.getSavedLocation();
		if ( saved ) {
			this.applyLocation( saved, saved.source === 'auto' ? 'saved' : 'manual', 'high' );
			this.watchForNewButtons();
			return;
		}

		this.watchForNewButtons();
		this.runDetection();
	};

	// -- Boot ------------------------------------------------------------

	ready( function () {
		try {
			if ( ! win.rlrConfig ) {
				return; // Plugin disabled or assets failed to localize — do nothing.
			}
			win.rlrController = new RlrController( win.rlrConfig );
			win.rlrController.init();
		} catch ( e ) {
			if ( win.console && win.console.warn ) {
				console.warn( 'Restaurant Location Redirect failed to initialize:', e );
			}
		}
	} );

	pureHelpers.RlrController = RlrController;
	return pureHelpers;
} );
