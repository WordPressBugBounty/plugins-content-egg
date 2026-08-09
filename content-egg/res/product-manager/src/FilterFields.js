import { SelectControl, TextControl } from '@wordpress/components';

// Positive integer or float only (no sign, no letters, no exponent).
const POSITIVE_NUMBER = /^\d*\.?\d*$/;

export default function FilterFields( { filters, values, onChange } ) {
	if ( ! Array.isArray( filters ) || filters.length === 0 ) {
		return null;
	}

	return (
		<div className="cegg-pm-filters">
			{ filters.map( ( filter ) => {
				const value = values[ filter.key ] ?? filter.default ?? '';

				// Labels are hidden so this row stays the same height as the
				// (label-less) search field — otherwise the toolbar jumps when
				// filters appear. The label rides along as placeholder/aria.
				if ( filter.type === 'select' ) {
					return (
						<SelectControl
							key={ filter.key }
							label={ filter.label }
							hideLabelFromVision
							value={ value }
							options={ ( filter.options || [] ).map(
								( option ) => ( {
									label: option.label,
									value: option.value,
								} )
							) }
							onChange={ ( next ) =>
								onChange( { ...values, [ filter.key ]: next } )
							}
							__nextHasNoMarginBottom
						/>
					);
				}

				const isNumber = filter.type === 'number';
				const setValue = ( next ) => {
					if (
						isNumber &&
						next !== '' &&
						! POSITIVE_NUMBER.test( next )
					) {
						return; // reject anything that isn't a positive number
					}
					onChange( { ...values, [ filter.key ]: next } );
				};

				return (
					<TextControl
						key={ filter.key }
						label={ filter.label }
						hideLabelFromVision
						placeholder={ filter.label }
						value={ value }
						type="text"
						inputMode={ isNumber ? 'decimal' : undefined }
						onChange={ setValue }
						style={ { width: '96px' } }
						__nextHasNoMarginBottom
					/>
				);
			} ) }
		</div>
	);
}
