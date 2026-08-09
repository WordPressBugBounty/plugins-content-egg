<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\feed\FeedImportPendingException;

/**
 * AbilitiesRegistrar class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Single registration point for the agent abilities layer (design spec §4).
 * Feature-gated on the core Abilities API (WP 6.9+) and the Agent Access
 * master switch. Free/Pro tier policy lives in abilityMap(), nowhere else.
 */
final class AbilitiesRegistrar
{
    const VERSION = '1.0.0';
    const OPT_ENABLED = 'cegg_agent_access_enabled';
    const CATEGORY = 'content-egg';

    public static function initAction(): void
    {
        if (!self::isSupported() || !self::isEnabled())
        {
            return;
        }

        \add_action('wp_abilities_api_categories_init', array(self::class, 'registerCategory'));
        \add_action('wp_abilities_api_init', array(self::class, 'registerAbilities'));
        OpenApiRestController::initAction();
        AgentGuideRestController::initAction();
        AbilityProxyRestController::initAction();
        McpBridge::initAction();
        CacheGuard::initAction();
    }

    public static function isSupported(): bool
    {
        return function_exists('wp_register_ability');
    }

    public static function isEnabled(): bool
    {
        // Opt-in by default: the agent layer stays dormant until an admin enables
        // it on the AI Agents screen. This mirrors how comparable surfaces gate
        // access in the wider ecosystem — WordPress' own Abilities API does NOT
        // expose abilities to MCP by default (meta.mcp.public is an explicit
        // per-ability opt-in), and WooCommerce's REST API does nothing until an
        // administrator explicitly generates scoped API keys. Since this layer marks
        // every ability mcp.public, this master switch is that single deliberate
        // opt-in gate, so it must default off.
        return \get_option(self::OPT_ENABLED, '0') === '1';
    }

    /**
     * Ability class => tier ('free'|'pro'). Pro abilities are simply not
     * registered on free builds; get-status reports the build so agents
     * understand why.
     */
    public static function abilityMap(): array
    {
        return array(
            GetStatusAbility::class => 'free',
            GetGuideAbility::class => 'free',
            ListModulesAbility::class => 'free',
            GetModuleSettingsAbility::class => 'free',
            GetSettingsAbility::class => 'free',
            SearchProductsAbility::class => 'free',
            SearchAllProductsAbility::class => 'free',
            SearchImagesAbility::class => 'free',
            SearchVideosAbility::class => 'free',
            SearchCouponsAbility::class => 'free',
            GetPostProductsAbility::class => 'free',
            GetFeedStatusAbility::class => 'free',
            ListBlocksAbility::class => 'free',
            ValidateBlocksAbility::class => 'free',
            PreviewBlocksAbility::class => 'free',
            GetPostBlocksAbility::class => 'free',
            FindPostsAbility::class => 'free',
            AddProductsToPostAbility::class => 'pro',
            AddCouponsToPostAbility::class => 'pro',
            AddImagesToPostAbility::class => 'pro',
            AddVideosToPostAbility::class => 'pro',
            UpdateProductAbility::class => 'pro',
            RemoveProductsAbility::class => 'pro',
            ReorderProductsAbility::class => 'pro',
            RefreshPostProductsAbility::class => 'pro',
            ActivateModuleAbility::class => 'pro',
            DeactivateModuleAbility::class => 'pro',
            UpdateModuleSettingsAbility::class => 'pro',
            UpdateSettingsAbility::class => 'pro',
            CreateFeedModuleAbility::class => 'pro',
            ConnectShopAbility::class => 'pro',
            InsertBlocksAbility::class => 'pro',
            CreatePostAbility::class => 'pro',
            SetFeaturedImageAbility::class => 'pro',
            SetPostStatusAbility::class => 'pro',
        );
    }

    /**
     * Ability names (content-egg/...) this build exposes, computed from the
     * ability map WITHOUT touching the core abilities registry.
     *
     * Callers outside a REST request — notably the Agent Access admin screen —
     * MUST use this instead of wp_get_abilities(): that would force-initialize
     * the shared registry (firing the one-shot wp_abilities_api_init) before
     * other plugins registered on that hook have attached their listeners,
     * leaving their abilities unregistered for the rest of the request.
     */
    public static function enabledAbilityNames(): array
    {
        $names = array();
        foreach (self::abilityMap() as $class => $tier)
        {
            if ($tier === 'pro' && !Plugin::isPaidBuild())
            {
                continue;
            }
            /** @var AbilityBase $ability */
            $ability = new $class();
            $names[] = $ability->name();
        }

        return $names;
    }

