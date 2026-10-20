/** Keep cached header messages in their schedule using freshly verified server UTC. */
( function () {
	'use strict';

	const item = document.currentScript.previousElementSibling;
	if ( ! item || ! item.hasAttribute( 'data-mrn-utility-refresh' ) ) {
		return;
	}

	let enabled = item.dataset.mrnUtilityEnabled === '1';
	let scheduled = item.hasAttribute( 'data-mrn-utility-schedule' );
	let start = item.dataset.mrnUtilityStart ? Number( item.dataset.mrnUtilityStart ) * 1000 : null;
	let end = item.dataset.mrnUtilityEnd ? Number( item.dataset.mrnUtilityEnd ) * 1000 : null;
	let rules;
	let formatter;
	let clockUrl;
	try {
		clockUrl = new URL( item.dataset.mrnUtilityClockUrl, window.location.href );
		if ( clockUrl.origin !== window.location.origin || ! /^https?:$/.test( clockUrl.protocol ) || ! /^[a-f0-9]{64}$/.test( item.dataset.mrnUtilityVersion ) ) {
			return;
		}
		rules = JSON.parse( item.dataset.mrnUtilityRules );
		formatter = new Intl.DateTimeFormat( 'en-US', {
			timeZone: rules.offset === null ? rules.timezone : 'UTC',
			calendar: 'gregory', numberingSystem: 'latn', hourCycle: 'h23',
			year: 'numeric', month: '2-digit', day: '2-digit', weekday: 'short',
			hour: '2-digit', minute: '2-digit', second: '2-digit',
		} );
	} catch ( error ) {
		// Invalid or unsupported schedule data stays hidden.
		return;
	}
	let hasDayRules = rules.weekdays.length || rules.holidays.length || rules.custom_days.length;
	let hasRecurringRules = scheduled && ( hasDayRules || rules.daily_start !== null );
	const dayMilliseconds = 86400000;
	const holidayCache = new Map();
	let timer;
	let syncTimer;
	let sample = null;
	let requestController = null;
	let requestId = 0;
	let lastMonotonic = performance.now();
	let lastWallTime = Date.now();
	const sampleLifetime = 120000;
	const startedKey = 'mrn-header-utility-started:' + clockUrl.href;
	let confirmedStartedVersion = null;

	function canKeepVisibleWithoutClock() {
		return enabled && ( ! scheduled || (
			end === null && ! hasRecurringRules && confirmedStartedVersion === item.dataset.mrnUtilityVersion
		) );
	}

	function restoreConfirmedStart() {
		if ( ! enabled || ! scheduled || end !== null || hasRecurringRules ) {
			return;
		}
		try {
			const saved = JSON.parse( window.sessionStorage.getItem( startedKey ) );
			if ( saved && saved.version === item.dataset.mrnUtilityVersion && Number.isFinite( saved.serverTime ) && saved.serverTime > 0 &&
				( start === null || ( Number.isFinite( start ) && saved.serverTime >= start ) ) ) {
				// A server-confirmed start stays in the past forever. No local clock,
				// elapsed-time estimate, or cached on/off decision is reused.
				confirmedStartedVersion = saved.version;
			}
		} catch ( error ) {
			// Blocked or malformed storage falls back to the fresh server check.
		}
	}

	function rememberConfirmedStart( serverTime ) {
		if ( item.hidden || ! scheduled || end !== null || hasRecurringRules || confirmedStartedVersion === item.dataset.mrnUtilityVersion ) {
			return;
		}
		confirmedStartedVersion = item.dataset.mrnUtilityVersion;
		try {
			window.sessionStorage.setItem( startedKey, JSON.stringify( { version: confirmedStartedVersion, serverTime } ) );
		} catch ( error ) {
			// This document can still retain its already verified open-ended state.
		}
	}

	function refreshMessage( data ) {
		if ( data.version === item.dataset.mrnUtilityVersion ) {
			return false;
		}
		const message = data.message;
		if ( ! message || typeof message.enabled !== 'boolean' || typeof message.scheduled !== 'boolean' || typeof message.html !== 'string' || ! Array.isArray( message.styles ) ||
			! [ message.start, message.end ].every( value => value === null || Number.isFinite( value ) ) ||
			! message.rules || ! [ 'weekdays', 'holidays', 'custom_days' ].every( name => Array.isArray( message.rules[ name ] ) ) ||
			! [ message.rules.daily_start, message.rules.daily_end, message.rules.offset ].every( value => value === null || Number.isFinite( value ) ) ) {
			throw new Error( 'Invalid message update' );
		}
		const nextFormatter = new Intl.DateTimeFormat( 'en-US', {
			timeZone: message.rules.offset === null ? message.rules.timezone : 'UTC',
			calendar: 'gregory', numberingSystem: 'latn', hourCycle: 'h23',
			year: 'numeric', month: '2-digit', day: '2-digit', weekday: 'short',
			hour: '2-digit', minute: '2-digit', second: '2-digit',
		} );
		const styles = message.styles.map( href => {
			const url = new URL( href, window.location.href );
			if ( ! /^https?:$/.test( url.protocol ) ) {
				throw new Error( 'Invalid message stylesheet' );
			}
			return url.href;
		} );
		const restoreFocus = item.contains( document.activeElement );
		confirmedStartedVersion = null;
		try {
			window.sessionStorage.removeItem( startedKey );
		} catch ( error ) {
			// Version matching prevents any old stored confirmation being reused.
		}
		// Markup comes only from the same-origin, read-only endpoint, which uses
		// the same sanitized/escaped rendering helper as the initial page.
		item.innerHTML = message.enabled ? message.html : '';
		enabled = message.enabled;
		scheduled = enabled && message.scheduled;
		start = message.start === null ? null : message.start * 1000;
		end = message.end === null ? null : message.end * 1000;
		rules = message.rules;
		formatter = nextFormatter;
		hasDayRules = rules.weekdays.length || rules.holidays.length || rules.custom_days.length;
		hasRecurringRules = scheduled && ( hasDayRules || rules.daily_start !== null );
		holidayCache.clear();
		item.dataset.mrnUtilityVersion = data.version;
		item.dataset.mrnUtilityEnabled = enabled ? '1' : '0';
		item.dataset.mrnUtilityStart = message.start === null ? '' : String( message.start );
		item.dataset.mrnUtilityEnd = message.end === null ? '' : String( message.end );
		item.dataset.mrnUtilityRules = JSON.stringify( rules );
		item.toggleAttribute( 'data-mrn-utility-schedule', scheduled );
		for ( const href of styles ) {
			if ( ! Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) ).some( link => link.href === href ) ) {
				const link = document.createElement( 'link' );
				link.rel = 'stylesheet';
				link.href = href;
				document.head.append( link );
			}
		}
		return restoreFocus;
	}

	function invalidate() {
		sample = null;
		item.hidden = ! canKeepVisibleWithoutClock();
		item.dataset.mrnUtilityClock = 'waiting';
		window.clearTimeout( timer );
	}

	async function synchronize() {
		if ( document.hidden || requestController ) {
			return;
		}
		const controller = new AbortController();
		requestController = controller;
		const id = ++requestId;
		const started = performance.now();
		const timeout = window.setTimeout( () => controller.abort(), 5000 );
		try {
			const token = Array.from( crypto.getRandomValues( new Uint32Array( 4 ) ), value => value.toString( 16 ).padStart( 8, '0' ) ).join( '' );
			const response = await fetch( clockUrl.href, {
				method: 'POST', cache: 'no-store', credentials: 'omit', redirect: 'error',
				headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( { token, refresh: true, version: item.dataset.mrnUtilityVersion } ), signal: controller.signal,
			} );
			const data = await response.json();
			const received = performance.now();
			if ( id !== requestId ) {
				return;
			}
			if ( ! response.ok || Number( response.headers.get( 'Age' ) ) > 0 || data.token !== token || ! /^[a-f0-9]{64}$/.test( data.version ) || ! Number.isFinite( data.now ) || data.now <= 0 || ! Number.isFinite( data.received ) || data.received > data.now || received - started > 5000 ) {
				throw new Error( 'Unverified server clock' );
			}
			const restoreFocus = refreshMessage( data );
			// Exclude WordPress processing time before estimating network transit.
			// Device wall time never enters this UTC anchor or visibility decisions.
			const transit = Math.max( 0, received - started - ( data.now - data.received ) );
			sample = { utc: data.now + transit / 2, monotonic: received };
			item.dataset.mrnUtilityClock = 'synced';
			update();
			if ( restoreFocus && ! item.hidden ) {
				item.querySelector( 'a' )?.focus( { preventScroll: true } );
			}
		} catch ( error ) {
			if ( id === requestId ) {
				invalidate();
				item.dataset.mrnUtilityClock = 'unavailable';
			}
		} finally {
			window.clearTimeout( timeout );
			if ( id === requestId ) {
				requestController = null;
				window.clearTimeout( syncTimer );
				syncTimer = window.setTimeout( synchronize, sample ? 60000 : 15000 );
			}
		}
	}

	function revalidate( event ) {
		if ( event && event.type === 'focus' && requestController ) {
			return;
		}
		invalidate();
		window.clearTimeout( syncTimer );
		++requestId;
		if ( requestController ) {
			requestController.abort();
			requestController = null;
		}
		lastMonotonic = performance.now();
		lastWallTime = Date.now();
		if ( ! event || event.type !== 'pagehide' ) {
			synchronize();
		}
	}

	function dateKey( date ) {
		return date.toISOString().slice( 0, 10 );
	}

	function holidayDates( year ) {
		if ( holidayCache.has( year ) ) {
			return holidayCache.get( year );
		}
		const dates = new Set();
		const fixed = { new_years_day: [ 1, 1 ], juneteenth: [ 6, 19 ], independence_day: [ 7, 4 ], veterans_day: [ 11, 11 ], christmas_day: [ 12, 25 ] };
		const floating = { mlk_day: [ 1, 1, 3 ], presidents_day: [ 2, 1, 3 ], memorial_day: [ 5, 1, -1 ], labor_day: [ 9, 1, 1 ], columbus_day: [ 10, 1, 2 ], thanksgiving_day: [ 11, 4, 4 ] };
		for ( const holiday of rules.holidays ) {
			let date;
			if ( fixed[ holiday ] ) {
				const [ month, day ] = fixed[ holiday ];
				date = new Date( Date.UTC( year, month - 1, day ) );
				dates.add( dateKey( date ) );
				if ( date.getUTCDay() === 6 ) {
					date = new Date( date.getTime() - dayMilliseconds );
				} else if ( date.getUTCDay() === 0 ) {
					date = new Date( date.getTime() + dayMilliseconds );
				}
			} else if ( floating[ holiday ] ) {
				const [ month, weekday, nth ] = floating[ holiday ];
				date = new Date( Date.UTC( year, month - ( nth === -1 ? 0 : 1 ), nth === -1 ? 0 : 1 ) );
				const distance = nth === -1 ? -( ( date.getUTCDay() - weekday + 7 ) % 7 ) : ( weekday - date.getUTCDay() + 7 ) % 7 + 7 * ( nth - 1 );
				date = new Date( date.getTime() + distance * dayMilliseconds );
			}
			if ( date ) {
				dates.add( dateKey( date ) );
			}
		}
		holidayCache.set( year, dates );
		return dates;
	}

	function eligibleDay( date ) {
		if ( ! hasDayRules ) {
			return true;
		}
		const key = dateKey( date );
		const year = date.getUTCFullYear();
		return rules.weekdays.includes( String( date.getUTCDay() ) ) ||
			rules.custom_days.includes( key ) || rules.custom_days.includes( key.slice( 5 ) ) ||
			[ year - 1, year, year + 1 ].some( value => holidayDates( value ).has( key ) );
	}

	function matchesRecurringRules( now ) {
		const parts = {};
		for ( const part of formatter.formatToParts( new Date( now + ( rules.offset || 0 ) * 1000 ) ) ) {
			parts[ part.type ] = part.value;
		}
		const date = new Date( Date.UTC( Number( parts.year ), Number( parts.month ) - 1, Number( parts.day ) ) );
		const seconds = Number( parts.hour ) * 3600 + Number( parts.minute ) * 60 + Number( parts.second );
		if ( rules.daily_start === null ) {
			return eligibleDay( date );
		}
		if ( rules.daily_end > rules.daily_start ) {
			return eligibleDay( date ) && seconds >= rules.daily_start && seconds < rules.daily_end;
		}
		return ( seconds >= rules.daily_start && eligibleDay( date ) ) ||
			( seconds < rules.daily_end && eligibleDay( new Date( date.getTime() - dayMilliseconds ) ) );
	}

	function update() {
		window.clearTimeout( timer );
		if ( ! sample || performance.now() - sample.monotonic >= sampleLifetime ) {
			invalidate();
			return;
		}
		const now = sample.utc + performance.now() - sample.monotonic;
		item.hidden = ! enabled || ( scheduled && (
			( start !== null && ( ! Number.isFinite( start ) || now < start ) ) ||
			( end !== null && ( ! Number.isFinite( end ) || now >= end ) ) ||
			( hasRecurringRules && ! matchesRecurringRules( now ) ) ) );
		rememberConfirmedStart( now );
		let next = scheduled ? ( start !== null && now < start ? start : end ) : null;
		if ( hasRecurringRules && ( start === null || now >= start ) && ( end === null || now < end ) ) {
			// One local computation per minute keeps weekly/holiday rules correct
			// through DST jumps, including intervals skipped by the clock change.
			next = Math.min( next === null ? Infinity : next, now - now % 60000 + 60000 );
			for ( const boundary of [ rules.daily_start, rules.daily_end ] ) {
				if ( boundary !== null && boundary % 60 !== 0 ) {
					let candidate = now - now % 60000 + ( boundary % 60 ) * 1000;
					if ( candidate <= now ) {
						candidate += 60000;
					}
					next = Math.min( next, candidate );
				}
			}
		}
		if ( next !== null && Number.isFinite( next ) && next > now ) {
			// Browsers cap timeout delays at a signed 32-bit integer.
			timer = window.setTimeout( update, Math.min( next - now + 1, 2147483647 ) );
		}
	}

	// Some browsers pause the monotonic counter during OS sleep. Wall time is
	// used ONLY as a wake/clock-change hint: a discrepancy hides the message and
	// requests new server UTC; it can never make a message become visible.
	window.setInterval( () => {
		const monotonic = performance.now();
		const wallTime = Date.now();
		const interrupted = Math.abs( wallTime - lastWallTime - ( monotonic - lastMonotonic ) ) > 2000;
		lastMonotonic = monotonic;
		lastWallTime = wallTime;
		if ( ! document.hidden && ( interrupted || ( sample && monotonic - sample.monotonic >= sampleLifetime ) ) ) {
			revalidate();
		}
	}, 1000 );
	restoreConfirmedStart();
	invalidate();
	synchronize();
	window.addEventListener( 'pageshow', event => {
		if ( event.persisted ) {
			revalidate();
		}
	} );
	window.addEventListener( 'focus', revalidate );
	window.addEventListener( 'online', revalidate );
	window.addEventListener( 'pagehide', revalidate );
	document.addEventListener( 'visibilitychange', revalidate );
}() );
