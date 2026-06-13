/**
 * Parses a quantity typed by the user.
 *
 * Accepts both '.' and ',' as decimal separator. Returns:
 * - null      for an empty value (quantity is optional),
 * - undefined for an invalid value,
 * - a number  otherwise.
 */
export const parseQuantity = ( raw ) => {
	const value = String( raw ?? '' )
		.trim()
		.replace( ',', '.' );

	if ( value === '' ) {
		return null;
	}

	const number = Number( value );

	return Number.isFinite( number ) && number >= 0 ? number : undefined;
};
