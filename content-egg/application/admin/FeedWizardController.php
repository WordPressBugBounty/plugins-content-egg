<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\admin\PluginAdmin;
use ContentEgg\application\components\ai\ModulePrompt;
use ContentEgg\application\components\feed\FeedDetector;
use ContentEgg\application\components\ModuleConfig;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ModuleName;
use ContentEgg\application\modules\Feed\FeedModule;
use ContentEgg\application\Plugin;

/**
 * FeedWizardController class file
 *
 * Setup wizard for generic Feed modules: paste a URL, auto-detect all feed
 * parameters, map fields against live sample data (heuristics + optional AI),
 * then activate and import in the background with progress.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedWizardController
{
    const NONCE = 'cegg-feed-wizard';

    /** Same exclusions as FeedModule::aiAutomap(). */
    const AI_EXCLUDED_FIELDS = array('product node', 'attributes', 'short description', 'isbn', 'subtitle');

    /** Clarifying hints for mapping fields whose expected value isn't obvious from the label alone. */
    private static function mappingHints(): array
    {
        return array(
            'id' => __('Required. Unique identifier for each product. Must remain consistent across imports.', 'content-egg'),
            'affiliate link' => __("The product's affiliate URL, including your tracking parameters.", 'content-egg'),
            'is in stock' => __('Stock status. Supported values: 1, true, on, yes, 0, false, off, no', 'content-egg'),
            'availability' => __('Text-based stock status. Supported values: in stock, out of stock', 'content-egg'),
            'direct link' => __('Direct (non-affiliate) URL to the original product page.', 'content-egg'),
            'gtin' => __('Global Trade Item Number, such as EAN-13 (e.g., 3001234567892).', 'content-egg'),
        );
    }

    public function __construct()
    {
        \add_action('wp_ajax_cegg_feed_wizard_analyze', array($this, 'ajaxAnalyze'));
        \add_action('wp_ajax_cegg_feed_wizard_ai_map', array($this, 'ajaxAiMap'));
        \add_action('wp_ajax_cegg_feed_wizard_save', array($this, 'ajaxSave'));
        \add_action('wp_ajax_cegg_feed_wizard_status', array($this, 'ajaxStatus'));
    }

    // ------------------------------------------------------------------
    // Wizard page rendering (called from ModuleConfig::settings_page)
    // ------------------------------------------------------------------

    /**
     * Render the wizard instead of the settings form when requested and
     * applicable (generic Feed slot that is not active yet).
     */
    public static function maybeRenderWizard(ModuleConfig $config): bool
    {
        if (empty($_GET['wizard']))
        {
            return false;
        }

        $module = $config->getModuleInstance();

        if (!$module instanceof FeedModule || $module->isActive())
        {
            return false;
        }

        // A previous visit may have analyzed a ZIP feed and then abandoned the
        // wizard; bound how long that prefetched archive lingers on disk.
        $module->clearStalePrefetchedArchive();

        \wp_enqueue_style('cegg-bootstrap-icons', PluginAdmin::res('/admin/bootstrap/css/bootstrap-icons.min.css'), [], Plugin::version());
        \wp_enqueue_style('cegg-feed-wizard', PluginAdmin::res('/admin/css/feed-wizard.css'), ['cegg-bootstrap5-full'], Plugin::version());
        \wp_enqueue_script('cegg-feed-wizard', \ContentEgg\PLUGIN_RES . '/admin/js/feed-wizard.js', array(), Plugin::version(), true);

        $hints = self::mappingHints();
        $mapping_fields = array();
        foreach ($config->mappingFields() as $field => $required)
        {
            if ($field === 'product node')
            {
                continue; // handled internally from detection
            }
            $label = preg_replace('/\p{Cf}/u', '', $field);
            $mapping_fields[] = array(
                'key' => $field,
                'label' => $label,
                'required' => (bool) $required,
                'hint' => isset($hints[$label]) ? $hints[$label] : '',
            );
        }

        \wp_localize_script('cegg-feed-wizard', 'ceggFeedWizard', array(
            'nonce' => \wp_create_nonce(self::NONCE),
            'module' => $module->getId(),
            'mappingFields' => $mapping_fields,
            'hasAiKey' => (bool) GeneralConfig::getOption('system_ai_key', '', 'contentegg_options'),
            'settingsUrl' => \admin_url('admin.php?page=' . $config->page_slug()),
            'newPostUrl' => \admin_url('post-new.php'),
            'i18n' => array(
                'subtitleStep1' => __("Paste a product feed URL below — the plugin will try to detect the format, fields and currency automatically.", 'content-egg'),
                'subtitleStep2' => __('Confirm how each feed column maps to a product field — check the sample data and live preview on the right to make sure it looks right.', 'content-egg'),
                'subtitleStep3' => __('Give your feed a name and choose how often it should refresh, then start the import.', 'content-egg'),
                'analyzing' => __('Analyzing feed…', 'content-egg'),
                'analyzeFailed' => __('Analysis failed', 'content-egg'),
                'rescanning' => __('Re-scanning…', 'content-egg'),
                'aiMapping' => __('Asking AI…', 'content-egg'),
                'notMapped' => __('— not mapped —', 'content-egg'),
                'custom' => __('Custom…', 'content-egg'),
                'required' => __('required', 'content-egg'),
                'saving' => __('Saving…', 'content-egg'),
                'importing' => __('Importing products…', 'content-egg'),
                'rowsProcessed' => __('processed', 'content-egg'),
                'rowsInserted' => __('inserted', 'content-egg'),
                'rowsSkipped' => __('skipped', 'content-egg'),
                'approxLabel' => __('(estimated)', 'content-egg'),
                'importDone' => __('products imported. Your feed is ready!', 'content-egg'),
                'importFailed' => __('Import failed:', 'content-egg'),
                'mapRequired' => __('Please map all required fields to continue.', 'content-egg'),
                'aiKeyMissing' => __('Set the OpenAI API key under Settings → AI to enable', 'content-egg'),
            ),
        ));

        PluginAdmin::render('feed_wizard', array('module' => $module, 'config' => $config));

        return true;
    }

    // ------------------------------------------------------------------
    // Business logic (public, CLI-testable)
    // ------------------------------------------------------------------

    /**
     * @param string|null $product_node Optional XML product-node override for a
     *   "re-scan" when auto-detection picked the wrong node.
     * @throws \Exception
     */
    public function analyze(string $module_id, string $url, ?string $product_node = null): array
    {
        $module = $this->feedModule($module_id);
        $config = $module->getConfigInstance();

        if (!$config->validateFeedUrl($url))
        {
            throw new \Exception(esc_html__('Please enter a valid feed URL. Supported schemes: http://, https://, ftp://, ftps://.', 'content-egg'));
        }

        // wp_remote_get() can't fetch ftp:// URLs; hand FTP/FTPS transfers to
        // the module's own FTP client (cURL → ext-ftp → stream wrapper), which
        // downloads the whole file once so the real import can reuse it.
        $onFtpFetch = function (string $ftp_url) use ($module)
        {
            return $module->downloadViaFtp($ftp_url, $module->importTimeLimit());
        };

        $result = FeedDetector::analyze(
            $url,
            array_keys($config->mappingFields()),
            array($module, 'storePrefetchedArchive'),
            $onFtpFetch,
            $product_node
        );

        $result['has_ai_key'] = (bool) GeneralConfig::getOption('system_ai_key', '', 'contentegg_options');
        $result['url'] = $url;

        return $result;
    }

    /**
     * AI mapping suggestion for one sample record.
     *
     * @param string       $format csv|json|xml
     * @param array|string $sample assoc record (csv/json) or raw node XML (xml)
     * @throws \Exception
     */
    public function aiMap(string $module_id, string $format, $sample): array
    {
        $module = $this->feedModule($module_id);
        $config = $module->getConfigInstance();

        $api_key = GeneralConfig::getOption('system_ai_key', '', 'contentegg_options');
        if (!$api_key)
        {
            throw new \Exception(esc_html__('OpenAI API key is not configured. Please add it under Content Egg → Settings → AI.', 'content-egg'));
        }

        $original_fields = array_keys($config->mappingFields());

        $clean_names = array();
        foreach ($original_fields as $field)
        {
            if (in_array($field, self::AI_EXCLUDED_FIELDS, true))
            {
                continue;
            }
            $clean = preg_replace('/\p{Cf}/u', '', $field);
            $clean_names[$clean] = $field;
        }

        $prompt = new ModulePrompt($api_key);

        switch ($format)
        {
            case 'csv':
                $suggestions = $prompt->suggestFieldsMappingCsv((array) $sample, array_keys($clean_names));
                break;
            case 'json':
                $suggestions = $prompt->suggestFieldsMappingJson((array) $sample, array_keys($clean_names));
                break;
            case 'xml':
                $suggestions = $prompt->suggestFieldsMappingXml((string) $sample, array_keys($clean_names));
                break;
            default:
                throw new \Exception('Unsupported format.');
        }

        // Key the result by the ORIGINAL mapping field names.
        $mapping = array();
        foreach ($suggestions as $clean => $feed_field)
        {
            if (!isset($clean_names[$clean]) || !is_string($feed_field))
            {
                continue;
            }
            if ($feed_field === '' || strtolower($feed_field) === 'unknown')
            {
                continue;
            }
            $mapping[$clean_names[$clean]] = $feed_field;
        }

        return $mapping;
    }

    /**
     * Persist wizard settings into the module slot, activate it and schedule
     * the first import. Returns urls for the completion screen.
     *
     * @throws \Exception
     */
    public function save(string $module_id, array $settings): array
    {
        $module = $this->feedModule($module_id);

        if ($module->isActive())
        {
            throw new \Exception(esc_html__('This feed module is already configured. Use its settings page instead.', 'content-egg'));
        }

        $config = $module->getConfigInstance();

        $feed_url = isset($settings['feed_url']) ? trim((string) $settings['feed_url']) : '';
        if (!$config->validateFeedUrl($feed_url))
        {
            throw new \Exception(esc_html__('Please enter a valid feed URL.', 'content-egg'));
        }

        $mapping = isset($settings['mapping']) && is_array($settings['mapping']) ? $settings['mapping'] : array();
        $mapping = $config->mappingSanitize($mapping);

        if (!empty($settings['product_node']))
        {
            $mapping['product node'] = sanitize_text_field((string) $settings['product_node']);
        }

        if (!$config->isAllRequiredFieldsFilled($mapping))
        {
            throw new \Exception(sprintf(
                esc_html__('Please map the required fields: %s.', 'content-egg'),
                esc_html(implode(', ', array_map(static function ($field)
                {
                    return preg_replace('/\p{Cf}/u', '', $field);
                }, $config->missingRequired($mapping))))
            ));
        }

        $pick = static function ($key, $allowed, $default) use ($settings)
        {
            $value = isset($settings[$key]) ? (string) $settings[$key] : '';

            return in_array($value, $allowed, true) ? $value : $default;
        };

        $feed_name = sanitize_text_field(isset($settings['feed_name']) ? (string) $settings['feed_name'] : '');
        if ($feed_name === '')
        {
            $feed_name = 'Feed';
        }
        $feed_name = $this->uniqueFeedName($feed_name, $module_id);

        $options = array(
            'is_active' => 1,
            'feed_name' => $feed_name,
            'feed_url' => $feed_url,
            'feed_format' => $pick('feed_format', array('csv', 'xml', 'json'), 'csv'),
            'xml_processor' => $pick('xml_processor', array('XmlStringStreamer', 'XmlReader'), 'XmlStringStreamer'),
            'archive_format' => $pick('archive_format', array('none', 'zip', 'gz'), 'none'),
            'encoding' => $pick('encoding', array('UTF-8', 'ISO-8859-1'), 'UTF-8'),
            'currency' => strtoupper(sanitize_text_field(isset($settings['currency']) ? (string) $settings['currency'] : 'USD')),
            'domain' => $config->sanitizeDomain(isset($settings['domain']) ? (string) $settings['domain'] : ''),
            'csv_delimiter' => $pick('csv_delimiter', array('auto', 'tab', ';', ',', '|'), 'auto'),
            'csv_enclosure' => $pick('csv_enclosure', array('auto', '"', "'", 'none'), 'auto'),
            'price_decimal_separator' => $pick('price_decimal_separator', array('auto', '.', ','), 'auto'),
            'sync_interval' => $pick('sync_interval', array('3600.', '10800.', '21600.', '43200.', '86400.', '259200.', '604800.'), '43200.'),
            'in_stock' => !empty($settings['in_stock']) ? 1 : 0,
            // Mapping was confirmed in the wizard; no import-time AI needed.
            'auto_mapping' => 'disabled',
            'mapping' => $mapping,
        );

        if ($options['domain'] === '')
        {
            throw new \Exception(esc_html__('Please provide the merchant domain.', 'content-egg'));
        }

        \update_option($config->option_name(), $options);
        ModuleName::getInstance()->saveName($module_id, $feed_name);

        // Schedule the first import right away.
        $module->requestForceRefresh();
        $module->refreshFeedData(true);

        return array(
            'feed_name' => $feed_name,
            'settings_url' => \admin_url('admin.php?page=' . $config->page_slug()),
        );
    }

    public function status(string $module_id): array
    {
        $module = $this->feedModule($module_id);

        return array(
            'status' => $module->importStatus()->get(),
            'products' => (int) $module->getProductCount(),
            'in_progress' => $module->isImportInProgress(),
            'scheduled' => $module->isImportScheduled(),
            'last_error' => (string) $module->getLastImportError(),
            'last_notice' => (string) $module->getLastImportNotice(),
        );
    }

    // ------------------------------------------------------------------
    // AJAX wrappers
    // ------------------------------------------------------------------

    public function ajaxAnalyze(): void
    {
        $this->guard();

        try
        {
            $url = isset($_POST['url']) ? trim(sanitize_url(wp_unslash($_POST['url']))) : '';
            $module_id = $this->requestedModuleId();
            $product_node = isset($_POST['product_node']) && $_POST['product_node'] !== ''
                ? sanitize_text_field(wp_unslash($_POST['product_node']))
                : null;
            \wp_send_json(array('ok' => 1, 'data' => $this->analyze($module_id, $url, $product_node)));
        }
        catch (\Throwable $e)
        {
            \wp_send_json(array('error' => $e->getMessage()));
        }
    }

    public function ajaxAiMap(): void
    {
        $this->guard();

        try
        {
            $module_id = $this->requestedModuleId();
            $format = isset($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : '';
            $sample_raw = isset($_POST['sample']) ? wp_unslash($_POST['sample']) : ''; // phpcs:ignore

            $sample = $format === 'xml' ? (string) $sample_raw : json_decode((string) $sample_raw, true);
            if ($format !== 'xml' && !is_array($sample))
            {
                throw new \Exception('Invalid sample data.');
            }

            \wp_send_json(array('ok' => 1, 'mapping' => $this->aiMap($module_id, $format, $sample)));
        }
        catch (\Throwable $e)
        {
            \wp_send_json(array('error' => $e->getMessage()));
        }
    }

    public function ajaxSave(): void
    {
        $this->guard();

        try
        {
            $module_id = $this->requestedModuleId();
            $settings_raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : ''; // phpcs:ignore
            $settings = json_decode((string) $settings_raw, true);

            if (!is_array($settings))
            {
                throw new \Exception('Invalid settings payload.');
            }

            \wp_send_json(array('ok' => 1) + $this->save($module_id, $settings));
        }
        catch (\Throwable $e)
        {
            \wp_send_json(array('error' => $e->getMessage()));
        }
    }

    public function ajaxStatus(): void
    {
        $this->guard();

        try
        {
            \wp_send_json(array('ok' => 1) + $this->status($this->requestedModuleId()));
        }
        catch (\Throwable $e)
        {
            \wp_send_json(array('error' => $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function guard(): void
    {
        if (!\current_user_can('manage_options'))
        {
            \wp_send_json(array('error' => 'Access denied.'), 403);
        }

        if (!\check_ajax_referer(self::NONCE, '_wizard_nonce', false))
        {
            \wp_send_json(array('error' => __('Your session has expired. Please reload the page and try again.', 'content-egg')));
        }
    }

    private function requestedModuleId(): string
    {
        $module_id = isset($_POST['module']) ? \ContentEgg\application\helpers\TextHelper::clearId(sanitize_text_field(wp_unslash($_POST['module']))) : '';

        if ($module_id === '')
        {
            throw new \Exception('Module is undefined.');
        }

        return $module_id;
    }

    /** @throws \Exception */
    private function feedModule(string $module_id): FeedModule
    {
        $module = ModuleManager::factory($module_id);

        if (!$module instanceof FeedModule)
        {
            throw new \Exception('Not a feed module.');
        }

        return $module;
    }

    /** Ensure the feed name is unique across configured feed modules. */
    private function uniqueFeedName(string $name, string $module_id): string
    {
        $names = \get_option(ModuleName::OPTION_NAME, array());
        if (!is_array($names))
        {
            $names = array();
        }
        unset($names[$module_id]);

        $candidate = $name;
        $suffix = 2;
        while (in_array($candidate, $names, true))
        {
            $candidate = $name . ' ' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
