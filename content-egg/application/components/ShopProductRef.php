<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * ShopProductRef class file
 *
 * Parses the clipboard payload produced by the products modal into the product
 * references a coupon binds to.
 *
 * The shape is not invented here: {module_id, unique_id} is what
 * CopyAllRefsButton emits and what CeProductResolver consumes, so one paste
 * target accepts both the per-row copy and "Copy all product references".
 *
 * post_id is deliberately absent. A promotion is real wherever its product
 * appears, so binding to one post would drop the coupon from a roundup that
 * lists the same product.
 *
 * Pure: no WordPress, no database.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopProductRef
{
    /**
     * Whatever the operator pasted => a deduped list of references.
     *
     * Accepts three shapes, because an operator can reasonably paste any of
     * them: the full copy payload, a bare list of refs, or a single object of
     * either kind. Anything unrecognisable yields array(), which the caller
     * reports as a save error rather than storing silently.
     */
    public static function parse($input)
    {
        $rows = self::decode($input);

        $out = array();

        foreach ($rows as $row)
        {
            if (!is_array($row))
                continue;

            if (!$ref = self::one($row))
                continue;

            $key = $ref['module_id'] . '|' . $ref['unique_id'];

            if (isset($out[$key]))
                continue;

            $out[$key] = $ref;
        }

        return array_values($out);
    }

    /**
     * The orig_url values in the payload.
     *
     * Used once, at save time, to tell the operator that the product they
     * pasted belongs to a different shop - the one mistake this feature cannot
     * otherwise report, because a cross-domain binding renders nothing and says
     * nothing. Not stored.
     */
    public static function urls($input)
    {
        $out = array();

        foreach (self::decode($input) as $row)
        {
            if (!is_array($row) || empty($row['orig_url']))
                continue;

            $url = trim((string) $row['orig_url']);

            if ($url !== '' && !in_array($url, $out, true))
                $out[] = $url;
        }

        return $out;
    }

    /**
     * The payload as a list of rows, whatever shape it arrived in.
     */
    private static function decode($input)
    {
        $input = trim((string) $input);

        if ($input === '')
            return array();

        $decoded = json_decode($input, true);

        if (!is_array($decoded))
            return array();

        // A single object rather than a list of them.
        if (isset($decoded['product_ref']) || isset($decoded['module_id']) || isset($decoded['unique_id']))
            return array($decoded);

        return $decoded;
    }

    /**
     * One row => one reference, or null when it names no product.
     */
    private static function one(array $row)
    {
        $src = isset($row['product_ref']) && is_array($row['product_ref'])
            ? $row['product_ref']
            : $row;

        $module_id = isset($src['module_id']) ? trim((string) $src['module_id']) : '';
        $unique_id = isset($src['unique_id']) ? trim((string) $src['unique_id']) : '';

        if ($module_id === '' || $unique_id === '')
            return null;

        // Display only, never matched on: a title can change in the shop while
        // the reference stays valid.
        //
        // Either key. `title` is what the copy action sends; `label` is what we
        // store, and the edit screen puts the STORED shape back in the textarea
        // - so reading only `title` quietly emptied the label of every existing
        // binding the first time its shop was saved again.
        $label = isset($row['title']) ? trim((string) $row['title']) : '';

        if ($label === '' && isset($row['label']))
            $label = trim((string) $row['label']);

        return array(
            'module_id' => $module_id,
            'unique_id' => $unique_id,
            'label' => $label,
        );
    }
}
