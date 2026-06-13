/**
 * VVK Ingredients Table — progressive enhancement for the public tables:
 * servings scaling and metric/imperial unit conversion. Without JS the
 * tables render fully server-side; the controls stay hidden.
 */
( function () {
	'use strict';

	var GLYPHS = {
		'1/2': '½',
		'1/3': '⅓',
		'2/3': '⅔',
		'1/4': '¼',
		'3/4': '¾',
		'1/5': '⅕',
		'2/5': '⅖',
		'3/5': '⅗',
		'4/5': '⅘',
		'1/6': '⅙',
		'5/6': '⅚',
		'1/8': '⅛',
		'3/8': '⅜',
		'5/8': '⅝',
		'7/8': '⅞'
	};

	/* Continued-fraction expansion of 0 < value < 1 (mirrors the PHP side). */
	function ratio( value ) {
		var h1 = 1, h2 = 0, k1 = 0, k2 = 1;
		var b = 1 / value;
		var a, aux, i = 0;

		do {
			if ( ++i > 32 ) {
				return null;
			}

			b = 1 / b;
			a = Math.floor( b );

			aux = h1; h1 = a * h1 + h2; h2 = aux;
			aux = k1; k1 = a * k1 + k2; k2 = aux;

			b -= a;

			if ( b === 0 ) {
				break;
			}
		} while ( Math.abs( value - h1 / k1 ) > value * 1e-6 );

		return k1 > 16 ? null : [ h1, k1 ];
	}

	function trimDecimal( value ) {
		return String( Math.round( value * 100 ) / 100 );
	}

	function format( value, fractions ) {
		if ( ! isFinite( value ) || value <= 0 ) {
			return '';
		}

		if ( ! fractions ) {
			return trimDecimal( value );
		}

		var whole = Math.floor( value + 1e-9 );
		var rest = value - whole;

		if ( rest < 1e-6 ) {
			return String( whole );
		}

		var r = ratio( rest );

		if ( ! r ) {
			return trimDecimal( value );
		}

		var key = r[ 0 ] + '/' + r[ 1 ];
		var frac = GLYPHS[ key ] || key;

		return whole > 0 ? whole + ' ' + frac : frac;
	}

	function init( wrap ) {
		var catalog;

		try {
			catalog = JSON.parse( wrap.dataset.units || '[]' );
		} catch ( e ) {
			catalog = [];
		}

		var unitById = {};
		catalog.forEach( function ( unit ) {
			unitById[ unit.id ] = unit;
		} );

		var fractions = wrap.dataset.fractions === '1';
		var origServings = parseInt( wrap.dataset.servings || '0', 10 );
		var servings = origServings;
		var system = '';

		var controls = wrap.querySelector( '.vvkit-controls' );

		if ( ! controls ) {
			return;
		}

		var rows = Array.prototype.map.call( wrap.querySelectorAll( '.vvkit-qty' ), function ( span ) {
			var quantity = parseFloat( span.dataset.quantity );
			var unitSpan = span.parentNode.querySelector( '.vvkit-unit-name' );

			return {
				span: span,
				unitSpan: unitSpan,
				quantity: isFinite( quantity ) ? quantity : null,
				unit: unitById[ parseInt( span.dataset.unitId || '0', 10 ) ] || null,
				unitName: unitSpan ? unitSpan.textContent : ''
			};
		} );

		var convertible = rows.some( function ( row ) {
			if ( ! row.unit || ! row.unit.factor || row.quantity === null ) {
				return false;
			}

			return catalog.some( function ( unit ) {
				return unit.dimension === row.unit.dimension && unit.system && unit.system !== row.unit.system;
			} );
		} );

		var servingsBox = controls.querySelector( '.vvkit-servings' );
		var systemBox = controls.querySelector( '.vvkit-system' );
		var show = false;

		if ( servingsBox ) {
			if ( origServings > 0 ) {
				show = true;
			} else {
				servingsBox.hidden = true;
			}
		}

		if ( systemBox ) {
			if ( convertible ) {
				show = true;
			} else {
				systemBox.hidden = true;
			}
		}

		if ( ! show ) {
			return;
		}

		controls.hidden = false;

		function render() {
			var factor = origServings > 0 ? servings / origServings : 1;

			rows.forEach( function ( row ) {
				if ( row.quantity === null ) {
					return;
				}

				var quantity = row.quantity * factor;
				var unitName = row.unitName;

				if ( system && row.unit && row.unit.factor > 0 && row.unit.system !== system ) {
					var candidates = catalog
						.filter( function ( unit ) {
							return unit.dimension === row.unit.dimension && unit.system === system && unit.factor > 0;
						} )
						.sort( function ( a, b ) {
							return b.factor - a.factor;
						} );

					if ( candidates.length ) {
						var base = quantity * row.unit.factor;
						var chosen = null;

						for ( var i = 0; i < candidates.length; i++ ) {
							if ( base / candidates[ i ].factor >= 0.5 ) {
								chosen = candidates[ i ];
								break;
							}
						}

						chosen = chosen || candidates[ candidates.length - 1 ];
						quantity = base / chosen.factor;
						unitName = chosen.name;
					}
				}

				row.span.textContent = format( quantity, fractions );

				if ( row.unitSpan ) {
					row.unitSpan.textContent = unitName;
				}
			} );

			var value = wrap.querySelector( '.vvkit-servings-value' );

			if ( value && servings > 0 ) {
				value.textContent = String( servings );
			}

			if ( systemBox ) {
				Array.prototype.forEach.call( systemBox.querySelectorAll( 'button' ), function ( button ) {
					button.classList.toggle( 'is-active', ( button.dataset.system || '' ) === system );
				} );
			}
		}

		wrap.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );

			if ( ! button ) {
				return;
			}

			if ( button.classList.contains( 'vvkit-servings-dec' ) ) {
				if ( servings > 1 ) {
					servings--;
					render();
				}
			} else if ( button.classList.contains( 'vvkit-servings-inc' ) ) {
				servings++;
				render();
			} else if ( systemBox && systemBox.contains( button ) ) {
				system = button.dataset.system || '';
				render();
			}
		} );

		render();
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '.vvkit-wrap' ), init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
