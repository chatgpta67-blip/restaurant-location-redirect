/**
 * Jest tests for public/js/rlr-public.js — the frontend controller.
 *
 * Covers: storage/expiry logic, button URL application (multiple buttons),
 * manual selection vs. change tracking, consent gating, and order-click
 * tracking. Runs under jest-environment-jsdom.
 */

const path = require( 'path' );

function loadModule() {
	jest.resetModules();
	// eslint-disable-next-line global-require, import/no-dynamic-require
	return require( path.join( '..', '..', 'public', 'js', 'rlr-public.js' ) );
}

describe( 'pure helpers', () => {
	let mod;
	beforeEach( () => {
		mod = loadModule();
	} );

	test( 'isExpired detects expired and non-expired timestamps', () => {
		const now = 1_700_000_000_000;
		expect( mod.isExpired( now - 31 * 24 * 60 * 60 * 1000, 30, now ) ).toBe( true );
		expect( mod.isExpired( now - 1 * 24 * 60 * 60 * 1000, 30, now ) ).toBe( false );
		expect( mod.isExpired( 0, 30, now ) ).toBe( true );
	} );

	test( 'findLocationBySlug finds an exact slug match', () => {
		const locations = [ { slug: 'arizona' }, { slug: 'manchester' } ];
		expect( mod.findLocationBySlug( locations, 'manchester' ) ).toEqual( { slug: 'manchester' } );
		expect( mod.findLocationBySlug( locations, 'flamingo' ) ).toBeNull();
		expect( mod.findLocationBySlug( null, 'arizona' ) ).toBeNull();
	} );

	test( 'hasConsent: no consent required => true', () => {
		expect( mod.hasConsent( { requireConsent: false }, '' ) ).toBe( true );
	} );

	test( 'hasConsent: required but no cookie configured => closed by default', () => {
		expect( mod.hasConsent( { requireConsent: true, consentCookieName: '' }, 'anything=1' ) ).toBe( false );
	} );

	test( 'hasConsent: cookie present with matching value => true', () => {
		const cfg = { requireConsent: true, consentCookieName: 'cookie_consent', consentCookieValue: 'granted' };
		expect( mod.hasConsent( cfg, 'other=1; cookie_consent=granted-all' ) ).toBe( true );
	} );

	test( 'hasConsent: cookie present without matching value => false', () => {
		const cfg = { requireConsent: true, consentCookieName: 'cookie_consent', consentCookieValue: 'granted' };
		expect( mod.hasConsent( cfg, 'cookie_consent=denied' ) ).toBe( false );
	} );

	test( 'hasConsent: cookie missing entirely => false', () => {
		const cfg = { requireConsent: true, consentCookieName: 'cookie_consent', consentCookieValue: 'granted' };
		expect( mod.hasConsent( cfg, '' ) ).toBe( false );
	} );

	test( 'hasConsent: integrator override function takes priority', () => {
		const cfg = { requireConsent: true };
		expect( mod.hasConsent( cfg, '', () => true ) ).toBe( true );
		expect( mod.hasConsent( cfg, '', () => false ) ).toBe( false );
	} );

	test( 'categorizeReferrer buckets correctly', () => {
		expect( mod.categorizeReferrer( '', 'example.com' ) ).toBe( 'direct' );
		expect( mod.categorizeReferrer( 'https://example.com/page', 'example.com' ) ).toBe( 'internal' );
		expect( mod.categorizeReferrer( 'https://www.google.com/search', 'example.com' ) ).toBe( 'search' );
		expect( mod.categorizeReferrer( 'https://instagram.com/p/123', 'example.com' ) ).toBe( 'social' );
		expect( mod.categorizeReferrer( 'https://someblog.example.org/post', 'example.com' ) ).toBe( 'referral' );
	} );

	test( 'generateSessionId returns distinct, non-empty strings', () => {
		const a = mod.generateSessionId();
		const b = mod.generateSessionId();
		expect( typeof a ).toBe( 'string' );
		expect( a.length ).toBeGreaterThan( 0 );
		expect( a ).not.toBe( b );
	} );
} );

