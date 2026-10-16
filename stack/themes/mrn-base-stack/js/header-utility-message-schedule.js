/** Keep cached header messages in their absolute schedule window. */
( function () {
	'use strict';

	const item = document.currentScript.previousElementSibling;
	if ( ! item || ! item.hasAttribute( 'data-mrn-utility-schedule' ) ) {
		return;
	}

	const start = item.dataset.mrnUtilityStart ? Number( item.dataset.mrnUtilityStart ) * 1000 : null;
	const end = item.dataset.mrnUtilityEnd ? Number( item.dataset.mrnUtilityEnd ) * 1000 : null;
	let rules;
	let formatter;
	try {
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
	const hasDayRules = rules.weekdays.length || rules.holidays.length || rules.custom_days.length;
	const hasRecurringRules = hasDayRules || rules.daily_start !== null;
	const dayMilliseconds = 86400000;
	const holidayCache = new Map();
	let timer;

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
		const now = Date.now();
		item.hidden =
			( start !== null && ( ! Number.isFinite( start ) || now < start ) ) ||
			( end !== null && ( ! Number.isFinite( end ) || now >= end ) ) ||
			( hasRecurringRules && ! matchesRecurringRules( now ) );
		let next = start !== null && now < start ? start : end;
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

	update();
	window.addEventListener( 'pageshow', update );
	document.addEventListener( 'visibilitychange', update );
}() );
