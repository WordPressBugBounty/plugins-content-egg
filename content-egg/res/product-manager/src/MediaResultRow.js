import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import HoverPreview from './HoverPreview';
import useHoverPreview from './useHoverPreview';
import { mediaSource } from './mediaFields';

// A media search result rendered as a grid CARD (the image/video analogue of
// ResultRow, which is a list row). The whole card adds on click; a corner badge
// shows the add/busy/added state, videos get a play glyph + source label.
export default function MediaResultRow( {
	product,
	isAdded,
	isBusy,
	disabled,
	onAdd,
	onSetFeatured,
	parserType,
} ) {
	const isVideo = parserType === 'VIDEO';
	const source = isVideo ? mediaSource( product ) : null;
	const title = decodeEntities( product?.title || product?.unique_id || '' );

	const { thumbRef, position, show, hide } = useHoverPreview( {
		enabled: !! product?.img,
		side: 'right',
	} );

	const addable = ! isAdded && ! isBusy && ! disabled;

	let stateIndicator = <span className="cegg-pm-media-card__add">＋</span>;
	if ( isBusy ) {
		stateIndicator = <Spinner />;
	} else if ( isAdded ) {
		stateIndicator = <span className="cegg-pm-media-card__added">✓</span>;
	}
	const cardInteraction = addable
		? {
				role: 'button',
				tabIndex: 0,
				onClick: ( event ) => {
					if ( event.target.closest( 'a, button' ) ) {
						return;
					}
					onAdd();
				},
				onKeyDown: ( event ) => {
					if ( event.key !== 'Enter' && event.key !== ' ' ) {
						return;
					}
					event.preventDefault();
					onAdd();
				},
				'aria-label': sprintf(
					/* translators: %s: media title */
					__( 'Add %s', 'content-egg' ),
					title
				),
		  }
		: {};

	return (
		<div
			className={ `cegg-pm-media-card${ isVideo ? ' is-video' : '' }${
				isAdded ? ' is-added' : ''
			}${ addable ? ' is-clickable' : '' }` }
			{ ...cardInteraction }
		>
			<span
				ref={ thumbRef }
				className="cegg-pm-media-card__thumb"
				onMouseEnter={ show }
				onMouseLeave={ hide }
			>
				{ product?.img ? <img src={ product.img } alt="" /> : null }
				{ isVideo && (
					<span
						className="cegg-pm-media-card__play"
						aria-hidden="true"
					>
						▶
					</span>
				) }
				<span className="cegg-pm-media-card__state">
					{ stateIndicator }
				</span>
				{ onSetFeatured && (
					<button
						type="button"
						className="cegg-pm-media-card__feature"
						title={ __(
							'Add & set as featured image',
							'content-egg'
						) }
						aria-label={ __(
							'Add & set as featured image',
							'content-egg'
						) }
						onClick={ ( event ) => {
							event.stopPropagation();
							onSetFeatured();
						} }
					>
						★
					</button>
				) }
				{ source && (
					<span className="cegg-pm-media-card__source">
						{ source.label }
					</span>
				) }
			</span>
			<HoverPreview position={ position } img={ product?.img } />
			<div className="cegg-pm-media-card__title" title={ title }>
				{ title }
			</div>
		</div>
	);
}
