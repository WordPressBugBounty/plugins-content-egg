<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

/**
 * Page-level JSON-LD schema collector.
 *
 * Blocks call static methods during render to register schema entries.
 * On wp_footer, all entries are deduplicated and emitted as a single
 * <script type="application/ld+json"> with a @graph array.
 *
 * Product dedup key: "module_id:unique_id"
 * FAQ: all items merged into one FAQPage entity.
 * Positional tracking: last registered product key is remembered so
 * enrichment blocks (pros-cons, rating-breakdown) without product_ref
 * attach to the correct product.
 */
final class EggbSchemaCollector
{
    /** @var array<string, array> Product schemas keyed by "module_id:unique_id" */
    private static array $products = [];

    /** @var array FAQ question items (merged across all FAQ blocks) */
    private static array $faqItems = [];

    /** @var string|null Last registered product key for positional enrichment */
    private static ?string $lastProductKey = null;

    /** @var bool Whether wp_footer hook is registered */
    private static bool $hooked = false;

    /**
     * Register the wp_footer hook. Safe to call multiple times.
     */
    public static function init(): void
    {
        if (self::$hooked)
        {
            return;
        }

        add_action('wp_footer', [self::class, 'flush'], 99);
        self::$hooked = true;
    }

    /**
     * Register a base Product schema from a product-card block.
     *
     * @param array $productRef ['module_id' => string, 'unique_id' => string]
     * @param array $data       ['name', 'description', 'image', 'score', 'url', 'merchant', 'price', 'currency']
     */
    public static function addProduct(array $productRef, array $data): void
    {
        $key = self::productKey($productRef);
        if ($key === null)
        {
            return;
        }

        self::$lastProductKey = $key;

        if (isset(self::$products[$key]))
        {
            return; // First product-card wins for base data
        }

        $schema = [
            '@type' => 'Product',
            'name'  => (string) ($data['name'] ?? ''),
        ];

        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '')
        {
            $schema['description'] = $description;
        }

        $image = trim((string) ($data['image'] ?? ''));
        if ($image !== '')
        {
            $schema['image'] = $image;
        }

        $score = $data['score'] ?? '';
        if ($score !== '' && is_numeric($score) && (float) $score > 0)
        {
            $schema['review'] = [
                '@type'        => 'Review',
                'reviewRating' => [
                    '@type'       => 'Rating',
                    'ratingValue' => (string) $score,
                    'bestRating'  => '10',
                ],
            ];
        }

        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '')
        {
            $schema['url'] = $url;
        }

        $price = trim((string) ($data['price'] ?? ''));
        $currency = trim((string) ($data['currency'] ?? ''));
        $merchant = trim((string) ($data['merchant'] ?? ''));
        if ($price !== '')
        {
            $offer = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency !== '' ? $currency : 'USD',
            ];
            if ($url !== '')
            {
                $offer['url'] = $url;
            }
            if ($merchant !== '')
            {
                $offer['seller'] = [
                    '@type' => 'Organization',
                    'name'  => $merchant,
                ];
            }
            $schema['offers'] = $offer;
        }

        self::$products[$key] = $schema;
    }

    /**
     * Enrich an existing product with pros/cons.
     * Attaches to the last registered product if no productRef given.
     *
     * @param array      $pros       Array of pro strings
     * @param array      $cons       Array of con strings
     * @param array|null $productRef Optional explicit product reference
     */
    public static function enrichProsCons(array $pros, array $cons, ?array $productRef = null): void
    {
        $key = $productRef !== null ? self::productKey($productRef) : self::$lastProductKey;
        if ($key === null || !isset(self::$products[$key]))
        {
            return;
        }

        if (!isset(self::$products[$key]['review']))
        {
            self::$products[$key]['review'] = [
                '@type' => 'Review',
            ];
        }

        if (!empty($pros))
        {
            self::$products[$key]['review']['positiveNotes'] = [
                '@type'           => 'ItemList',
                'itemListElement' => array_map(function (string $text, int $i) {
                    return [
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => $text,
                    ];
                }, array_values($pros), array_keys(array_values($pros))),
            ];
        }

        if (!empty($cons))
        {
            self::$products[$key]['review']['negativeNotes'] = [
                '@type'           => 'ItemList',
                'itemListElement' => array_map(function (string $text, int $i) {
                    return [
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => $text,
                    ];
                }, array_values($cons), array_keys(array_values($cons))),
            ];
        }
    }

    /**
     * Enrich an existing product with aggregate rating.
     * Attaches to the last registered product if no productRef given.
     *
     * @param string     $ratingValue Overall score
     * @param string     $bestRating  Scale max (default "10")
     * @param array|null $productRef  Optional explicit product reference
     */
    public static function enrichRating(string $ratingValue, string $bestRating = '10', ?array $productRef = null): void
    {
        $key = $productRef !== null ? self::productKey($productRef) : self::$lastProductKey;
        if ($key === null || !isset(self::$products[$key]))
        {
            return;
        }

        if (!is_numeric($ratingValue) || (float) $ratingValue <= 0)
        {
            return;
        }

        self::$products[$key]['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => $ratingValue,
            'bestRating'  => $bestRating,
            'ratingCount' => '1',
        ];
    }

    /**
     * Add FAQ items (merged across all FAQ blocks on the page).
     *
     * @param array $items Array of ['question' => string, 'answer_text' => string]
     */
    public static function addFaqItems(array $items): void
    {
        foreach ($items as $item)
        {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer_text'] ?? ''));
            if ($question !== '' && $answer !== '')
            {
                self::$faqItems[] = [
                    'question'    => $question,
                    'answer_text' => $answer,
                ];
            }
        }
    }

    /**
     * Emit all collected schemas as a single JSON-LD script tag.
     * Called on wp_footer.
     */
    public static function flush(): void
    {
        $graph = [];

        $author = self::resolveReviewAuthor();

        foreach (self::$products as $product)
        {
            if (($product['name'] ?? '') !== '')
            {
                if (isset($product['review']) && !isset($product['review']['author']))
                {
                    $product['review']['author'] = $author;
                }
                $graph[] = $product;
            }
        }

        if (!empty(self::$faqItems))
        {
            $entities = [];
            foreach (self::$faqItems as $item)
            {
                $entities[] = [
                    '@type' => 'Question',
                    'name'  => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $item['answer_text'],
                    ],
                ];
            }
            $graph[] = [
                '@type'      => 'FAQPage',
                'mainEntity' => $entities,
            ];
        }

        if (empty($graph))
        {
            self::reset();
            return;
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];

        echo '<script type="application/ld+json">'
            . wp_json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>' . "\n";

        self::reset();
    }

    /**
     * Reset state (for testability and after flush).
     */
    public static function reset(): void
    {
        self::$products = [];
        self::$faqItems = [];
        self::$lastProductKey = null;
    }

    private static function resolveReviewAuthor(): array
    {
        if (is_singular()) {
            $name = (string) get_the_author_meta('display_name', (int) get_post_field('post_author', get_the_ID()));
            if ($name !== '') {
                return ['@type' => 'Person', 'name' => $name];
            }
        }
        return ['@type' => 'Organization', 'name' => get_bloginfo('name')];
    }

    private static function productKey(?array $productRef): ?string
    {
        if ($productRef === null)
        {
            return null;
        }

        $moduleId = trim((string) ($productRef['module_id'] ?? ''));
        $uniqueId = trim((string) ($productRef['unique_id'] ?? ''));
        if ($moduleId === '' || $uniqueId === '')
        {
            return null;
        }

        return $moduleId . ':' . $uniqueId;
    }
}
