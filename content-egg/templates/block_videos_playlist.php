<?php

/*
 * Name: Videos playlist
 * Module Types: VIDEO
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// Playlist layout for VIDEO modules: one main player plus a clickable list of the
// other clips (YouTube-playlist feel). Selecting a clip loads it into the main
// stage. The embed is built with createElement (YouTube <iframe> / HTML5 <video>)
// so the inline script has no literal "<" — a bare "<" would break WordPress
// <script>-tag detection in wptexturize and mangle later "&&" into an entity.
//   extra.guid      → YouTube
//   extra.video_url → HTML5 (mp4) with img poster

$list_id = TemplateHelper::generateGlobalId('cegg_vpl_');

// Collect playable clips first so we can seed the main stage with the first one.
$clips = array();
foreach ($items as $i => $item)
{
    if (!empty($item['extra']['guid']))
        $type = 'yt';
    elseif (!empty($item['extra']['video_url']))
        $type = 'html5';
    else
        continue;

    $this->setItem($item, $i);
    $clips[] = array(
        'type'  => $type,
        'guid'  => $type === 'yt' ? $item['extra']['guid'] : '',
        'src'   => $type === 'html5' ? $item['extra']['video_url'] : '',
        'img'   => !empty($item['img']) ? $item['img'] : '',
        'title' => ($this->isVisible('title') && !empty($item['title'])) ? $item['title'] : '',
    );
}

if (!$clips)
    return;

$first = $clips[0];

?>

<div id="<?php echo esc_attr($list_id); ?>" class="container px-0 mb-5 mt-1 cegg-vplaylist" <?php $this->colorMode(); ?>>
    <div class="cegg-vplaylist__main">
        <div class="cegg-vplaylist__stage">
            <button type="button" class="cegg-vplaylist__facade" data-index="0" aria-label="<?php echo esc_attr($first['title'] ?: TemplateHelper::__('Play video')); ?>">
                <?php if ($first['img']): ?>
                    <img src="<?php echo esc_url($first['img']); ?>" alt="" />
                <?php endif; ?>
                <span class="cegg-vplaylist__play" aria-hidden="true">
                    <svg viewBox="0 0 68 48"><path class="cegg-vplaylist__play-bg" d="M66.5 7.7a8.6 8.6 0 0 0-6-6C55.2.3 34 .3 34 .3S12.8.3 7.5 1.7a8.6 8.6 0 0 0-6 6A90 90 0 0 0 0 24a90 90 0 0 0 1.5 16.3 8.6 8.6 0 0 0 6 6C12.8 47.7 34 47.7 34 47.7s21.2 0 26.5-1.4a8.6 8.6 0 0 0 6-6A90 90 0 0 0 68 24a90 90 0 0 0-1.5-16.3z" /><path d="M45 24 27 14v20z" fill="#fff" /></svg>
                </span>
            </button>
        </div>
        <?php if ($first['title']): ?>
            <div class="cegg-vplaylist__now"><?php echo esc_html($first['title']); ?></div>
        <?php endif; ?>
    </div>

    <ol class="cegg-vplaylist__list">
        <?php foreach ($clips as $i => $clip): ?>
            <li>
                <button
                    type="button"
                    class="cegg-vplaylist__item<?php echo $i === 0 ? ' is-active' : ''; ?>"
                    data-index="<?php echo (int) $i; ?>"
                    data-embed="<?php echo esc_attr($clip['type']); ?>"
                    data-guid="<?php echo esc_attr($clip['guid']); ?>"
                    data-src="<?php echo esc_url($clip['src']); ?>"
                    data-title="<?php echo esc_attr($clip['title']); ?>"
                >
                    <span class="cegg-vplaylist__item-thumb">
                        <?php if ($clip['img']): ?>
                            <img src="<?php echo esc_url($clip['img']); ?>" alt="" loading="lazy" />
                        <?php endif; ?>
                        <span class="cegg-vplaylist__item-badge" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                        </span>
                    </span>
                    <?php if ($clip['title']): ?>
                        <span class="cegg-vplaylist__item-title"><?php echo esc_html($clip['title']); ?></span>
                    <?php endif; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<?php if (!function_exists('cegg_videos_playlist_css_enqueue')): ?>
    <?php function cegg_videos_playlist_css_enqueue()
    { ?>
        <style>
            .cegg-vplaylist {
                display: grid;
                gap: 1.25rem;
                grid-template-columns: 1fr;
            }

            @media (min-width: 768px) {
                .cegg-vplaylist {
                    grid-template-columns: minmax(0, 2.4fr) minmax(240px, 1fr);
                    align-items: start;
                }
            }

            .cegg-vplaylist__main {
                min-width: 0;
            }

            .cegg-vplaylist__stage {
                position: relative;
                aspect-ratio: 16 / 9;
                border-radius: .5rem;
                overflow: hidden;
                background: #000;
                box-shadow: 0 6px 22px rgba(0, 0, 0, .16);
            }

            .cegg-vplaylist__stage iframe,
            .cegg-vplaylist__stage video {
                width: 100%;
                height: 100%;
                border: 0;
                display: block;
            }

            .cegg-vplaylist__facade {
                position: absolute;
                inset: 0;
                width: 100%;
                padding: 0;
                border: 0;
                background: none;
                cursor: pointer;
            }

            .cegg-vplaylist__facade img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: opacity .25s ease;
            }

            .cegg-vplaylist__facade:hover img {
                opacity: .85;
            }

            .cegg-vplaylist__play {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 68px;
                height: 48px;
            }

            .cegg-vplaylist__play svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 1px 4px rgba(0, 0, 0, .4));
            }

            .cegg-vplaylist__play-bg {
                fill: #212121;
                fill-opacity: .8;
                transition: fill-opacity .2s ease;
            }

            .cegg-vplaylist__facade:hover .cegg-vplaylist__play-bg {
                fill: #f00;
                fill-opacity: 1;
            }

            .cegg-vplaylist__now {
                margin-top: .65rem;
                font-weight: 600;
                font-size: 1.05rem;
                line-height: 1.35;
            }

            /* Scope with the block root so padding:0 outweighs cegg-bootstrap's
               `.cegg5-container ol` (0,1,1), which otherwise forces padding-left:2rem. */
            .cegg-vplaylist .cegg-vplaylist__list {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: .4rem;
                max-height: 60vh;
                overflow-y: auto;
            }

            .cegg-vplaylist__item {
                display: flex;
                gap: .6rem;
                align-items: center;
                width: 100%;
                text-align: left;
                padding: .35rem;
                border: 0;
                border-radius: .45rem;
                background: none;
                /* Buttons inherit a theme's (often white) button text color; force the
                   surrounding readable text color so clip titles stay legible. */
                color: inherit;
                cursor: pointer;
                transition: background .15s ease;
            }

            .cegg-vplaylist__item:hover,
            .cegg-vplaylist__item.is-active {
                background: var(--bs-tertiary-bg, #f1f2f4);
            }

            .cegg-vplaylist__item-thumb {
                position: relative;
                flex: 0 0 auto;
                width: 116px;
                aspect-ratio: 16 / 9;
                border-radius: .35rem;
                overflow: hidden;
                background: #000;
            }

            .cegg-vplaylist__item-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .cegg-vplaylist__item-badge {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 1.5rem;
                height: 1.5rem;
                color: #fff;
                opacity: 0;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .5));
                transition: opacity .15s ease;
            }

            .cegg-vplaylist__item:hover .cegg-vplaylist__item-badge,
            .cegg-vplaylist__item.is-active .cegg-vplaylist__item-badge {
                opacity: 1;
            }

            .cegg-vplaylist__item-title {
                min-width: 0;
                font-size: .875rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .cegg-vplaylist__item.is-active .cegg-vplaylist__item-title {
                font-weight: 600;
            }
        </style>
    <?php } ?>
    <?php cegg_videos_playlist_css_enqueue();
    ?>
