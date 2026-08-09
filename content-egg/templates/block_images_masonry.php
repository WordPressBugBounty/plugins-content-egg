<?php

/*
 * Name: Images masonry
 * Module Types: IMAGE
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// Masonry gallery for IMAGE modules: big images in 1–2 columns (configurable via
// `cols`), natural aspect ratios (no cropping) via CSS multi-column layout. Same
// click-to-enlarge lightbox as block_images.php (prev/next, Esc, click-outside),
// with masonry-scoped classes so the two templates never collide on one page.
//
// IMPORTANT: keep the inline <script> free of any bare "<" (comments included) —
// a stray "<" breaks WordPress <script>-tag detection in wptexturize and mangles
// later "&&" into an HTML entity, silently breaking the script.

$cols = !empty($params['cols']) ? (int) $params['cols'] : 3;
if ($cols < 1)
    $cols = 1;
if ($cols > 4)
    $cols = 4;

$gallery_id = TemplateHelper::generateGlobalId('cegg_masonry_');

?>

<div id="<?php echo esc_attr($gallery_id); ?>" class="container px-0 mb-5 mt-1 cegg-masonry" style="--cegg-masonry-cols: <?php echo (int) $cols; ?>" <?php $this->colorMode(); ?>>
    <?php foreach ($items as $i => $item): ?>
        <?php $this->setItem($item, $i); ?>
        <?php if (!$this->isVisible('img')) continue; ?>
        <?php $full = !empty($item['img_large']) ? $item['img_large'] : (!empty($item['img']) ? $item['img'] : ''); ?>
        <?php $caption = ($this->isVisible('title', false) && !empty($item['title'])) ? $item['title'] : ''; ?>
        <figure class="cegg-masonry__item">
            <button type="button" class="cegg-masonry__tile" data-full="<?php echo esc_url($full); ?>" data-caption="<?php echo esc_attr($caption); ?>" aria-label="<?php echo esc_attr($caption ?: TemplateHelper::__('View image')); ?>">
                <?php TemplateHelper::displayImage($item, 0, 0, array('class' => 'cegg-masonry__img')); ?>
                <span class="cegg-masonry__zoom" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" /><path d="M21 21l-4.3-4.3M11 8v6M8 11h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                </span>
            </button>
            <?php if ($caption): ?>
                <figcaption class="cegg-masonry__caption small lh-sm"><?php echo esc_html($caption); ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endforeach; ?>

    <div class="cegg-lightbox" role="dialog" aria-modal="true" hidden>
        <button type="button" class="cegg-lightbox__close" aria-label="<?php echo esc_attr(TemplateHelper::__('Close')); ?>">&times;</button>
        <button type="button" class="cegg-lightbox__nav cegg-lightbox__prev" aria-label="<?php echo esc_attr(TemplateHelper::__('Previous')); ?>">&lsaquo;</button>
        <figure class="cegg-lightbox__stage">
            <img class="cegg-lightbox__img" src="" alt="" />
            <figcaption class="cegg-lightbox__caption"></figcaption>
        </figure>
        <button type="button" class="cegg-lightbox__nav cegg-lightbox__next" aria-label="<?php echo esc_attr(TemplateHelper::__('Next')); ?>">&rsaquo;</button>
    </div>
</div>

<?php if (!function_exists('cegg_images_masonry_css_enqueue')): ?>
    <?php function cegg_images_masonry_css_enqueue()
    { ?>
        <style>
            .cegg-masonry {
                column-count: 1;
                column-gap: 1rem;
            }

            @media (min-width: 576px) {
                .cegg-masonry {
                    column-count: 2;
                }
            }

            @media (min-width: 992px) {
                .cegg-masonry {
                    column-count: var(--cegg-masonry-cols, 3);
                }
            }

            .cegg-masonry__item {
                margin: 0 0 1rem;
                break-inside: avoid;
            }

            .cegg-masonry__tile {
                position: relative;
                display: block;
                width: 100%;
                padding: 0;
                border: 0;
                border-radius: .5rem;
                background: var(--bs-tertiary-bg, #f1f2f4);
                overflow: hidden;
                cursor: zoom-in;
                box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
            }

            .cegg-masonry__img {
                width: 100%;
                height: auto;
                display: block;
                transition: transform .35s ease;
            }

            .cegg-masonry__tile:hover .cegg-masonry__img,
            .cegg-masonry__tile:focus-visible .cegg-masonry__img {
                transform: scale(1.03);
            }

            .cegg-masonry__zoom {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                background: rgba(0, 0, 0, 0);
                opacity: 0;
                transition: opacity .25s ease, background .25s ease;
            }

            .cegg-masonry__tile:hover .cegg-masonry__zoom,
            .cegg-masonry__tile:focus-visible .cegg-masonry__zoom {
                opacity: 1;
                background: rgba(0, 0, 0, .28);
            }

            .cegg-masonry__zoom svg {
                width: 2.25rem;
                height: 2.25rem;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .45));
            }

            .cegg-masonry__caption {
                margin-top: .4rem;
                color: var(--bs-secondary-color, #6c757d);
            }

            .cegg-lightbox {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 3vmin;
                background: rgba(0, 0, 0, .85);
            }

            .cegg-lightbox[hidden] {
                display: none;
            }

            .cegg-lightbox__stage {
                margin: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: .75rem;
                max-width: 92vw;
                max-height: 90vh;
            }

            .cegg-lightbox__img {
                max-width: 92vw;
                max-height: 80vh;
                object-fit: contain;
                border-radius: .375rem;
                box-shadow: 0 10px 40px rgba(0, 0, 0, .5);
            }

            .cegg-lightbox__caption {
                max-width: 60ch;
                color: #fff;
                text-align: center;
                font-size: .9rem;
            }

            .cegg-lightbox__caption[hidden] {
                display: none;
            }

            .cegg-lightbox__close,
            .cegg-lightbox__nav {
                position: absolute;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 0;
                border-radius: 50%;
                color: #fff;
                background: rgba(255, 255, 255, .14);
                cursor: pointer;
                transition: background .2s ease;
            }

            .cegg-lightbox__close:hover,
            .cegg-lightbox__nav:hover {
                background: rgba(255, 255, 255, .3);
            }

            .cegg-lightbox__nav[hidden] {
                display: none;
            }

            .cegg-lightbox__close {
                top: 3vmin;
                right: 3vmin;
                width: 2.75rem;
                height: 2.75rem;
                font-size: 1.7rem;
                line-height: 1;
            }

            .cegg-lightbox__nav {
                top: 50%;
                transform: translateY(-50%);
                width: 3rem;
                height: 3rem;
                font-size: 2rem;
                line-height: 1;
            }

            .cegg-lightbox__prev {
                left: 3vmin;
            }

            .cegg-lightbox__next {
                right: 3vmin;
            }
        </style>
    <?php } ?>
    <?php cegg_images_masonry_css_enqueue();
    ?>
<?php endif; ?>

<script>
    "use strict";
    ( function() {
        function init() {
            const root = document.getElementById( "<?php echo esc_js($gallery_id); ?>" );
            if ( !root || root.dataset.ceggLightbox === "1" ) {
                return;
            }
            root.dataset.ceggLightbox = "1";

            const box = root.querySelector( ".cegg-lightbox" );
            const tiles = Array.prototype.slice.call(
                root.querySelectorAll( ".cegg-masonry__tile" )
            );
            if ( !box || !tiles.length ) {
                return;
            }
            document.body.appendChild( box );

            const items = tiles.map( function( t ) {
                const imgEl = t.querySelector( "img" );
                return {
                    full:
                        t.getAttribute( "data-full" ) ||
                        ( imgEl ? imgEl.currentSrc || imgEl.src : "" ),
                    caption: t.getAttribute( "data-caption" ) || "",
                };
            } );
            const img = box.querySelector( ".cegg-lightbox__img" );
            const cap = box.querySelector( ".cegg-lightbox__caption" );
            const prev = box.querySelector( ".cegg-lightbox__prev" );
            const next = box.querySelector( ".cegg-lightbox__next" );
            const closeBtn = box.querySelector( ".cegg-lightbox__close" );
            // items.length is at least 1 here; "=== 1" (never a less-than) means one.
            const single = items.length === 1;
            let current = 0;

            if ( single ) {
                prev.hidden = true;
                next.hidden = true;
            }

            function show( idx ) {
                current = ( idx + items.length ) % items.length;
                img.src = items[ current ].full;
                const c = items[ current ].caption;
                cap.textContent = c;
                cap.hidden = !c;
            }

            function onKey( e ) {
                if ( e.key === "Escape" ) {
                    close();
                } else if ( !single && e.key === "ArrowLeft" ) {
                    show( current - 1 );
                } else if ( !single && e.key === "ArrowRight" ) {
                    show( current + 1 );
                }
            }

            function open( idx ) {
                show( idx );
                box.hidden = false;
                document.addEventListener( "keydown", onKey );
            }

            function close() {
                box.hidden = true;
                img.src = "";
                document.removeEventListener( "keydown", onKey );
            }

            tiles.forEach( function( t, i ) {
                t.addEventListener( "click", function( e ) {
                    e.preventDefault();
                    open( i );
                } );
            } );
            prev.addEventListener( "click", function() { show( current - 1 ); } );
            next.addEventListener( "click", function() { show( current + 1 ); } );
            closeBtn.addEventListener( "click", close );
            box.addEventListener( "click", function( e ) {
                if ( e.target === box ) {
                    close();
                }
            } );
        }

        if ( document.readyState === "loading" ) {
            document.addEventListener( "DOMContentLoaded", init );
        } else {
            init();
        }
    } )();
</script>