describe( 'applyLocationToButtons (Test 8: multiple buttons)', () => {
	let mod;
	beforeEach( () => {
		mod = loadModule();
		document.body.innerHTML = `
			<a class="order-now" href="#">Order Now</a>
			<a class="order-now" href="#">Order Now</a>
			<a class="order-now-button" href="#">Order</a>
			<a class="order-now" href="#">Order Now</a>
			<a class="order-now" href="#">Order Now</a>
		`;
	} );

	test( 'updates every matching element with the correct URL', () => {
		const location = { orderUrl: 'https://example.com/arizona-order', slug: 'arizona' };
		const count = mod.applyLocationToButtons( document, '.order-now, .order-now-button', location );

		expect( count ).toBe( 5 );
		document.querySelectorAll( '.order-now, .order-now-button' ).forEach( ( el ) => {
			expect( el.getAttribute( 'href' ) ).toBe( 'https://example.com/arizona-order' );
			expect( el.getAttribute( 'data-rlr-applied' ) ).toBe( '1' );
		} );
	} );

	test( 'an invalid selector fails silently instead of throwing', () => {
		expect( () => mod.applyLocationToButtons( document, ':::not-a-selector', { orderUrl: 'x' } ) ).not.toThrow();
		expect( mod.applyLocationToButtons( document, ':::not-a-selector', { orderUrl: 'x' } ) ).toBe( 0 );
	} );

	test( 'missing orderUrl is a no-op', () => {
		expect( mod.applyLocationToButtons( document, '.order-now', {} ) ).toBe( 0 );
	} );
} );

