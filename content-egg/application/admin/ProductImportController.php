<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\admin\import\AbstractTab;
use ContentEgg\application\admin\import\BulkImportTab;
use ContentEgg\application\admin\import\FeedImportTab;
use ContentEgg\application\admin\import\SearchTab;
use ContentEgg\application\admin\import\AutoImportTab;
use ContentEgg\application\admin\import\PresetsTab;
use ContentEgg\application\admin\import\QueueTab;

use function ContentEgg\prnx;;

/**
 * ProductImportController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class ProductImportController
{
    public const SLUG = 'content-egg-product-import';

    /** @var AbstractTab[] */
    private array $tabs = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu()
    {
        $badge = '';

        add_submenu_page(
            Plugin::getSlug(),
            __('Import Tools', 'content-egg'),
            __('Import Tools', 'content-egg') . $badge,
            'manage_options',
            self::SLUG,
            [$this, 'renderPage']
        );
    }

    private function initTabs()
    {
        $this->tabs = [
            new SearchTab(),
            new BulkImportTab(),
            new FeedImportTab(),
            new AutoImportTab(),
            new PresetsTab(),
            new QueueTab(),
        ];
    }

    public function renderPage()
    {
        if (Plugin::isInactiveEnvato())
        {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Product Import', 'content-egg') . '</h1>';
            echo '<div class="notice notice-error"><p>'
                . esc_html__('You need to activate the plugin first to use the Product Import Tool.', 'content-egg')
                . '</p></div>';
            echo '</div>';

            $activate_url = admin_url('admin.php?page=content-egg-lic');
            echo '<p><a href="' . esc_url($activate_url) . '" class="button button-primary">'
                . esc_html__('Activate now', 'content-egg') . '</a></p>';

            echo '</div></div>';
            return;
        }

        $this->initTabs();

        $requested = sanitize_key($_GET['tab'] ?? '');
        $current   = $requested
            && in_array($requested, array_map(fn($t) => $t->getSlug(), $this->tabs), true)
            ? $requested
            : $this->tabs[0]->getSlug();

        ob_start();
        require __DIR__ . '/views/_version_badge.php';
        $version_badge = ob_get_clean();

        echo '<div class="wrap">';
        echo '<div class="cegg5-container">';
        echo '<h1 class="h4 d-flex align-items-center justify-content-between mb-2 mt-2" style="height: 30px;"><span>' . esc_html__('Product Import', 'content-egg') . '</span>';
        echo '<div class="d-flex align-items-center">';
        echo ' <a href="https://ce-docs.keywordrush.com/set-up-products/import-tools" target="_blank" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center me-4">' . esc_html__('Help', 'content-egg') . '</a>';
        echo wp_kses_post($version_badge);
        echo '</div>';
        echo '</h1>';
        echo '</div>';
        echo '<h2 class="nav-tab-wrapper">';

        foreach ($this->tabs as $tab)
        {
            $is_active = $tab->getSlug() === $current ? ' nav-tab-active' : '';
            $url       = add_query_arg(
                ['page' => self::SLUG, 'tab' => $tab->getSlug()],
                admin_url('admin.php')
            );

            printf(
                '<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
                esc_url($url),
                esc_attr($is_active),
                esc_html($tab->getTitle())
            );
        }

        echo '</h2>';

        // Render the active tab
        foreach ($this->tabs as $tab)
        {
            if ($tab->getSlug() === $current)
            {
                $tab->enqueueAssets();
                $tab->render();
                break;
            }
        }

        echo '</div>';
    }
}
