<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\abilities\AbilitiesRegistrar;
use ContentEgg\application\abilities\OpenApiGenerator;

/**
 * AgentAbilityCatalog class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * UI presenter for the Agent Access screen: turns the raw ability registry
 * into human-friendly, grouped rows (label, plain description, example
 * prompt, tier). The Free/Pro tier is read from AbilitiesRegistrar so there
 * is a single source of truth; this class only adds the display copy.
 */
final class AgentAbilityCatalog
{
    /**
     * Grouped rows for the view. Each group: ['title' => string, 'abilities'
     * => array of ['name','label','desc','example','tier','available']].
     */
    public static function groups(): array
    {
        $paid = Plugin::isPaidBuild();
        $tiers = self::tierMap();
        // Badge only what the ChatGPT profile ACTUALLY drops on this build. Reading
        // the raw constant meant a free install (17 abilities, nothing trimmed)
        // still showed "Not in ChatGPT" on three abilities its schema does include.
        // enabledAbilityNames() is map-derived and safe to call on an admin request
        // -- wp_get_abilities() here would force-init the shared registry.
        $chatgpt_excluded = array_flip(
            OpenApiGenerator::chatGptExcludes(count(AbilitiesRegistrar::enabledAbilityNames()))
        );
        $groups = self::definitions();

        foreach ($groups as &$group)
        {
            foreach ($group['abilities'] as &$ability)
            {
                $tier = $tiers[$ability['name']] ?? 'pro';
                $ability['tier'] = $tier;
                $ability['available'] = ($tier === 'free') || $paid;
                // Trimmed from the ChatGPT Actions profile (30-operation cap);
                // still reachable via Claude/MCP/REST. Single source of truth:
                // OpenApiGenerator::CHATGPT_EXCLUDE.
                $ability['chatgpt_excluded'] = isset($chatgpt_excluded[$ability['name']]);
            }
        }

        return $groups;
    }

    /** Total number of abilities available on this build. */
    public static function availableCount(): int
    {
        $count = 0;
        foreach (self::groups() as $group)
        {
            foreach ($group['abilities'] as $ability)
            {
                if ($ability['available'])
                {
                    $count++;
                }
            }
        }

        return $count;
    }

    /** Ability name => 'free'|'pro', derived from the registrar. */
    private static function tierMap(): array
    {
        $map = array();
        foreach (AbilitiesRegistrar::abilityMap() as $class => $tier)
        {
            /** @var \ContentEgg\application\abilities\AbilityBase $ability */
            $ability = new $class();
            $map[$ability->name()] = $tier;
        }

        return $map;
    }