describe( 'RlrController integration behavior', () => {
	let mod;
	let config;

	const arizona = { id: 1, slug: 'arizona', name: 'Arizona', orderUrl: 'https://example.com/arizona-order' };
	const manchester = { id: 2, slug: 'manchester', name: 'Manchester', orderUrl: 'https://example.com/manchester-order' };

	beforeEach( () => {
		document.body.innerHTML = `
			<a class="order-now" href="#">Order Now</a>
			<div id="rlr-modal-overlay" class="rlr-modal-overlay" hidden aria-hidden="true">
				<div id="rlr-modal">
					<button data-rlr-close-modal>close</button>
					<button class="rlr-location-option" data-rlr-location-id="1" data-rlr-location-slug="arizona" data-rlr-location-url="https://example.com/arizona-order" data-rlr-location-name="Arizona">Arizona</button>
					<button class="rlr-location-option" data-rlr-location-id="2" data-rlr-location-slug="manchester" data-rlr-location-url="https://example.com/manchester-order" data-rlr-location-name="Manchester">Manchester</button>
				</div>
			</div>
		`;
		document.cookie = 'rlr_selected_location=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		window.localStorage.clear();

		global.fetch = jest.fn( () => Promise.resolve( { ok: true, json: () => Promise.resolve( { success: true } ) } ) );

		mod = loadModule();

		config = {
			restUrl: 'https://example.test/wp-json/rlr/v1',
			nonce: 'test-nonce',
			siteHost: 'example.test',
			locations: [ arizona, manchester ],
			settings: {
				buttonSelector: '.order-now',
				changeLocationSelector: '.rlr-change-location',
				storageDurationDays: 30,
				storageMethod: 'both',
				enableGeolocation: true,
				enablePopup: true,
				debugMode: false,
			},
			analytics: { mode: 'internal', requireConsent: false },
		};
	} );

	test( 'Test 3/11: manual selection applies buttons, stores location, and tracks manual_selection', () => {
		const controller = new mod.RlrController( config );
		controller.bindEvents();

		controller.selectLocation( manchester );

		expect( document.querySelector( '.order-now' ).getAttribute( 'href' ) ).toBe( 'https://example.com/manchester-order' );
		expect( global.fetch ).toHaveBeenCalledWith(
			expect.stringContaining( '/track' ),
			expect.objectContaining( { body: expect.stringContaining( '"event":"manual_selection"' ) } )
		);
	} );

	test( 'Test 5: changing an existing selection tracks location_changed, not manual_selection', () => {
		const controller = new mod.RlrController( config );
		controller.applyLocation( arizona, 'auto', 'high' );
		global.fetch.mockClear();

		controller.selectLocation( manchester );

		expect( global.fetch ).toHaveBeenCalledWith(
			expect.stringContaining( '/track' ),
			expect.objectContaining( { body: expect.stringContaining( '"event":"location_changed"' ) } )
		);
		expect( document.querySelector( '.order-now' ).getAttribute( 'href' ) ).toBe( 'https://example.com/manchester-order' );
	} );

	test( 'Test 4: a saved (non-expired) location is read back without re-detecting', () => {
		window.localStorage.setItem( 'rlr_location', JSON.stringify( { slug: 'manchester', source: 'manual', ts: Date.now() } ) );

		const controller = new mod.RlrController( config );
		const saved = controller.getSavedLocation();

		expect( saved ).not.toBeNull();
		expect( saved.slug ).toBe( 'manchester' );
	} );

	test( 'Test 4: an expired saved location is treated as absent', () => {
		const twoMonthsAgo = Date.now() - 60 * 24 * 60 * 60 * 1000;
		window.localStorage.setItem( 'rlr_location', JSON.stringify( { slug: 'manchester', source: 'manual', ts: twoMonthsAgo } ) );

		const controller = new mod.RlrController( config );
		expect( controller.getSavedLocation() ).toBeNull();
	} );

	test( 'runDetection sends the X-WP-Nonce header, so WordPress recognizes a logged-in admin and returns debug data', () => {
		// Regression test: without this header, WP's REST cookie auth treats
		// the request as anonymous regardless of the session cookie, so an
		// admin with Debug Mode enabled would never see the debug block.
		const controller = new mod.RlrController( config );
		controller.runDetection();

		expect( global.fetch ).toHaveBeenCalledWith(
			expect.stringContaining( '/detect' ),
			expect.objectContaining( { headers: expect.objectContaining( { 'X-WP-Nonce': config.nonce } ) } )
		);
	} );

	test( 'order button click is tracked only after a location has been applied', () => {
		const controller = new mod.RlrController( config );
		controller.bindEvents();

		const button = document.querySelector( '.order-now' );
		button.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( global.fetch ).not.toHaveBeenCalled();

		controller.applyLocation( arizona, 'manual', 'high' );
		global.fetch.mockClear();

		button.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		expect( global.fetch ).toHaveBeenCalledWith(
			expect.stringContaining( '/track' ),
			expect.objectContaining( { body: expect.stringContaining( '"event":"order_click"' ) } )
		);
	} );

	test( 'consent gating: no analytics request is sent without consent', () => {
		config.analytics = { mode: 'internal', requireConsent: true, consentCookieName: 'cc', consentCookieValue: 'yes' };
		const controller = new mod.RlrController( config );

		controller.trackEvent( 'manual_selection' );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'analytics disabled entirely: trackEvent never calls fetch', () => {
		config.analytics = { mode: 'disabled' };
		const controller = new mod.RlrController( config );

		controller.trackEvent( 'order_click' );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'modal open/close toggles hidden + aria-hidden', () => {
		const controller = new mod.RlrController( config );
		controller.config.analytics = { mode: 'disabled' };

		controller.openModal();
		expect( controller.modalOverlay.hidden ).toBe( false );
		expect( controller.modalOverlay.getAttribute( 'aria-hidden' ) ).toBe( 'false' );

		controller.closeModal();
		expect( controller.modalOverlay.hidden ).toBe( true );
		expect( controller.modalOverlay.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	} );
} );
