<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;

/**
 * BuildCapabilities class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Declares to agents what this build cannot do.
 *
 * Free builds register only the 'free' tier of AbilitiesRegistrar::abilityMap(),
 * so the write abilities vanish from discovery with nothing to explain their
 * absence. This class turns that gate into an explicit declaration, surfaced by
 * GetStatusAbility, the agent guide and the POST proxy's 402.
 *
 * INERT ON PAID BUILDS by contract: forBuild(true) declares nothing, markdown(true)
 * is an empty string, statusFields(true)/statusSchemaProperties(true) are empty
 * arrays. A paid site's responses, guide and OpenAPI must stay byte-identical to a
 * build without this class.
 *
 * Every method takes the build as a PARAMETER rather than reading it:
 * Plugin::isPaidBuild() resolves from file and class existence and cannot be
 * mocked, so passing it in is the only way to test free behaviour from a paid
 * checkout. current() is the single place that reads the real build.
 */
final class BuildCapabilities
{
    const UPGRADE_URL = 'https://www.keywordrush.com/contentegg/pricing?utm_source=plugin&utm_medium=agent';

    /**
     * @return array{can_read:bool,can_write:bool,unavailable:array,upgrade_url:?string}
     */
    public static function forBuild(bool $paid): array
    {
        $unavailable = array();

        if (!$paid)
        {
            foreach (AbilitiesRegistrar::abilityMap() as $class => $tier)
            {
                if ($tier !== 'pro')
                {
                    continue;
                }

                /** @var AbilityBase $ability */
                $ability = new $class();
                $unavailable[] = array(
                    'name' => $ability->name(),
                    'label' => $ability->label(),
                    'requires' => 'pro',
                );
            }
        }

        return array(
            'can_read' => true,
            'can_write' => $paid,
            'unavailable' => $unavailable,
            'upgrade_url' => $paid ? null : self::upgradeUrl(),
        );
    }

    /** The only place that reads the real build. */
    public static function current(): array
    {
        return self::forBuild(Plugin::isPaidBuild());
    }

    public static function upgradeUrl(): string
    {
        return (string) \apply_filters('content_egg_agent_upgrade_url', self::UPGRADE_URL);
    }

    /**
     * The ability instance for a name that exists in the map but is gated on
     * this build. Null for free abilities, unknown names, and paid builds —
     * lets the POST proxy tell "needs Pro" apart from "no such ability".
     */
    public static function gatedAbility(string $name, bool $paid): ?AbilityBase
    {
        if ($paid)
        {
            return null;
        }

        foreach (AbilitiesRegistrar::abilityMap() as $class => $tier)
        {
            if ($tier !== 'pro')
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

    /** Extra get-status payload keys. Empty on a paid build. */
    public static function statusFields(bool $paid): array
    {
        if ($paid)
        {
            return array();
        }

        $caps = self::forBuild(false);

        return array(
            'capabilities' => array(
                'can_read' => $caps['can_read'],
                'can_write' => $caps['can_write'],
            ),
            'unavailable_abilities' => $caps['unavailable'],
            'upgrade_url' => $caps['upgrade_url'],
        );
    }

    /** Matching output-schema properties. Keys MUST match statusFields(). */
    public static function statusSchemaProperties(bool $paid): array
    {
        if ($paid)
        {
            return array();
        }

        return array(
            'capabilities' => array(
                'type' => 'object',
                'description' => 'What this build allows. can_write is false on Content Egg Free.',
                'properties' => array(
                    'can_read' => array('type' => 'boolean'),
                    'can_write' => array('type' => 'boolean'),
                ),
            ),
            'unavailable_abilities' => array(
                'type' => 'array',
                'description' => 'Abilities this build does not register. Absent on paid builds.',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array('type' => 'string'),
                        'label' => array('type' => 'string'),
                        'requires' => array('type' => 'string'),
                    ),
                ),
            ),
            'upgrade_url' => array('type' => 'string'),
        );
    }

    /**
     * The {capabilities} guide block. Empty on a paid build so the served guide
     * stays byte-identical. Returns NO trailing newline: the controller removes
     * the placeholder's own line, so a trailing newline would leave a gap.
     */
    public static function markdown(bool $paid): string
    {
        if ($paid)
        {
            return '';
        }

        $caps = self::forBuild(false);

        $lines = array();
        foreach ($caps['unavailable'] as $item)
        {
            $lines[] = '- `' . $item['name'] . '` — ' . $item['label'];
        }

        return "## What this build can do\n"
            . "\n"
            . "This site runs **Content Egg Free**: you can research, compose and preview,\n"
            . "but nothing can be written to the site. These abilities do not exist here —\n"
            . "calling one returns `cegg_requires_pro` (402):\n"
            . "\n"
            . implode("\n", $lines) . "\n"
            . "\n"
            . "They ship with Content Egg Pro:\n"
            . $caps['upgrade_url'] . "\n"
            . "\n"
            . "### How to work on this site\n"
            . "\n"
            . "Do the reachable work first. The entire build loop short of saving is\n"
            . "available:\n"
            . "\n"
            . "    search-products / search-all-products -> compose a block tree ->\n"
            . "    validate-blocks -> preview-blocks\n"
            . "\n"
            . "For \"build me a best-X roundup\": research the lineup, search the products,\n"
            . "compose the real Egg Blocks tree and validate it. Editorial blocks (intro,\n"
            . "FAQ, step lists, key takeaways, conclusion) preview exactly as they will\n"
            . "publish, so show the user those. Then say plainly that saving it needs\n"
            . "Content Egg Pro, and that what you built is ready to insert unchanged.\n"
            . "\n"
            . "One limit to be honest about: product-bound blocks (`comparison-table`,\n"
            . "`product-card`, `verdict`, `quick-picks`, `where-to-buy`) preview WITHOUT\n"
            . "live product data on this build. Prices, images and links hydrate only once\n"
            . "products are attached to a real post, and attaching is Pro. Describe the\n"
            . "products from your search results instead of implying the preview shows\n"
            . "them.\n"
            . "\n"
            . "State the limit once, when you reach it — not before starting, and not\n"
            . "repeated in every message.";
    }
}