    /**
     * Display copy, grouped by what a user would want to do (friendlier than
     * a raw read/write split). Names must match the registered abilities.
     */
    private static function definitions(): array
    {
        return array(
            array(
                'title' => __('Explore & check', 'content-egg'),
                'abilities' => array(
                    array('name' => 'content-egg/get-status', 'label' => __('Plugin status', 'content-egg'), 'desc' => __('Confirm the plugin is set up and your license is active.', 'content-egg'), 'example' => __('Is Content Egg set up correctly on my site?', 'content-egg')),
                    array('name' => 'content-egg/get-guide', 'label' => __('Agent guide', 'content-egg'), 'desc' => __('Read the built-in guide that teaches your assistant how to use Content Egg.', 'content-egg'), 'example' => __('Read the Content Egg agent guide before we start.', 'content-egg')),
                    array('name' => 'content-egg/list-modules', 'label' => __('Active modules', 'content-egg'), 'desc' => __('See which affiliate and content modules are turned on.', 'content-egg'), 'example' => __('Which Content Egg modules are active?', 'content-egg')),
                    array('name' => 'content-egg/get-module-settings', 'label' => __('Module settings', 'content-egg'), 'desc' => __('Read a module’s configuration. API keys stay hidden.', 'content-egg'), 'example' => __('Show my Amazon module settings.', 'content-egg')),
                    array('name' => 'content-egg/get-settings', 'label' => __('Plugin settings', 'content-egg'), 'desc' => __('Read your general Content Egg settings.', 'content-egg'), 'example' => __('What are my general Content Egg settings?', 'content-egg')),
                    array('name' => 'content-egg/get-feed-status', 'label' => __('Feed status', 'content-egg'), 'desc' => __('Check whether a product feed has finished importing.', 'content-egg'), 'example' => __('Has my products feed finished importing?', 'content-egg')),
                ),
            ),
            array(
                'title' => __('Find & manage products', 'content-egg'),
                'abilities' => array(
                    array('name' => 'content-egg/search-products', 'label' => __('Search products', 'content-egg'), 'desc' => __('Find products from your networks by keyword.', 'content-egg'), 'example' => __('Search Amazon for robot vacuums — top 5 with prices.', 'content-egg')),
                    array('name' => 'content-egg/search-all-products', 'label' => __('Search several networks', 'content-egg'), 'desc' => __('Search several product modules at once and compare the results.', 'content-egg'), 'example' => __('Compare robot vacuums across Amazon, eBay and Walmart.', 'content-egg')),
                    array('name' => 'content-egg/get-post-products', 'label' => __('A post’s products', 'content-egg'), 'desc' => __('List the products attached to a post.', 'content-egg'), 'example' => __('What products are on my “Best Drills” post?', 'content-egg')),
                    array('name' => 'content-egg/add-products-to-post', 'label' => __('Add products', 'content-egg'), 'desc' => __('Attach products to a post — from your networks, or a custom one you describe.', 'content-egg'), 'example' => __('Add this deal as a custom product: “Acme Drill”, $99, example.com/drill.', 'content-egg')),
                    array('name' => 'content-egg/update-product', 'label' => __('Edit a product', 'content-egg'), 'desc' => __('Change a product’s title, price, or other fields.', 'content-egg'), 'example' => __('Set the DeWalt’s price to 129 on that post.', 'content-egg')),
                    array('name' => 'content-egg/refresh-post-products', 'label' => __('Refresh prices', 'content-egg'), 'desc' => __('Re-pull current prices and stock for a post’s products.', 'content-egg'), 'example' => __('Refresh the prices on my “Best Drills” post.', 'content-egg')),
                    array('name' => 'content-egg/reorder-products', 'label' => __('Reorder products', 'content-egg'), 'desc' => __('Change the order products appear in.', 'content-egg'), 'example' => __('Put the Makita first on that post.', 'content-egg')),
                    array('name' => 'content-egg/remove-products', 'label' => __('Remove products', 'content-egg'), 'desc' => __('Remove products from a post.', 'content-egg'), 'example' => __('Remove the discontinued Ryobi from that post.', 'content-egg')),
                ),
            ),
            array(
                'title' => __('Find media & coupons', 'content-egg'),
                'abilities' => array(
                    array('name' => 'content-egg/search-images', 'label' => __('Search images', 'content-egg'), 'desc' => __('Find royalty-free images (Pixabay, Unsplash, Pexels…).', 'content-egg'), 'example' => __('Search Pixabay for images of robot vacuums.', 'content-egg')),
                    array('name' => 'content-egg/search-videos', 'label' => __('Search videos', 'content-egg'), 'desc' => __('Find videos (YouTube, Pexels Videos).', 'content-egg'), 'example' => __('Find 3 YouTube videos reviewing robot vacuums.', 'content-egg')),
                    array('name' => 'content-egg/search-coupons', 'label' => __('Search coupons', 'content-egg'), 'desc' => __('Find coupons and deals from your coupon modules.', 'content-egg'), 'example' => __('Find current coupons from my Admitad module.', 'content-egg')),
                    array('name' => 'content-egg/add-images-to-post', 'label' => __('Add images', 'content-egg'), 'desc' => __('Attach found images to a post so an images block can show them.', 'content-egg'), 'example' => __('Add those robot-vacuum images to my “Best Vacuums” post.', 'content-egg')),
                    array('name' => 'content-egg/add-videos-to-post', 'label' => __('Add videos', 'content-egg'), 'desc' => __('Attach found videos to a post so a videos block can show them.', 'content-egg'), 'example' => __('Add those two review videos to my “Best Vacuums” post.', 'content-egg')),
                    array('name' => 'content-egg/add-coupons-to-post', 'label' => __('Add coupons', 'content-egg'), 'desc' => __('Attach found coupons to a post so a coupons block can show them.', 'content-egg'), 'example' => __('Add my Admitad coupons to the “Deals” post.', 'content-egg')),
                ),
            ),
            array(
                'title' => __('Build pages', 'content-egg'),
                'abilities' => array(
                    array('name' => 'content-egg/list-blocks', 'label' => __('Block catalog', 'content-egg'), 'desc' => __('See the Egg Blocks available for building pages.', 'content-egg'), 'example' => __('What Egg Blocks can you use?', 'content-egg')),
                    array('name' => 'content-egg/get-post-blocks', 'label' => __('Read a post’s blocks', 'content-egg'), 'desc' => __('See the Egg Blocks already in a post.', 'content-egg'), 'example' => __('What’s already on my “Best Headphones” post?', 'content-egg')),
                    array('name' => 'content-egg/preview-blocks', 'label' => __('Preview a layout', 'content-egg'), 'desc' => __('Preview how a set of blocks will look.', 'content-egg'), 'example' => __('Show me a preview of that comparison table.', 'content-egg')),
                    array('name' => 'content-egg/validate-blocks', 'label' => __('Check a layout', 'content-egg'), 'desc' => __('Check a page layout is valid before saving.', 'content-egg'), 'example' => __('Double-check the layout before you build it.', 'content-egg')),
                    array('name' => 'content-egg/insert-blocks', 'label' => __('Insert blocks', 'content-egg'), 'desc' => __('Add or replace blocks in a post.', 'content-egg'), 'example' => __('Add a FAQ to the end of that post.', 'content-egg')),
                    array('name' => 'content-egg/create-post', 'label' => __('Create a page', 'content-egg'), 'desc' => __('Create a full draft article from blocks and products.', 'content-egg'), 'example' => __('Create a draft roundup of the 3 best robot vacuums.', 'content-egg')),
                    array('name' => 'content-egg/find-posts', 'label' => __('Find a post', 'content-egg'), 'desc' => __('Look up existing posts by title, status or product module.', 'content-egg'), 'example' => __('Find my draft post about robot vacuums.', 'content-egg')),
                    array('name' => 'content-egg/set-featured-image', 'label' => __('Set featured image', 'content-egg'), 'desc' => __('Set a post’s featured image from a media item or image URL.', 'content-egg'), 'example' => __('Use this image as the featured image for that post.', 'content-egg')),
                    array('name' => 'content-egg/set-post-status', 'label' => __('Publish or schedule', 'content-egg'), 'desc' => __('Change a post to draft, pending, published or scheduled.', 'content-egg'), 'example' => __('Schedule that post for next Monday at 9am.', 'content-egg')),
                ),
            ),
            array(
                'title' => __('Modules & feeds', 'content-egg'),
                'abilities' => array(
                    array('name' => 'content-egg/activate-module', 'label' => __('Turn on a module', 'content-egg'), 'desc' => __('Activate an affiliate or content module.', 'content-egg'), 'example' => __('Turn on the Walmart module.', 'content-egg')),
                    array('name' => 'content-egg/deactivate-module', 'label' => __('Turn off a module', 'content-egg'), 'desc' => __('Deactivate a module.', 'content-egg'), 'example' => __('Turn off the eBay module.', 'content-egg')),
                    array('name' => 'content-egg/update-module-settings', 'label' => __('Edit module settings', 'content-egg'), 'desc' => __('Change a module’s configuration.', 'content-egg'), 'example' => __('Set my Amazon associate tag to mysite-20.', 'content-egg')),
                    array('name' => 'content-egg/update-settings', 'label' => __('Edit plugin settings', 'content-egg'), 'desc' => __('Change your general Content Egg settings.', 'content-egg'), 'example' => __('Change my default product template to grid.', 'content-egg')),
                    array('name' => 'content-egg/create-feed-module', 'label' => __('Create a feed', 'content-egg'), 'desc' => __('Set up a new product feed from a CSV or XML URL.', 'content-egg'), 'example' => __('Create a feed from example.com/feed.csv (CSV, USD).', 'content-egg')),
                    array('name' => 'content-egg/connect-shop', 'label' => __('Connect a shop', 'content-egg'), 'desc' => __('Add a new affiliate shop module from its domain (needs Affiliate Egg).', 'content-egg'), 'example' => __('Connect walmart.com as a shop module.', 'content-egg')),
                ),
            ),
        );
    }
}
