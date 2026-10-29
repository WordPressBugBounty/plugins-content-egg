<?php

namespace ContentEgg\application\components\families;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\WooHelper;

defined('\ABSPATH') || exit;

/**
 * FamilyStore class file
 *
 * The only class that talks to WordPress for variant families: the private
 * `cegg_family` taxonomy (one term per family, definition in the term meta
 * `_cegg_family`), membership (a page is a member when it has the term) and
 * the catalog cache (the post ids a shop catalog list should hide so each
 * family shows once).
 *
 * The catalog cache is rebuilt after every family write. A caller that
 * writes many times in a row (set-family) wraps its writes in
 * suspendCatalog() / resumeCatalog(), so the cache is rebuilt once at the
 * end. A member's status change or delete (bulk trash in wp-admin fires one
 * per post) only marks the cache stale; it is rebuilt once, when the request
 * ends (`shutdown`) or as soon as someone reads it (catalogHiddenIds()), so a
 * reader never sees a stale list. The mark is persisted by deleting the
 * cached option: another request that reads the list meanwhile, or any
 * request after this one died before `shutdown`, finds no cache and rebuilds
 * it. A change made during `shutdown` itself rebuilds at once.
 *
 * primaryOf(), catalogHidden(), catalogRows() and restoreVerdict() are pure
 * and unit-tested; everything else needs WordPress and is covered by the WP
 * smoke test.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class FamilyStore
{

	// A family holds at most this many pages (members() never returns more).
	const MAX_MEMBERS = 100;

	// Cached sorted list of post ids the catalog hides (autoload no).
	const CATALOG_OPTION = 'cegg_family_catalog_hidden';

	// A member of the post being deleted: its family is rebuilt once the term links are gone.
	private static $pending_delete = false;

	// Families of the pages being deleted in this request: deleted_post checks whether any is left empty.
	private static $deleting_from = array();

	// Depth of suspendCatalog() calls; rebuilds wait while it is above 0.
	private static $suspended = 0;

	// The catalog cache is stale: a rebuild is owed.
	private static $dirty = false;

	private static $shutdown_hooked = false;

	public static function initAction(): void
	{
		// The plugin boots on `init`, so a hook added now would never fire: register at once when init has started.
		if (\did_action('init'))
		{
			self::register();
		}
		else
		{
			\add_action('init', array(__CLASS__, 'register'));
		}
		\add_action('transition_post_status', array(__CLASS__, 'onTransitionPostStatus'), 10, 3);
		\add_action('before_delete_post', array(__CLASS__, 'onBeforeDeletePost'));
	}

	public static function register(): void
	{
		\register_taxonomy(VariantFamily::TAXONOMY, self::postTypes(), array(
			'labels' => array('name' => 'Product families', 'singular_name' => 'Product family'),
			'public' => false,
			'show_ui' => false,
			'show_in_rest' => false,
			'rewrite' => false,
			'query_var' => false,
			'hierarchical' => false,
			'show_in_nav_menus' => false,
		));
	}

	/**
	 * Post types a family can hold: the plugin's own post types, plus
	 * WooCommerce products when WooCommerce is active.
	 */
	public static function postTypes(): array
	{
		$types = (array) GeneralConfig::getInstance()->option('post_types');
		if (WooHelper::isWooActive())
		{
			$types[] = 'product';
		}

		return array_values(array_unique(array_filter(array_map('strval', $types), 'strlen')));
	}

	/**
	 * @throws \InvalidArgumentException on an invalid name or definition.
	 * @throws \RuntimeException when WordPress refuses to create the term.
	 */
	public static function create(string $name, array $definition): int
	{
		$name = self::cleanName($name);
		$definition = VariantFamily::normalize($definition);

		$result = \wp_insert_term(\wp_slash($name), VariantFamily::TAXONOMY);

		// WordPress refuses a second term with the same name: two families may share a name, so retry with a numbered slug.
		for ($n = 2; \is_wp_error($result) && $result->get_error_code() === 'term_exists' && $n <= 50; $n++)
		{
			$slug = \sanitize_title($name);
			$slug = ($slug !== '' ? $slug : 'family') . '-' . $n;
			$result = \wp_insert_term(\wp_slash($name), VariantFamily::TAXONOMY, array('slug' => $slug));
		}

		if (\is_wp_error($result))
		{
			throw new \RuntimeException('The family could not be created: ' . $result->get_error_message());
		}

		$id = (int) $result['term_id'];
		\update_term_meta($id, VariantFamily::META, \wp_slash($definition));
		self::rebuildCatalog();

		return $id;
	}

	/**
	 * Replace the definition (and the name, when $name is not null).
	 *
	 * @throws \InvalidArgumentException when the family does not exist or the name or definition is invalid.
	 * @throws \RuntimeException when WordPress refuses the rename.
	 */
	public static function update(int $id, ?string $name, array $definition): void
	{
		self::requireTerm($id);
		$definition = VariantFamily::normalize($definition);

		if ($name !== null)
		{
			$result = \wp_update_term($id, VariantFamily::TAXONOMY, array('name' => \wp_slash(self::cleanName($name))));
			if (\is_wp_error($result))
			{
				throw new \RuntimeException('The family could not be renamed: ' . $result->get_error_message());
			}
		}

		\update_term_meta($id, VariantFamily::META, \wp_slash($definition));
		self::rebuildCatalog();
	}

	/**
	 * Remove the family term. The pages and their passports stay as they are.
	 *
	 * @throws \InvalidArgumentException when the family does not exist.
	 * @throws \RuntimeException when WordPress refuses the delete.
	 */
	public static function delete(int $id): void
	{
		self::requireTerm($id);

		$result = \wp_delete_term($id, VariantFamily::TAXONOMY);
		if (\is_wp_error($result))
		{
			throw new \RuntimeException('The family could not be deleted: ' . $result->get_error_message());
		}

		self::rebuildCatalog();
	}

	/**
	 * ['id', 'name'] + the stored definition, or null when the term is gone.
	 * Meta that is missing or no longer passes VariantFamily::normalize()
	 * still returns the id and name, with an empty definition
	 * (dimensions [], order [], primary 0, brand ''), so callers never
	 * receive a half-valid shape; such a family has no usable dimensions.
	 */
	public static function get(int $id): ?array
	{
		$term = self::term($id);
		if (!$term)
		{
			return null;
		}

		$stored = \get_term_meta($id, VariantFamily::META, true);
		try
		{
			$definition = VariantFamily::normalize(is_array($stored) ? $stored : array());
		}
		catch (\InvalidArgumentException $e)
		{
			$definition = array('v' => VariantFamily::VERSION, 'dimensions' => array(), 'order' => array(), 'primary' => 0, 'brand' => '');
		}

		return array('id' => (int) $term->term_id, 'name' => self::termName($term)) + $definition;
	}

	/**
	 * Families by name, with the number of pages in each (any status except
	 * trash, at most MAX_MEMBERS).
	 *
	 * With $sees (callable(int $post_id, string $status): bool) only the pages
	 * the caller may see are counted, and a family whose pages are all hidden
	 * from the caller is not listed (total counts the listed families). A
	 * family without pages is listed.
	 *
	 * @return array {families: [{id, name, members}], total: int}
	 */
	public static function listFamilies(int $limit, int $offset, ?callable $sees = null): array
	{
		$limit = max(1, $limit);
		$offset = max(0, $offset);

		if ($sees !== null)
		{
			return self::listVisibleFamilies($limit, $offset, $sees);
		}

		$terms = \get_terms(array(
			'taxonomy' => VariantFamily::TAXONOMY,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
			'number' => $limit,
			'offset' => $offset,
			'update_term_meta_cache' => false,
		));
		$total = \wp_count_terms(array('taxonomy' => VariantFamily::TAXONOMY, 'hide_empty' => false));

		$families = array();
		if (!\is_wp_error($terms))
		{
			foreach ($terms as $term)
			{
				$families[] = array(
					'id' => (int) $term->term_id,
					'name' => self::termName($term),
					'members' => count(self::queryMemberIds((int) $term->term_id)),
				);
			}
		}

		return array('families' => $families, 'total' => \is_wp_error($total) ? count($families) : (int) $total);
	}

	/**
	 * The listing window over the families the caller may see. Pure.
	 *
	 * @param array $terms   [[term_id, name]] every family, in listing order.
	 * @param array $links   [[post_id, term_id]] of the families' pages (any status except trash).
	 * @param array $visible [post_id => true] the pages the caller may see.
	 * @return array {families: [{id, name, members}], total: int}
	 */
	public static function visibleListing(array $terms, array $links, array $visible, int $limit, int $offset): array
	{
		$has_pages = array();
		$seen = array();
		foreach ($links as $link)
		{
			if (!is_array($link) || count($link) < 2)
			{
				continue;
			}
			$link = array_values($link);
			$post_id = (int) $link[0];
			$term_id = (int) $link[1];
			if ($post_id <= 0 || $term_id <= 0)
			{
				continue;
			}
			$has_pages[$term_id] = true;
			if (isset($visible[$post_id]))
			{
				$seen[$term_id][$post_id] = true;
			}
		}

		$listed = array();
		foreach ($terms as $term)
		{
			$term = is_array($term) ? array_values($term) : array();
			$term_id = isset($term[0]) ? (int) $term[0] : 0;
			if ($term_id <= 0)
			{
				continue;
			}
			$count = isset($seen[$term_id]) ? count($seen[$term_id]) : 0;
			if ($count === 0 && isset($has_pages[$term_id]))
			{
				// Every page is hidden from the caller, and so is the family.
				continue;
			}
			$listed[] = array('id' => $term_id, 'name' => isset($term[1]) ? (string) $term[1] : '', 'members' => min($count, self::MAX_MEMBERS));
		}

		return array('families' => array_slice($listed, max(0, $offset), max(1, $limit)), 'total' => count($listed));
	}

	/**
	 * listFamilies() with $sees: every family, with one query for their pages,
	 * one for the published among them and one for the term links.
	 */
	private static function listVisibleFamilies(int $limit, int $offset, callable $sees): array
	{
		$terms = \get_terms(array(
			'taxonomy' => VariantFamily::TAXONOMY,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
			'update_term_meta_cache' => false,
		));
		if (\is_wp_error($terms))
		{
			return array('families' => array(), 'total' => 0);
		}

		$query = array(
			'post_type' => self::postTypes(),
			'tax_query' => array(array(
				'taxonomy' => VariantFamily::TAXONOMY,
				'operator' => 'EXISTS',
			)),
			'fields' => 'ids',
			'numberposts' => -1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);
		$all = array_map('intval', (array) \get_posts(array('post_status' => 'any') + $query));
		$published = array_flip(array_map('intval', (array) \get_posts(array('post_status' => 'publish') + $query)));

		$visible = array();
		foreach ($all as $post_id)
		{
			$status = isset($published[$post_id]) ? 'publish' : (string) \get_post_status($post_id);
			if ($sees($post_id, $status))
			{
				$visible[$post_id] = true;
			}
		}

		$links = array();
		if ($all)
		{
			$objects = \wp_get_object_terms($all, VariantFamily::TAXONOMY, array(
				'fields' => 'all_with_object_id',
				'orderby' => 'none',
				'update_term_meta_cache' => false,
			));
			if (!\is_wp_error($objects))
			{
				foreach ($objects as $object)
				{
					$links[] = array((int) $object->object_id, (int) $object->term_id);
				}
			}
		}

		$rows = array();
		foreach ($terms as $term)
		{
			$rows[] = array((int) $term->term_id, self::termName($term));
		}

		return self::visibleListing($rows, $links, $visible, $limit, $offset);
	}

	/**
	 * The family a page belongs to, 0 when it has none.
	 */
	public static function familyIdOf(int $post_id): int
	{
		if ($post_id <= 0)
		{
			return 0;
		}

		$terms = \get_the_terms($post_id, VariantFamily::TAXONOMY);
		if (!is_array($terms) || !$terms)
		{
			return 0;
		}

		$term = reset($terms);

		return (int) $term->term_id;
	}

	/**
	 * Make the page a member of the family. A page holds one family, so this
	 * replaces any family it had; callers that must not move a page check
	 * familyIdOf() first.
	 *
	 * @throws \InvalidArgumentException when the family or page does not exist, the page cannot be in a family or the family is full.
	 * @throws \RuntimeException when WordPress refuses the link.
	 */
	public static function join(int $id, int $post_id): void
	{
		self::requireTerm($id);

		$post = $post_id > 0 ? \get_post($post_id) : null;
		if (!$post || !in_array($post->post_type, self::postTypes(), true))
		{
			throw new \InvalidArgumentException(sprintf('Page %d does not exist or is not a post type that can hold a product family.', $post_id));
		}

		if (self::familyIdOf($post_id) !== $id && count(self::queryMemberIds($id)) >= self::MAX_MEMBERS)
		{
			throw new \InvalidArgumentException(sprintf('A family can hold at most %d pages.', self::MAX_MEMBERS));
		}

		$result = \wp_set_object_terms($post_id, array($id), VariantFamily::TAXONOMY, false);
		if (\is_wp_error($result))
		{
			throw new \RuntimeException('The page could not join the family: ' . $result->get_error_message());
		}

		self::rebuildCatalog();
	}

	public static function leave(int $post_id): void
	{
		if ($post_id <= 0)
		{
			return;
		}

		\wp_delete_object_term_relationships($post_id, array(VariantFamily::TAXONOMY));
		self::rebuildCatalog();
	}

	/**
	 * Hold catalog rebuilds until the matching resumeCatalog(). Calls nest;
	 * always pair them with try/finally.
	 */
	public static function suspendCatalog(): void
	{
		self::$suspended++;
	}

	/**
	 * End a suspendCatalog(). The outermost one rebuilds the cache once when a
	 * write happened in between (also after an exception and partial writes).
	 */
	public static function resumeCatalog(): void
	{
		if (self::$suspended > 0)
		{
			self::$suspended--;
		}
		if (self::$suspended === 0 && self::$dirty)
		{
			self::rebuildCatalog();
		}
	}

	/**
	 * The family's pages by post id, at most MAX_MEMBERS. A password-protected
	 * page keeps its status here but is left out when $published_only: it
	 * does not count as published.
	 *
	 * @return array [{post_id, title, url, status, variant}]; variant is the page's passport variant, or [].
	 */
	public static function members(int $id, bool $published_only = true): array
	{
		return self::memberRows(self::queryMembers($id, $published_only));
	}

	/**
	 * @param \WP_Post[] $posts
	 */
	private static function memberRows(array $posts): array
	{
		$ids = array();
		foreach ($posts as $post)
		{
			$ids[] = (int) $post->ID;
		}
		if ($ids)
		{
			\update_meta_cache('post', $ids);
		}

		$rows = array();
		foreach ($posts as $post)
		{
			$passport = ProductPassport::get((int) $post->ID);
			$rows[] = array(
				'post_id' => (int) $post->ID,
				'title' => \wp_specialchars_decode((string) $post->post_title, ENT_QUOTES),
				'url' => (string) \get_permalink($post),
				'status' => (string) $post->post_status,
				'variant' => ($passport && isset($passport['variant']) && is_array($passport['variant'])) ? $passport['variant'] : array(),
			);
		}

		return $rows;
	}

	/**
	 * The post id the family is represented by: its `primary` when that is a
	 * published member, else the lowest published post id, else 0.
	 *
	 * @param array $family  the stored definition (only `primary` is read).
	 * @param array $members [{post_id, status, ...}]
	 */
	public static function primaryOf(array $family, array $members): int
	{
		$published = array();
		foreach ($members as $member)
		{
			if (is_array($member) && isset($member['post_id']) && isset($member['status']) && $member['status'] === 'publish')
			{
				$published[] = (int) $member['post_id'];
			}
		}

		if (!$published)
		{
			return 0;
		}

		$primary = isset($family['primary']) ? (int) $family['primary'] : 0;
		if ($primary > 0 && in_array($primary, $published, true))
		{
			return $primary;
		}

		return min($published);
	}

	/**
	 * The post ids a catalog list hides: every published member except each
	 * family's primaryOf(). Drafted and other unpublished members are not
	 * listed (they are not shown anyway).
	 *
	 * @param array $families [{family, members}]
	 * @return int[] sorted
	 */
	public static function catalogHidden(array $families): array
	{
		$hidden = array();
		foreach ($families as $row)
		{
			if (!is_array($row))
			{
				continue;
			}

			$family = (isset($row['family']) && is_array($row['family'])) ? $row['family'] : array();
			$members = (isset($row['members']) && is_array($row['members'])) ? $row['members'] : array();
			$primary = self::primaryOf($family, $members);

			foreach ($members as $member)
			{
				if (!is_array($member) || !isset($member['post_id']) || !isset($member['status']) || $member['status'] !== 'publish')
				{
					continue;
				}
				if ((int) $member['post_id'] !== $primary)
				{
					$hidden[(int) $member['post_id']] = true;
				}
			}
		}

		$ids = array_keys($hidden);
		sort($ids, SORT_NUMERIC);

		return $ids;
	}

	/**
	 * The catalogHidden() input from the term links of the published pages
	 * that have a family: one row per family, members by post id (at most
	 * MAX_MEMBERS, as members() returns), with the family's stored definition.
	 *
	 * @param array $links       [[post_id, term_id]] of published pages.
	 * @param array $definitions [term_id => stored definition]; anything else than an array reads as [].
	 * @return array [{family, members: [{post_id, status: 'publish'}]}]
	 */
	public static function catalogRows(array $links, array $definitions): array
	{
		$by_family = array();
		foreach ($links as $link)
		{
			if (!is_array($link) || count($link) < 2)
			{
				continue;
			}
			$link = array_values($link);
			$post_id = (int) $link[0];
			$term_id = (int) $link[1];
			if ($post_id <= 0 || $term_id <= 0)
			{
				continue;
			}
			$by_family[$term_id][$post_id] = true;
		}

		$rows = array();
		foreach ($by_family as $term_id => $post_ids)
		{
			$post_ids = array_keys($post_ids);
			sort($post_ids, SORT_NUMERIC);

			$members = array();
			foreach (array_slice($post_ids, 0, self::MAX_MEMBERS) as $post_id)
			{
				$members[] = array('post_id' => (int) $post_id, 'status' => 'publish');
			}

			$definition = isset($definitions[$term_id]) && is_array($definitions[$term_id]) ? $definitions[$term_id] : array();
			$rows[] = array('family' => $definition, 'members' => $members);
		}

		return $rows;
	}

	/**
	 * Whether a member restored from the trash can stay in its family: '' when
	 * it can, else why it leaves — 'misfit' (its variant no longer fits the
	 * family), 'full' (the family already holds MAX_MEMBERS other pages) or
	 * 'duplicate' (a sibling that is not in the trash has its variant). A
	 * family whose definition is unusable (no dimensions) is only checked for
	 * its size: a broken definition is no reason to drop its pages.
	 *
	 * @param array $members [{post_id, status?, variant}] the family's pages; the restored page may be among them. A row without a status counts as not in the trash.
	 * @param array $variant the restored page's passport variant ([] without one).
	 */
	public static function restoreVerdict(array $family, array $members, int $post_id, array $variant): string
	{
		$usable = isset($family['dimensions']) && is_array($family['dimensions']) && $family['dimensions'];

		if ($usable)
		{
			try
			{
				VariantFamily::memberValues($family, $variant);
			}
			catch (\InvalidArgumentException $e)
			{
				return 'misfit';
			}
		}

		$others = 0;
		foreach ($members as $member)
		{
			if (!is_array($member) || !isset($member['post_id']) || (int) $member['post_id'] === $post_id)
			{
				continue;
			}
			if (isset($member['status']) && $member['status'] === 'trash')
			{
				continue;
			}
			$others++;
		}
		if ($others >= self::MAX_MEMBERS)
		{
			return 'full';
		}

		if ($usable && VariantFamily::duplicateOf($family, $members, $post_id, $variant) > 0)
		{
			return 'duplicate';
		}

		return '';
	}

	/**
	 * The cached list written by rebuildCatalog(). A rebuild that is owed
	 * runs first, so the list is never stale.
	 *
	 * @return int[]
	 */
	public static function catalogHiddenIds(): array
	{
		if (self::$dirty && self::$suspended === 0)
		{
			self::rebuildCatalog();
		}

		$ids = \get_option(self::CATALOG_OPTION, null);

		// No cache: marked stale (by this or another request) or never built. Build it now, even inside a suspended batch.
		if (!is_array($ids))
		{
			self::buildCatalog();
			$ids = \get_option(self::CATALOG_OPTION, array());
		}

		return is_array($ids) ? array_values(array_map('intval', $ids)) : array();
	}

	/**
	 * Recompute the hidden list from every family's published members, with
	 * one query for the pages, one for their term links and one for the
	 * families' definitions. While suspended (suspendCatalog()) it only notes
	 * that a rebuild is owed.
	 */
	public static function rebuildCatalog(): void
	{
		if (self::$suspended > 0)
		{
			self::$dirty = true;
			return;
		}

		self::buildCatalog();
	}

	/**
	 * rebuildCatalog() without the suspend check.
	 */
	private static function buildCatalog(): void
	{
		self::$dirty = false;

		// Published, without a password (as queryMembers($id, true)), in any family.
		$post_ids = \get_posts(array(
			'post_type' => self::postTypes(),
			'post_status' => 'publish',
			'has_password' => false,
			'tax_query' => array(array(
				'taxonomy' => VariantFamily::TAXONOMY,
				'operator' => 'EXISTS',
			)),
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'numberposts' => -1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		));

		$links = array();
		$definitions = array();
		if ($post_ids)
		{
			$terms = \wp_get_object_terms(array_map('intval', $post_ids), VariantFamily::TAXONOMY, array(
				'fields' => 'all_with_object_id',
				'orderby' => 'none',
				'update_term_meta_cache' => false,
			));
			if (!\is_wp_error($terms))
			{
				foreach ($terms as $term)
				{
					$links[] = array((int) $term->object_id, (int) $term->term_id);
					$definitions[(int) $term->term_id] = array();
				}
			}

			if ($definitions)
			{
				\update_termmeta_cache(array_keys($definitions));
				foreach (array_keys($definitions) as $term_id)
				{
					$definitions[$term_id] = \get_term_meta($term_id, VariantFamily::META, true);
				}
			}
		}

		$hidden = self::catalogHidden(self::catalogRows($links, $definitions));

		$stored = \get_option(self::CATALOG_OPTION, null);
		$current = is_array($stored) ? array_values(array_map('intval', $stored)) : null;
		if ($current !== $hidden)
		{
			\update_option(self::CATALOG_OPTION, $hidden, false);
		}
	}

	/**
	 * The request ends: run the rebuild that is owed, even if a caller left
	 * the catalog suspended after an error.
	 */
	public static function onShutdown(): void
	{
		self::$suspended = 0;
		if (self::$dirty)
		{
			self::rebuildCatalog();
		}
	}

	/**
	 * A member's status changed (publish, unpublish, trash, restore). The
	 * catalog is rebuilt once at the end of the request (or when read before
	 * that). A member restored from the trash leaves its family when it no
	 * longer fits (restoreVerdict()).
	 */
	public static function onTransitionPostStatus($new_status, $old_status, $post): void
	{
		if ($new_status === $old_status || !is_object($post) || empty($post->ID))
		{
			return;
		}

		$post_id = (int) $post->ID;
		$family_id = self::familyIdOf($post_id);
		if ($family_id <= 0)
		{
			return;
		}

		if ($old_status === 'trash' && $new_status !== 'trash')
		{
			self::recheckRestored($family_id, $post_id);
		}

		self::markCatalogStale();
	}

	/**
	 * A member is about to be deleted. Its term links are still there now, so
	 * the rebuild waits for deleted_post, when they are gone.
	 */
	public static function onBeforeDeletePost($post_id): void
	{
		$family_id = self::familyIdOf((int) $post_id);
		if ($family_id <= 0)
		{
			return;
		}

		self::$deleting_from[$family_id] = true;
		if (!self::$pending_delete)
		{
			self::$pending_delete = true;
			\add_action('deleted_post', array(__CLASS__, 'onDeletedPost'));
		}
	}

	/**
	 * A member was deleted for good. A family left with no page at all, not
	 * even one in the trash, is deleted too: nothing can come back to it, and
	 * an empty family would only be picked up again by the next agent.
	 */
	public static function onDeletedPost($post_id = 0): void
	{
		\remove_action('deleted_post', array(__CLASS__, 'onDeletedPost'));
		self::$pending_delete = false;

		$families = array_keys(self::$deleting_from);
		self::$deleting_from = array();
		foreach (self::emptiedFamilies($families, array(__CLASS__, 'hasAnyMember')) as $family_id)
		{
			try
			{
				self::delete($family_id);
			}
			catch (\Throwable $e)
			{
				// Already gone, or WordPress refused: the family stays, as before.
			}
		}

		self::markCatalogStale();
	}

	/**
	 * Pure: the families among $family_ids that $has_member says have no page left.
	 *
	 * @param int[]    $family_ids
	 * @param callable $has_member function (int $family_id): bool
	 * @return int[]
	 */
	public static function emptiedFamilies(array $family_ids, callable $has_member): array
	{
		$empty = array();
		foreach ($family_ids as $family_id)
		{
			$family_id = (int) $family_id;
			if ($family_id > 0 && !$has_member($family_id))
			{
				$empty[] = $family_id;
			}
		}

		return $empty;
	}

	/** Any page in the family, the trash and every other status included. */
	public static function hasAnyMember(int $id): bool
	{
		$ids = \get_posts(array(
			// Every type: 'any' skips types hidden from search, and a page of a type
			// no longer in Content Egg's post types still belongs to the family.
			'post_type' => array_values(\get_post_types()),
			'post_status' => array_keys(\get_post_stati()),
			'tax_query' => array(array(
				'taxonomy' => VariantFamily::TAXONOMY,
				'field' => 'term_id',
				'terms' => array($id),
			)),
			'fields' => 'ids',
			'numberposts' => 1,
			'no_found_rows' => true,
			'suppress_filters' => true,
		));

		return !empty($ids);
	}

	/**
	 * A rebuild is owed: it runs at `shutdown`, or earlier when the list is
	 * read or a suspended batch ends. The cached option is deleted, so no
	 * other request serves the stale list and the rebuild is not lost when
	 * this process dies first. During `shutdown` it rebuilds at once (the
	 * shutdown rebuild may already have run).
	 */
	private static function markCatalogStale(): void
	{
		self::$dirty = true;
		// Every time: another request may have rebuilt it since the last change.
		\delete_option(self::CATALOG_OPTION);

		if (\did_action('shutdown'))
		{
			self::$suspended = 0;
			self::buildCatalog();
			return;
		}

		if (!self::$shutdown_hooked)
		{
			self::$shutdown_hooked = true;
			\add_action('shutdown', array(__CLASS__, 'onShutdown'), 0);
		}
	}

	/**
	 * R12: a member back from the trash may now duplicate a sibling (one that
	 * took its variant meanwhile), no longer fit the family (its definition
	 * changed) or overflow it (trashed pages do not count against the limit).
	 * Then it leaves the family; its passport stays.
	 */
	private static function recheckRestored(int $family_id, int $post_id): void
	{
		$family = self::get($family_id);
		if ($family === null)
		{
			return;
		}

		// One more than the limit, so a family that is already full is seen as such.
		$members = self::memberVariants($family_id, self::MAX_MEMBERS + 1);
		$passport = ProductPassport::get($post_id);
		$variant = ($passport && isset($passport['variant']) && is_array($passport['variant'])) ? $passport['variant'] : array();

		if (self::restoreVerdict($family, $members, $post_id, $variant) !== '')
		{
			\wp_delete_object_term_relationships($post_id, array(VariantFamily::TAXONOMY));
		}
	}

	/**
	 * What restoreVerdict() needs of the family's pages, without loading the
	 * posts (a bulk untrash runs this once per restored page): the ids of the
	 * pages in any status except trash, so no row carries a status, and
	 * their passport variants.
	 *
	 * @return array [{post_id, variant}]
	 */
	private static function memberVariants(int $id, int $limit): array
	{
		$ids = self::queryMemberIds($id, $limit);
		if ($ids)
		{
			\update_meta_cache('post', $ids);
		}

		$rows = array();
		foreach ($ids as $member_id)
		{
			$passport = ProductPassport::get($member_id);
			$rows[] = array(
				'post_id' => $member_id,
				'variant' => ($passport && isset($passport['variant']) && is_array($passport['variant'])) ? $passport['variant'] : array(),
			);
		}

		return $rows;
	}

	/**
	 * The family's pages as WP_Post objects, by post id. $published_only keeps
	 * published pages without a password.
	 *
	 * @return \WP_Post[]
	 */
	private static function queryMembers(int $id, bool $published_only, int $limit = self::MAX_MEMBERS): array
	{
		if ($id <= 0)
		{
			return array();
		}

		$posts = \get_posts(array(
			'post_type' => self::postTypes(),
			'post_status' => $published_only ? 'publish' : 'any',
			'tax_query' => array(array(
				'taxonomy' => VariantFamily::TAXONOMY,
				'field' => 'term_id',
				'terms' => array($id),
			)),
			'orderby' => 'ID',
			'order' => 'ASC',
			'numberposts' => $limit,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
		));

		if ($published_only)
		{
			$posts = array_values(array_filter($posts, function ($post) {
				return (string) $post->post_password === '';
			}));
		}

		return $posts;
	}

	/**
	 * The ids of the family's pages in any status except trash, at most $limit.
	 *
	 * @return int[]
	 */
	private static function queryMemberIds(int $id, int $limit = self::MAX_MEMBERS): array
	{
		if ($id <= 0)
		{
			return array();
		}

		$ids = \get_posts(array(
			'post_type' => self::postTypes(),
			'post_status' => 'any',
			'tax_query' => array(array(
				'taxonomy' => VariantFamily::TAXONOMY,
				'field' => 'term_id',
				'terms' => array($id),
			)),
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'numberposts' => $limit,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		));

		return array_map('intval', (array) $ids);
	}

	private static function term(int $id)
	{
		if ($id <= 0)
		{
			return null;
		}

		$term = \get_term($id, VariantFamily::TAXONOMY);

		return ($term instanceof \WP_Term) ? $term : null;
	}

	private static function requireTerm(int $id): void
	{
		if (!self::term($id))
		{
			throw new \InvalidArgumentException(sprintf('Family %d does not exist.', $id));
		}
	}

	// WordPress keeps term names HTML-escaped; callers get the plain text.
	private static function termName(\WP_Term $term): string
	{
		return \wp_specialchars_decode((string) $term->name, ENT_QUOTES);
	}

	/**
	 * @throws \InvalidArgumentException when the name is empty or longer than VariantFamily::MAX_NAME.
	 */
	private static function cleanName(string $name): string
	{
		$name = trim($name);
		$length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
		if ($name === '' || $length > VariantFamily::MAX_NAME)
		{
			throw new \InvalidArgumentException(sprintf('The family name must be 1 to %d characters.', VariantFamily::MAX_NAME));
		}

		return $name;
	}
}
