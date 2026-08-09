<?php

/*
 * Name: Videos grid
 * Module Types: VIDEO
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// Grid layout for VIDEO modules: a responsive grid of poster thumbnails with a
// play button; clicking one opens a full-screen lightbox player. The embed is
// built in JS with createElement (YouTube <iframe> or HTML5 <video>) so the
// inline script contains no literal "<" — a bare "<" would break WordPress
// <script>-tag detection in wptexturize and mangle later "&&" into an entity.
//   extra.guid      → YouTube
//   extra.video_url → HTML5 (mp4) with img poster

$grid_id = TemplateHelper::generateGlobalId('cegg_vgrid_');

?>

<div id="<?php echo esc_attr($grid_id); ?>" class="container px-0 mb-5 mt-1 cegg-vgrid-wrap" <?php $this->colorMode(); ?>>
    <div class="cegg-vgrid">
        <?php foreach ($items as $i => $item): ?>
            <?php $this->setItem($item, $i); ?>
            <?php
            $is_yt = !empty($item['extra']['guid']);
            $is_html5 = !$is_yt && !empty($item['extra']['video_url']);
            if (!$is_yt && !$is_html5)
                continue;
            $title = ($this->isVisible('title') && !empty($item['title'])) ? $item['title'] : '';
            ?>
            <figure class="cegg-vgrid__item">
                <button
                    type="button"
                    class="cegg-vgrid__tile"
                    data-embed="<?php echo $is_yt ? 'yt' : 'html5'; ?>"
                    data-guid="<?php echo esc_attr($is_yt ? $item['extra']['guid'] : ''); ?>"
                    data-src="<?php echo esc_url($is_html5 ? $item['extra']['video_url'] : ''); ?>"
                    aria-label="<?php echo esc_attr($title ?: TemplateHelper::__('Play video')); ?>"
                >
                    <span class="cegg-vgrid__thumb">
                        <?php if (!empty($item['img'])): ?>
                            <img src="<?php echo esc_url($item['img']); ?>" alt="" loading="lazy" />
                        <?php endif; ?>
                        <span class="cegg-vgrid__play" aria-hidden="true">
                            <svg viewBox="0 0 68 48"><path class="cegg-vgrid__play-bg" d="M66.5 7.7a8.6 8.6 0 0 0-6-6C55.2.3 34 .3 34 .3S12.8.3 7.5 1.7a8.6 8.6 0 0 0-6 6A90 90 0 0 0 0 24a90 90 0 0 0 1.5 16.3 8.6 8.6 0 0 0 6 6C12.8 47.7 34 47.7 34 47.7s21.2 0 26.5-1.4a8.6 8.6 0 0 0 6-6A90 90 0 0 0 68 24a90 90 0 0 0-1.5-16.3z" /><path d="M45 24 27 14v20z" fill="#fff" /></svg>
                        </span>
                    </span>
                </button>
                <?php if ($title): ?>
                    <figcaption class="cegg-vgrid__title small lh-sm"><?php echo esc_html($title); ?></figcaption>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>

    <div class="cegg-vlb" role="dialog" aria-modal="true" hidden>
        <button type="button" class="cegg-vlb__close" aria-label="<?php echo esc_attr(TemplateHelper::__('Close')); ?>">&times;</button>
        <div class="cegg-vlb__stage"></div>
    </div>
</div>

<?php if (!function_exists('cegg_videos_grid_css_enqueue')): ?>
    <?php function cegg_videos_grid_css_enqueue()
    { ?>
        <style>
            .cegg-vgrid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.25rem;
            }

            .cegg-vgrid__item {
                margin: 0;
            }

            .cegg-vgrid__tile {
                display: block;
                width: 100%;
                padding: 0;
                border: 0;
                background: none;
                cursor: pointer;
            }

            .cegg-vgrid__thumb {
                position: relative;
                display: block;
                aspect-ratio: 16 / 9;
                border-radius: .5rem;
                overflow: hidden;
                background: #000;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .14);
            }

            .cegg-vgrid__thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: transform .35s ease, opacity .25s ease;
            }

            .cegg-vgrid__tile:hover .cegg-vgrid__thumb img {
                transform: scale(1.04);
                opacity: .85;
            }

            .cegg-vgrid__play {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 68px;
                height: 48px;
            }

            .cegg-vgrid__play svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 1px 4px rgba(0, 0, 0, .4));
            }

            .cegg-vgrid__play-bg {
                fill: #212121;
                fill-opacity: .8;
                transition: fill-opacity .2s ease;
            }

            .cegg-vgrid__tile:hover .cegg-vgrid__play-bg {
                fill: #f00;
                fill-opacity: 1;
            }

            .cegg-vgrid__title {
                margin-top: .5rem;
                font-weight: 500;
            }

            .cegg-vlb {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 3vmin;
                background: rgba(0, 0, 0, .88);
            }

            .cegg-vlb[hidden] {
                display: none;
            }

            .cegg-vlb__stage {
                width: min(92vw, 1200px);
                aspect-ratio: 16 / 9;
                background: #000;
                border-radius: .5rem;
                overflow: hidden;
                box-shadow: 0 10px 40px rgba(0, 0, 0, .5);
            }

            .cegg-vlb__stage iframe,
            .cegg-vlb__stage video {
                width: 100%;
                height: 100%;
                border: 0;
                display: block;
            }

            .cegg-vlb__close {
                position: absolute;
                top: 3vmin;
                right: 3vmin;
                width: 2.75rem;
                height: 2.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 0;
                border-radius: 50%;
                color: #fff;
                background: rgba(255, 255, 255, .14);
                font-size: 1.7rem;
                line-height: 1;
                cursor: pointer;
                transition: background .2s ease;
            }

            .cegg-vlb__close:hover {
                background: rgba(255, 255, 255, .3);
            }
        </style>
    <?php } ?>
    <?php cegg_videos_grid_css_enqueue();
    ?>
<?php endif; ?>

<script>
    "use strict";
    ( function() {
        function init() {
            const root = document.getElementById( "<?php echo esc_js($grid_id); ?>" );
            if ( !root || root.dataset.ceggVgrid === "1" ) {
                return;
            }
            root.dataset.ceggVgrid = "1";

            const box = root.querySelector( ".cegg-vlb" );
            const stage = box ? box.querySelector( ".cegg-vlb__stage" ) : null;
            const closeBtn = box ? box.querySelector( ".cegg-vlb__close" ) : null;
            const tiles = Array.prototype.slice.call(
                root.querySelectorAll( ".cegg-vgrid__tile" )
            );
            if ( !box || !stage || !tiles.length ) {
                return;
            }
            document.body.appendChild( box );

            function clearStage() {
                while ( stage.firstChild ) {
                    stage.removeChild( stage.firstChild );
                }
            }

            // Build the player node with createElement only (no HTML string, so no
            // stray angle bracket that wptexturize could choke on).
            function buildPlayer( type, guid, src ) {
                if ( type === "yt" ) {
                    const f = document.createElement( "iframe" );
                    f.src =
                        "https://www.youtube.com/embed/" +
                        encodeURIComponent( guid ) +
                        "?rel=0&autoplay=1";
                    f.setAttribute( "frameborder", "0" );
                    f.setAttribute(
                        "allow",
                        "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                    );
                    f.allowFullscreen = true;
                    return f;
                }
                const v = document.createElement( "video" );
                v.controls = true;
                v.autoplay = true;
                v.setAttribute( "playsinline", "" );
                const s = document.createElement( "source" );
                s.src = src;
                s.type = "video/mp4";
                v.appendChild( s );
                return v;
            }

            function open( tile ) {
                clearStage();
                stage.appendChild(
                    buildPlayer(
                        tile.getAttribute( "data-embed" ),
                        tile.getAttribute( "data-guid" ),
                        tile.getAttribute( "data-src" )
                    )
                );
                box.hidden = false;
                document.addEventListener( "keydown", onKey );
            }

            function close() {
                box.hidden = true;
                clearStage();
                document.removeEventListener( "keydown", onKey );
            }

            function onKey( e ) {
                if ( e.key === "Escape" ) {
                    close();
                }
            }

            tiles.forEach( function( t ) {
                t.addEventListener( "click", function( e ) {
                    e.preventDefault();
                    open( t );
                } );
            } );
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
