<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * CurrencyHelper class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class CurrencyHelper
{

    private $locale;
    protected $currencies = array();
    protected $locales = array();
    private static $instance = null;
    private static $currencyRates = array();

    public static function getInstance($locale = null)
    {
        if (self::$instance === null)
        {
            self::$instance = new CurrencyHelper($locale);
        }

        return self::$instance;
    }

    private function __construct($locale)
    {
        $this->setLocale($locale);
        $this->currencies = self::currencies();
        $this->locales = self::locales();
    }

    public static function locales()
    {
        return array(
            'en' => array(
                'thousand_sep' => ',',
                'decimal_sep' => '.',
            ),
            'nl' => array(
                'thousand_sep' => '.',
                'decimal_sep' => ',',
            ),
            'be' => array(
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
            ),
            'de' => array(
                'thousand_sep' => '.',
                'decimal_sep' => ',',
            ),
            'es' => array(
                'thousand_sep' => '.',
                'decimal_sep' => ',',
            ),
            'fr' => array(
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
            ),
            'it' => array(
                'thousand_sep' => '.',
                'decimal_sep' => ',',
            ),
            'ru' => array(
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
            ),
            'uk' => array(
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
            ),
        );
    }

    public static function currencies()
    {
        $currencies = array(
            'USD' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('United States dollar', 'content-egg-tpl'),
            ),
            'EUR' => array(
                'currency_symbol' => '&euro;',
                'currency_pos' => array(
                    'nl' => 'left',
                    'be' => 'left',
                    'de' => 'right',
                    'es' => 'right',
                    'fr' => 'right',
                    'it' => 'right',
                    'fi' => 'right',
                    'sk' => 'right',
                ),
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Euro', 'content-egg-tpl'),
            ),
            'CAD' => array(
                'currency_symbol' => 'C $',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Canadian dollar', 'content-egg-tpl'),
            ),
            'GBP' => array(
                'currency_symbol' => '&pound;',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('British pound', 'content-egg-tpl'),
            ),
            'JPY' => array(
                'currency_symbol' => '&yen;',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Japanese yen', 'content-egg-tpl'),
            ),
            'CNY' => array(
                'currency_symbol' => '&yen;',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Chinese yuan', 'content-egg-tpl'),
            ),
            'UAH' => array(
                'currency_symbol' => 'грн.',
                'currency_pos' => 'right_space',
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Ukrainian hryvnia', 'content-egg-tpl'),
            ),
            'INR' => array(
                'currency_symbol' => '₹',
                'currency_pos' => 'left_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Indian Rupee', 'content-egg-tpl'),
            ),
            'AUD' => array(
                'currency_symbol' => 'AU $',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Australian dollar', 'content-egg-tpl'),
            ),
            'VND' => array(
                'currency_symbol' => '&#8363;',
                'currency_pos' => 'right',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Vietnamese dong', 'content-egg-tpl'),
            ),
            'BRL' => array(
                'currency_symbol' => 'R$',
                'currency_pos' => 'left_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Brazilian real', 'content-egg-tpl'),
            ),
            'TND' => array(
                'currency_symbol' => 'DT',
                'currency_pos'    => 'right',
                //'thousand_sep'    => ' ',
                'thousand_sep'    => '',
                'decimal_sep'     => ',',
                'num_decimals'    => 3,
                'name'            => __('Tunisian dinar', 'content-egg-tpl'),
            ),
            'DZD' => array(
                'currency_symbol' => 'DA',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Algerian Dinar', 'content-egg-tpl'),
            ),
            'NGN' => array(
                'currency_symbol' => '₦',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Nigerian naira', 'content-egg-tpl'),
            ),
            'MXN' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Mexican peso', 'content-egg-tpl'),
            ),
            'MDL' => array(
                'currency_symbol' => 'lei',
                'currency_pos' => 'right_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Moldovan leu', 'content-egg-tpl'),
            ),
            'KRW' => array(
                'currency_symbol' => '₩',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('South Korean won', 'content-egg-tpl'),
            ),
            'THB' => array(
                'currency_symbol' => '฿',
                'currency_pos' => 'left_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Thai baht', 'content-egg-tpl'),
            ),
            'RON' => array(
                'currency_symbol' => 'Lei',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Romanian Leu', 'content-egg-tpl'),
            ),
            'EGP' => array(
                'currency_symbol' => 'EGP',
                'currency_pos' => 'right_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Egypt Pound', 'content-egg-tpl'),
            ),
            'KWD' => array(
                'currency_symbol' => 'KD',
                'currency_pos' => 'right_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 3,
                'name' => __('Kuwaiti dinar', 'content-egg-tpl'),
            ),
            'TRY' => array(
                'currency_symbol' => 'TL',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Turkish Lira', 'content-egg-tpl'),
            ),
            'IDR' => array(
                'currency_symbol' => 'Rp',
                'currency_pos' => 'left_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Indonesian Rupiah', 'content-egg-tpl'),
            ),
            'PKR' => array(
                'currency_symbol' => 'PKR.',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Pakistani Rupee', 'content-egg-tpl'),
            ),
            'HKD' => array(
                'currency_symbol' => 'HKD$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Hong Kong dollar', 'content-egg-tpl'),
            ),
            'ILS' => array(
                'currency_symbol' => '&#8362;',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Israeli Shekel', 'content-egg-tpl'),
            ),
            'AED' => array(
                'currency_symbol' => 'AED',
                'currency_pos' => 'right_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('UAE Dirham', 'content-egg-tpl'),
            ),
            'SAR' => array(
                'currency_symbol' => 'SAR',
                'currency_pos' => 'right_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Saudi Riyal', 'content-egg-tpl'),
            ),
            'SGD' => array(
                'currency_symbol' => 'S$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Singapore dollar', 'content-egg-tpl'),
            ),
            'HUF' => array(
                'currency_symbol' => 'Ft',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Hungarian forint', 'content-egg-tpl'),
            ),
            'PLN' => array(
                'currency_symbol' => 'zł',
                'currency_pos' => 'right_space',
                'thousand_sep' => '',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Polish Zloty', 'content-egg-tpl'),
            ),
            'CZK' => array(
                'currency_symbol' => 'Kč',
                'currency_pos' => 'right_space',
                'thousand_sep' => ' ',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Czech koruna', 'content-egg-tpl'),
            ),
            'MYR' => array(
                'currency_symbol' => 'RM',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Malaysia Ringgit', 'content-egg-tpl'),
            ),
            'PCT' => array(
                'currency_symbol' => '%',
                'currency_pos' => 'right',
                'thousand_sep' => '',
                'decimal_sep' => '.',
                'num_decimals' => 1,
                'name' => __('Percentage', 'content-egg-tpl'),
            ),
            'CLP' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 0,
                'name' => __('Peso Chileno', 'content-egg-tpl'),
            ),
            'DKK' => array(
                'currency_symbol' => 'DKK',
                'currency_pos' => 'left_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Danske Kroner', 'content-egg-tpl'),
            ),
            'KES' => array(
                'currency_symbol' => 'KSh',
                'currency_pos' => 'left_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Kenyan Shilling', 'content-egg-tpl'),
            ),
            'HRK' => array(
                'currency_symbol' => 'kn',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Croatian Kuna', 'content-egg-tpl'),
            ),
            'PEN' => array(
                'currency_symbol' => 'S/',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Peruvian sol', 'content-egg-tpl'),
            ),
            'DOP' => array(
                'currency_symbol' => 'RD$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Dominican Peso', 'content-egg-tpl'),
            ),
            'UYU' => array(
                'currency_symbol' => 'U$S',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Uruguayan Peso', 'content-egg-tpl'),
            ),
            'NIO' => array(
                'currency_symbol' => 'C$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Nicaraguan Córdoba', 'content-egg-tpl'),
            ),
            'PAB' => array(
                'currency_symbol' => 'B/.',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Panamanian Balboa', 'content-egg-tpl'),
            ),
            'SVC' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Salvadoran Colón', 'content-egg-tpl'),
            ),
            'GTQ' => array(
                'currency_symbol' => 'Q',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Guatemalan Quetzal', 'content-egg-tpl'),
            ),
            'HNL' => array(
                'currency_symbol' => 'L',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Honduran Lempira', 'content-egg-tpl'),
            ),
            'JMD' => array(
                'currency_symbol' => 'JM$',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Jamaican Dollar', 'content-egg-tpl'),
            ),
            'CRC' => array(
                'currency_symbol' => '₡',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Costa Rican Colón', 'content-egg-tpl'),
            ),
            'ARS' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Argentine Peso', 'content-egg-tpl'),
            ),
            'BOB' => array(
                'currency_symbol' => 'Bs',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Bolivian Boliviano', 'content-egg-tpl'),
            ),
            'COP' => array(
                'currency_symbol' => '$',
                'currency_pos' => 'left',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Colombian Peso', 'content-egg-tpl'),
            ),
            'XOF' => array(
                'currency_symbol' => 'FCFA',
                'currency_pos' => 'left_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('CFA Franc', 'content-egg-tpl'),
            ),
            'SEK' => array(
                'currency_symbol' => 'kr',
                'currency_pos' => 'right_space',
                'thousand_sep' => ' ',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Swedish Krona', 'content-egg-tpl'),
            ),
            'PHP' => array(
                'currency_symbol' => '₱',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 0,
                'name' => __('Philippine Peso', 'content-egg-tpl'),
            ),
            'JOD' => array(
                'currency_symbol' => 'JOD',
                'currency_pos' => 'right',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Jordanian Dinar', 'content-egg-tpl'),
            ),
            'NOK' => array(
                'currency_symbol' => 'NOK',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Norske kroner', 'content-egg-tpl'),
            ),
            'NZD' => array(
                'currency_symbol' => 'NZ $',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('New Zealand dollar', 'content-egg-tpl'),
            ),
            'LKR' => array(
                'currency_symbol' => 'Rs',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Sri Lankan Rupee', 'content-egg-tpl'),
            ),
            'ZAR' => array(
                'currency_symbol' => 'R',
                'currency_pos' => 'left',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('South African Rand', 'content-egg-tpl'),
            ),
            'UMO' => array(
                'currency_symbol' => '$%PRICE% / ' . __('mo', 'content-egg-tpl'),
                'currency_pos' => 'pattern',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('USD/month', 'content-egg-tpl'),
            ),
            'BGN' => array(
                'currency_symbol' => 'лв.',
                'currency_pos' => 'right_space',
                'thousand_sep' => '.',
                'decimal_sep' => ',',
                'num_decimals' => 2,
                'name' => __('Bulgarian Lev', 'content-egg-tpl'),
            ),
            'BDT' => array(
                'currency_symbol' => '৳',
                'currency_pos' => 'right',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Bangladeshi Taka', 'content-egg-tpl'),
            ),
            'NPR' => array(
                'currency_symbol' => '₨',
                'currency_pos' => 'left_space',
                'thousand_sep' => ',',
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Nepalese Rupee', 'content-egg-tpl'),
            ),
            'CHF' => array(
                'currency_symbol' => 'CHF',
                'currency_pos' => 'right_space',
                'thousand_sep' => "'",
                'decimal_sep' => '.',
                'num_decimals' => 2,
                'name' => __('Swiss Franc', 'content-egg-tpl'),
            ),
        );

        return \apply_filters('cegg_currencies', $currencies);
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    private function getValue($currency, $key, $default = null)
    {
        if (isset($this->currencies[$currency]) && isset($this->currencies[$currency][$key]))
        {
            $value = $this->currencies[$currency][$key];
        }
        else
        {
            $value = null;
        }

        if (!is_null($value) && is_scalar($value) && $currency == 'MXN')
        {
            return $value;
        }

        if (is_array($value) && isset($value[$this->locale]))
        {
            return $value[$this->locale];
        }
        elseif (isset($this->locales[$this->locale]) && isset($this->locales[$this->locale][$key]))
        {
            return $this->locales[$this->locale][$key];
        }
        elseif (is_array($value))
        {
            return reset($value);
        } // first value
        elseif (is_scalar($value) && !is_null($value))
        {
            return $value;
        }
        else
        {
            return $default;
        }
    }

    public function currencyFormat($amount, $currency, $thousand_sep = null, $decimal_sep = null, $before_symbol = '', $after_symbol = '')
    {
        $amount = $this->numberFormat($amount, $currency, $thousand_sep, $decimal_sep);
        $symbol = \apply_filters('cegg_currency_symbol', $this->getSymbol($currency), $currency);
        $currency_pos = $this->getCurrencyPos($currency);
        $symbol = $before_symbol . $symbol . $after_symbol;
        switch ($currency_pos)
        {
            case 'left_space':
                return $symbol . ' ' . $amount;
            case 'left':
                return $symbol . $amount;
            case 'right_space':
                return $amount . ' ' . $symbol;
            case 'right':
                return $amount . $symbol;
            case 'pattern':
                return str_replace('%PRICE%', $amount, $symbol);
            default:
                return $symbol . ' ' . $amount;
        }
    }

    public function getCurrencyPos($currency, $default = 'left_space')
    {
        return $this->getValue($currency, 'currency_pos', $default);
    }

    public function getSymbol($currency)
    {
        return $this->getValue($currency, 'currency_symbol', $currency);
    }

    public function getName($currency)
    {
        return $this->getValue($currency, 'name', $currency);
    }

    public function numberFormat($number, $currency, $thousand_sep = null, $decimal_sep = null, $num_decimals = null)
    {

        if (!$thousand_sep)
        {
            $thousand_sep = $this->getValue($currency, 'thousand_sep', ',');
        }
        if (!$decimal_sep)
        {
            $decimal_sep = $this->getValue($currency, 'decimal_sep', '.');
        }
        if (!$num_decimals)
        {
            $num_decimals = $this->getValue($currency, 'num_decimals', 2);
        }

        return number_format((float) $number, absint($num_decimals), $decimal_sep, $thousand_sep);
    }

    public static function getCurrenciesList()
    {
        $list = array_keys(self::currencies());
        sort($list);

        return $list;
    }

    /**
     * @link: http://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html#dev
     */
    public static function queryCurrencyRateEcb($from, $to, $force = false)
    {
        $transient_name = 'cegg-currency-rates-ecb';
        $rates = \get_transient($transient_name);

        if ($rates === false || $force)
        {
            $url = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
            $params = array(
                'timeout' => 15,
                'user-agent' => 'Content Egg WP Plugin (https://www.keywordrush.com/contentegg)'
            );
            $response = \wp_remote_get($url, $params);
            $rates = array();
            if ($response && !\is_wp_error($response))
            {
                $results = TextHelper::unserialize_xml(\wp_remote_retrieve_body($response));
                if (!isset($results['Cube']['Cube']['Cube']))
                {
                    return 0;
                }
                foreach ($results['Cube']['Cube']['Cube'] as $r)
                {
                    $rates[$r['@attributes']['currency']] = (float) $r['@attributes']['rate'];
                }
            }
            \set_transient($transient_name, $rates, 6 * 3600);
        }

        if ($from == 'EUR' && isset($rates[$to]))
        {
            return $rates[$to];
        }
        elseif ($to == 'EUR' && isset($rates[$from]))
        {
            return 1 / $rates[$from];
        }
        elseif (isset($rates[$from]) && isset($rates[$to]))
        {
            return $rates[$to] / $rates[$from];
        }
        else
        {
            return 0;
        }
    }

    public static function queryCurrencyRate($from, $to)
    {
        return self::queryCurrencyRateEcb($from, $to);
    }

    public static function getCurrencyRate($from, $to)
    {
        if ($rate = \apply_filters('content_egg_currency_rate', 0, $from, $to))
        {
            return $rate;
        }

        $transient_name = 'currency-rate-' . $from . $to;
        if (!isset(self::$currencyRates[$transient_name]))
        {
            $rate = \get_transient($transient_name);
            if ($rate === false)
            {
                $rate = self::queryCurrencyRate($from, $to);
                \set_transient($transient_name, $rate, 24 * 3600);
            }
            self::$currencyRates[$transient_name] = $rate;
        }

        return self::$currencyRates[$transient_name];
    }

    public static function convertCurrency($price, $from, $to)
    {
        if (!$rate = CurrencyHelper::getCurrencyRate($from, $to))
            return $price;

        return round($price * $rate, 2);
    }
}
