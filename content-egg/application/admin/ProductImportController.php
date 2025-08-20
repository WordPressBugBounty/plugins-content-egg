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

use function ContentEgg\prnx;

/**
 * ProductImportController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
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

        if (Plugin::isFree() && time() < strtotime('2025-09-30 23:59:59'))
        {
            $badge = ' <span class="update-plugins count-1"><span class="plugin-count">New</span></span>';
        }

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
        $this->initTabs();

        $requested = sanitize_key($_GET['tab'] ?? '');
        $current   = $requested
            && in_array($requested, array_map(fn($t) => $t->getSlug(), $this->tabs), true)
            ? $requested
            : $this->tabs[0]->getSlug();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Product Import', 'content-egg');

        echo ' <a href="https://ce-docs.keywordrush.com/set-up-products/import-tools" target="_blank" class="button button-secondary" style="margin-left: 15px;">' . esc_html__('User Guide', 'content-egg') . '</a>';

        echo '</h1>';
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
                $is_active,
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