<?php endif; ?>

<script>
    "use strict";
    ( function() {
        function init() {
            const root = document.getElementById( "<?php echo esc_js($list_id); ?>" );
            if ( !root || root.dataset.ceggVpl === "1" ) {
                return;
            }
            root.dataset.ceggVpl = "1";

            const stage = root.querySelector( ".cegg-vplaylist__stage" );
            const now = root.querySelector( ".cegg-vplaylist__now" );
            const items = Array.prototype.slice.call(
                root.querySelectorAll( ".cegg-vplaylist__item" )
            );
            if ( !stage || !items.length ) {
                return;
            }

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

            function play( btn ) {
                while ( stage.firstChild ) {
                    stage.removeChild( stage.firstChild );
                }
                stage.appendChild(
                    buildPlayer(
                        btn.getAttribute( "data-embed" ),
                        btn.getAttribute( "data-guid" ),
                        btn.getAttribute( "data-src" )
                    )
                );
                if ( now ) {
                    now.textContent = btn.getAttribute( "data-title" ) || "";
                }
                items.forEach( function( it ) {
                    it.classList.toggle( "is-active", it === btn );
                } );
            }

            const facade = root.querySelector( ".cegg-vplaylist__facade" );
            if ( facade ) {
                facade.addEventListener( "click", function( e ) {
                    e.preventDefault();
                    play( items[ 0 ] );
                } );
            }

            items.forEach( function( btn ) {
                btn.addEventListener( "click", function( e ) {
                    e.preventDefault();
                    play( btn );
                } );
            } );
        }

        if ( document.readyState === "loading" ) {
            document.addEventListener( "DOMContentLoaded", init );
        } else {
            init();
        }
    } )();
</script>