    /**
     * Resolve an ability name (content-egg/...) to its instance, honoring the
     * free/pro tier gate (pro abilities are unavailable on free builds). Returns
     * null when the name is unknown or not exposed on this build. Used by the
     * POST proxy controller to dispatch by name.
     */
    public static function findEnabledAbility(string $name): ?AbilityBase
    {
        foreach (self::abilityMap() as $class => $tier)
        {
            if ($tier === 'pro' && !Plugin::isPaidBuild())
            {
                continue;
            }
            /** @var AbilityBase $ability */
            $ability = new $class();
            if ($ability->name() === $name)
            {
                return $ability;
            }
        }

        return null;
    }

    public static function registerCategory(): void
    {
        \wp_register_ability_category(self::CATEGORY, array(
            'label' => __('Content Egg', 'content-egg'),
            'description' => 'Affiliate product management and Egg Block page building.',
        ));
    }

    public static function registerAbilities(): void
    {
        foreach (self::abilityMap() as $class => $tier)
        {
            if ($tier === 'pro' && !Plugin::isPaidBuild())
            {
                continue;
            }

            /** @var AbilityBase $ability */
            $ability = new $class();

            \wp_register_ability($ability->name(), array(
                'label' => $ability->label(),
                'description' => $ability->description(),
                'category' => self::CATEGORY,
                'input_schema' => $ability->inputSchema(),
                'output_schema' => $ability->outputSchema(),
                'execute_callback' => static function ($input = null) use ($ability)
                {
                    return self::executeLogged($ability, is_array($input) ? $input : array());
                },
                'permission_callback' => static function ($input = null) use ($ability)
                {
                    return $ability->checkPermission($input);
                },
                'meta' => array(
                    'show_in_rest' => true,
                    'annotations' => $ability->annotations(),
                    'mcp' => array('public' => true),
                ),
            ));
        }
    }

    /**
     * Wraps execution with audit logging and exception -> WP_Error mapping
     * (error codes per design spec §9).
     *
     * @return array|\WP_Error
     */
    public static function executeLogged(AbilityBase $ability, array $input)
    {
        $start = microtime(true);

        try
        {
            $output = $ability->execute($input);
            self::log($ability, 'ok', '', $input, $start);

            return $output;
        }
        catch (AbilityRateLimitException $e)
        {
            self::log($ability, 'error', 'cegg_rate_limited', $input, $start);

            return new \WP_Error('cegg_rate_limited', $e->getMessage(), array(
                'status' => 429,
                'retry_after' => $e->retryAfter(),
            ));
        }
        catch (AbilityConflictException $e)
        {
            self::log($ability, 'error', 'cegg_conflict', $input, $start);

            return new \WP_Error('cegg_conflict', $e->getMessage(), array(
                'status' => 409,
                'revision' => $e->currentRevision(),
            ));
        }
        catch (FeedImportPendingException $e)
        {
            self::log($ability, 'error', 'cegg_feed_pending', $input, $start);

            return new \WP_Error(
                'cegg_feed_pending',
                'Feed import is still running. Call content-egg/get-feed-status to monitor progress.',
                array('status' => 409)
            );
        }
        catch (AbilitySearchException $e)
        {
            // Upstream module/API failure (e.g. missing API key): surface the
            // real reason so the user can fix the module, not a generic 500.
            self::log($ability, 'error', 'cegg_search_failed', $input, $start);

            return new \WP_Error('cegg_search_failed', $e->getMessage(), array('status' => 502));
        }
        catch (\InvalidArgumentException $e)
        {
            // Covers AbilityInputException and service-level input errors.
            self::log($ability, 'error', 'cegg_validation_failed', $input, $start);

            return new \WP_Error('cegg_validation_failed', $e->getMessage(), array('status' => 400));
        }
        catch (\Throwable $e)
        {
            self::log($ability, 'error', 'cegg_internal_error', $input, $start);
            error_log('[content-egg abilities] ' . $ability->name() . ': ' . $e->getMessage());

            return new \WP_Error(
                'cegg_internal_error',
                'Content Egg could not complete the operation due to an internal error. An administrator can find details in the server error log and the Agent Access activity log.',
                array('status' => 500)
            );
        }
    }

    private static function log(AbilityBase $ability, string $status, string $code, array $input, float $start): void
    {
        AbilityLog::record(
            $ability->name(),
            $status,
            $code,
            $input,
            (int) round((microtime(true) - $start) * 1000)
        );
    }
}
