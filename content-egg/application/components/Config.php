<?php

namespace ContentEgg\application\components;

use ContentEgg\application\helpers\AdminHelper;
use ContentEgg\application\Plugin;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * Config class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
abstract class Config
{
    protected $page_slug;
    protected $option_name;
    protected $option_values = array();
    protected $options = array();
    protected $input = array();
    protected $out = array();
    private static $_instances = array();

    public static function getInstance($id = null)
    {
        $class = get_called_class();
        if ($id)
        {
            $instance_id = $id;
        }
        else
        {
            $instance_id = $class;
        }

        if (!isset(self::$_instances[$instance_id]))
        {
            self::$_instances[$instance_id] = new $class($id);
        }

        return self::$_instances[$instance_id];
    }

    protected function __construct()
    {
        $values = \get_option($this->option_name());

        // prevent call validators twice for first time. Settings API bug?
        if ($values === false)
        {
            \add_option($this->option_name(), '');
        }

        $this->option_name = $this->option_name();
        $this->options = $this->options();

        if ($values)
        {
            $this->option_values = $values;
        }
        else
        {
            foreach ($this->options as $key => $option)
            {
                $this->option_values[$key] = $this->get_default($key);
            }
        }

        $this->page_slug = $this->page_slug();
    }

    public function option($opt_name, $default = null)
    {
        if ($default !== null && !$this->option_exists($opt_name))
        {
            return $default;
        }

        return $this->get_current($opt_name);
    }

    public function adminInit()
    {
        global $pagenow;
        \add_action('admin_menu', array($this, 'add_admin_menu'));

        if ($pagenow == 'options.php' || (!empty($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) == $this->page_slug))
        {
            \add_action('admin_init', array($this, 'register_settings'));
        }
    }

    abstract public function page_slug();

    abstract public function option_name();

    abstract protected function options();

    abstract public function add_admin_menu();

    public function get_page_slug()
    {
        return $this->page_slug;
    }

    protected function get_default($option)
    {
        if (isset($this->options[$option]) && isset($this->options[$option]['default']))
        {
            return $this->options[$option]['default'];
        }
        else
        {
            return '';
        }
    }

    protected function get_validator($option)
    {
        if (isset($this->options[$option]) && isset($this->options[$option]['validator']))
        {
            return $this->options[$option]['validator'];
        }
        else
        {
            return null;
        }
    }

    public function get_current($option)
    {
        if (isset($this->option_values[$option]))
        {
            return $this->option_values[$option];
        }
        elseif ($this->option_values && $this->is_checkbox($option))
        {
            return false;
        }
        else
        {
            return $this->get_default($option);
        }
    }

    public function set_current($option, $value)
    {
        if (isset($this->option_values[$option]))
        {
            $this->option_values[$option] = $value;
        }
    }

    /**
     * Unconditionally sets an option value on this instance, keeping the
     * in-memory cache in sync after a direct option write (set_current()
     * skips keys absent from the cache, e.g. defined-but-never-stored
     * options).
     */
    public function setOptionValue($option, $value)
    {
        $this->option_values[$option] = $value;
    }

    public function register_settings()
    {
        \register_setting(
            $this->page_slug, // group, used for settings_fields()
            $this->option_name, // option name, used as key in database
            array($this, 'validate')      // validation callback
        );

        // reinit options for later plugin binding
        $this->options = $this->options();

        $sections = array();
        foreach ($this->options as $id => $field)
        {
            if (empty($field['title']))
            {
                $field['title'] = '';
            }
            if (empty($field['description']))
            {
                $field['description'] = '';
            }
            $params = array(
                'name' => $id, // value for 'name' attribute
                'title' => $field['title'],
                'description' => $field['description'],
                'value' => $this->get_current($id),
                'option_name' => $this->option_name,
                'label_for' => 'label-' . $id,
            );
            if (!empty($field['dropdown_options']))
            {
                $params['dropdown_options'] = $field['dropdown_options'];
            }
            if (!empty($field['checkbox_options']))
            {
                $params['checkbox_options'] = $field['checkbox_options'];
            }
            if (!empty($field['render_after']))
            {
                $params['render_after'] = $field['render_after'];
            }
            if (!empty($field['placeholder']))
            {
                $params['placeholder'] = $field['placeholder'];
            }
            if (empty($field['section']))
            {
                $field['section'] = 'default';
            }
            if (!empty($field['help_url']))
            {
                $params['help_url'] = $field['help_url'];
            }
            if (!empty($field['is_pro']))
            {
                $params['is_pro'] = true;
            }
            else
            {
                $params['is_pro'] = false;
            }
            // section
            if (!isset($sections[$field['section']]))
            {
                if ($field['section'] == 'default')
                {
                    $section_title = '';
                }
                else
                {
                    $section_title = $field['section'];
                }

                \add_settings_section(\sanitize_text_field($field['section']), $section_title, null, $this->page_slug);
                $sections[$field['section']] = $field['section'];
            }

            $title = $field['title'];

            if ($params['is_pro'] && !Plugin::isPro())
            {
                $title .= AdminHelper::getProFeatureWarning();
            }

            add_settings_field(
                $id,
                wp_kses(
                    $title,
                    array(
                        'span' => array(
                            'style' => true,
                            'class' => true,
                        ),
                    )
                ),
                $field['callback'],
                $this->page_slug,
                $field['section'],
                $params
            );
        }
    }

    public function render_input($args)
    {
        if (!empty($args['class']))
        {
            $class = $args['class'];
        }
        else
        {
            $class = 'regular-text ltr';
        }
        if (!empty($args['type']))
        {
            $type = $args['type'];
        }
        else
        {
            $type = 'text';
        }
        echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($args['option_name']) . '[' . esc_attr($args['name']) . ']" id="' . esc_attr($args['label_for']) . '" value="' . esc_attr($args['value']) . '" class="' . esc_attr($class) . '" />';
        if (!empty($args['render_after']))
        {
            echo wp_kses_post($args['render_after']);
        }
        if ($args['description'])
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
    }

    public function render_textarea($args)
    {
        echo '<textarea name="' . esc_attr($args['option_name']) . '['
            . esc_attr($args['name']) . ']" id="'
            . esc_attr($args['label_for'])
            . '" rows="3" class="large-text code"';

        if (!empty($args['placeholder']))
            echo ' placeholder="' . esc_attr($args['placeholder']) . '"';

        echo '>' . esc_html($args['value']) .
            '</textarea>';
        if (!empty($args['render_after']))
        {
            echo wp_kses_post($args['render_after']);
        }
        if ($args['description'])
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
    }

    public function render_password($args)
    {
        echo '<input type="password" name="' . esc_attr($args['option_name']) . '['
            . esc_attr($args['name']) . ']" id="'
            . esc_attr($args['label_for']) . '" value="'
            . esc_attr($args['value']) . '" class="regular-text" />';

        if (! empty($args['render_after']))
        {
            echo wp_kses_post($args['render_after']);
        }

        if (! empty($args['description']))
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
    }

    public function render_checkbox($args)
    {
        if ((bool) $args['value'])
        {
            $checked = ' checked="checked" ';
        }
        else
        {
            $checked = '';
        }
        echo '<label for="' . esc_attr($args['label_for']) . '">';
        echo '<input type="checkbox" name="' . esc_attr($args['option_name']) . '['
            . esc_attr($args['name']) . ']" id="'
            . esc_attr($args['label_for']) . '"';
        if ($checked)
            echo ' checked="checked" ';
        echo ' value="1" />';
        if ($args['description'])
        {
            echo ' ' . wp_kses_post($args['description']);
        }
        echo '</label>';
    }

    public function render_dropdown(array $args): void
    {
        $name       = "{$args['option_name']}[{$args['name']}]";
        $id         = (string) $args['label_for'];
        $value      = $args['value'];
        $fieldIsPro = ! empty($args['is_pro']);

        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '"'
            . disabled($fieldIsPro && ! Plugin::isPro(), true, false)
            . '>';

        foreach ($args['dropdown_options'] as $optValue => $optData)
        {
            if (is_array($optData))
            {
                $optLabel = $optData['label'] ?? '';
                $optPro   = ! empty($optData['is_pro']);
            }
            else
            {
                $optLabel = $optData;
                $optPro   = false;
            }

            $suffix = ($optPro && ! Plugin::isPro()) ? '<small>(Pro)</small>' : '';

            printf(
                '<option value="%s"%s%s>%s%s</option>',
                esc_attr((string) $optValue),
                selected($value, $optValue, false),
                disabled($optPro && ! Plugin::isPro(), true, false),
                esc_html($optLabel),
                $suffix ? wp_kses($suffix, array('small' => array())) : ''
            );
        }

        echo '</select>';

        if (! empty($args['render_after']))
        {
            echo wp_kses_post($args['render_after']);
        }

        if (! empty($args['description']))
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
    }
    public function render_checkbox_list($args)
    {
        if (empty($args['checkbox_options']))
        {
            echo '-';

            return;
        }

        echo '<div class="cegg-checkboxgroup">';

        if ($args['checkbox_options'] && is_array($args['checkbox_options']))
        {
            foreach ($args['checkbox_options'] as $value => $name)
            {
                if (in_array($value, $args['value']))
                {
                    $checked = ' checked="checked" ';
                }
                else
                {
                    $checked = '';
                }

                echo '<div class="cegg-checkbox">';
                echo '<label for="' . esc_attr($args['label_for'] . '-' . $value) . '">';
                echo '<input type="checkbox" name="' . esc_attr($args['option_name']) . '['
                    . esc_attr($args['name']) . '][' . esc_attr($value) . ']" id="'
                    . esc_attr($args['label_for'] . '-' . $value), '"';
                if ($checked)
                    echo ' checked="checked" ';
                echo ' value="' . esc_attr($value) . '" />';
                echo ' ' . esc_html($name);
                echo '</label>';
                echo '</div>';
            }
        }
        echo '</div>';
        if ($args['description'])
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
    }

    public function render_hidden($args)
    {
        echo '<input type="hidden" name="' . esc_attr($args['option_name']) . '['
            . esc_attr($args['name']) . '] value="'
            . esc_attr($args['value']) . '" />';
    }

    public function render_color_picker($args)
    {
        echo '<input name="' . esc_attr($args['option_name']) . '['
            . esc_attr($args['name']) . ']" id="'
            . esc_attr($args['label_for']) . '" value="'
            . esc_attr($args['value']) . '" />';
        if (!empty($args['render_after']))
        {
            echo wp_kses_post($args['render_after']);
        }
        if ($args['description'])
        {
            echo '<p class="description">' . wp_kses_post($args['description']) . '</p>';
        }
        \wp_enqueue_style('wp-color-picker');
        \wp_enqueue_script('wp-color-picker', \admin_url('js/color-picker.min.js'));
        echo '<script type="text/javascript">' . "jQuery(document).ready(function($){jQuery('#" . esc_attr($args['label_for']) . "').wpColorPicker();});" . '</script>';
    }

    public function render_text($args)
    {
        echo wp_kses_post($args['description']);
    }

    public function option_exists($option)
    {
        if (array_key_exists($option, $this->options))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function validate($input)
    {
        $this->input = $input;

        if (!is_array($this->input))
        {
            return;
        }

        foreach ($this->input as $option => $value)
        {
            if (!$this->option_exists($option))
            {
                continue;
            }

            // Preserve the historical quirk: a validator declared as a bare
            // (non-array) value skips the option entirely without writing out.
            $validator = $this->get_validator($option);
            if ($validator && !is_array($validator))
            {
                continue;
            }

            $res = $this->evaluateOptionValidators($option, $value);
            if (!$res['ok'])
            {
                \add_settings_error($option, $option, wp_kses_post((string) $res['error']));
                $value = $this->get_current($option);
                if (!empty($res['when']))
                {
                    $this->out[$res['when']] = $this->get_current($res['when']);
                }
            }
            else
            {
                $value = $res['value'];
            }
            $this->out[$option] = $value;
        }

        return $this->out;
    }

    /**
     * Runs the filter/validator chain for a single option against the current
     * submitted-value context ($this->input / $this->out), with NO side effects
     * (no add_settings_error, no is_active reverts). This is the shared core of
     * the admin validate() pipeline and the agent abilities layer, so the two
     * paths validate identically and cannot drift.
     *
     * @param bool $apply_filters When false, filter callbacks are skipped and
     *   only pass/fail validators run. Dry-run callers (activation readiness,
     *   patch validation) pass false because some filters persist data as a
     *   side effect (e.g. saveModuleName / processLinkIndexBackfiller); a
     *   read-only check must not trigger those. The unconditional leading
     *   trim() is always applied (it is pure).
     *
     * @return array{ok: bool, value: mixed, error: ?string, when: mixed}
     */
    public function evaluateOptionValidators($option, $value, $apply_filters = true)
    {
        if (!is_array($value))
        {
            $value = trim($value);
        }

        $validator = $this->get_validator($option);
        if (!$validator || !is_array($validator))
        {
            return array('ok' => true, 'value' => $value, 'error' => null, 'when' => null);
        }

        foreach ($validator as $v)
        {
            if (!is_array($v))
            {
                if ($v == 'allow_empty')
                {
                    if ($value === '')
                    {
                        break;
                    }
                    else
                    {
                        continue;
                    }
                }

                // filter (function name)
                if ($apply_filters)
                {
                    $value = call_user_func($v, $value);
                }
                continue;
            }

            // check 'when' condition
            if (!empty($v['when']))
            {
                if (!$this->evaluate_when_condition($v['when']))
                {
                    continue;
                }
            }

            if (!empty($v['type']) && $v['type'] == 'filter')
            {
                // filter
                if ($apply_filters)
                {
                    $value = call_user_func($v['call'], $value);
                }
                continue;
            }

            // validator
            if (empty($v['arg']))
            {
                $res = call_user_func($v['call'], $value);
            }
            else
            {
                $res = call_user_func($v['call'], $value, $v['arg']);
            }

            if (!$res)
            {
                return array(
                    'ok' => false,
                    'value' => $value,
                    'error' => isset($v['message']) ? $v['message'] : sprintf(
                        /* translators: %s: option key */
                        __('Invalid value for "%s".', 'content-egg'),
                        $option
                    ),
                    'when' => !empty($v['when']) ? $v['when'] : null,
                );
            }
        }

        return array('ok' => true, 'value' => $value, 'error' => null, 'when' => null);
    }

    /**
     * Agent-abilities entry point: validate a partial settings patch against the
     * current stored values WITHOUT persisting and WITHOUT admin side effects.
     * `when` conditions resolve against current values overlaid with the patch,
     * so activation-gated requirements evaluate correctly. Filters are not run
     * (see evaluateOptionValidators); callers own their own sanitization.
     *
     * @return array{values: array, errors: array<string,string>}
     */
    public function validatePatchValues(array $patch)
    {
        $saved_input = $this->input;
        $saved_out = $this->out;

        $this->input = array_merge($this->getOptionValues(), $patch);
        $this->out = array();

        $values = array();
        $errors = array();
        foreach ($patch as $key => $value)
        {
            $key = (string) $key;
            if (!$this->option_exists($key))
            {
                continue;
            }
            $res = $this->evaluateOptionValidators($key, $value, false);
            if ($res['ok'])
            {
                $values[$key] = $res['value'];
                $this->out[$key] = $res['value'];
            }
            else
            {
                $errors[$key] = (string) $res['error'];
            }
        }

        $this->input = $saved_input;
        $this->out = $saved_out;

        return array('values' => $values, 'errors' => $errors);
    }

    /**
     * Agent-abilities entry point: the options that currently BLOCK activation,
     * i.e. what the admin validate() pipeline would use to snap is_active back
     * to 0 when the module is turned on. Empty array => safe to activate.
     *
     * Mirrors the two admin activation gates: the is_active field's own
     * requirement validator, and any field whose conditional (`when`) validator
     * fails once is_active = 1 (e.g. a required API key). Read-only: filters are
     * not run.
     *
     * @return array<string,string> option key => reason message
     */
    public function getActivationBlockers()
    {
        $saved_input = $this->input;
        $saved_out = $this->out;

        $this->input = array_merge($this->getOptionValues(), array('is_active' => 1));
        $this->out = array();

        $blockers = array();
        foreach (array_keys($this->options()) as $option)
        {
            $option = (string) $option;
            $value = ($option === 'is_active') ? 1 : $this->get_current($option);
            $res = $this->evaluateOptionValidators($option, $value, false);
            if ($res['ok'])
            {
                continue;
            }
            // is_active's own validator (requirement check), or a conditional
            // validator that only fires because the module is being activated.
            if ($option === 'is_active' || !empty($res['when']))
            {
                $blockers[$option] = (string) $res['error'];
            }
        }

        $this->input = $saved_input;
        $this->out = $saved_out;

        return $blockers;
    }

    public function is_checkbox($option)
    {
        if (isset($this->options[$option]['callback'][1]) && $this->options[$option]['callback'][1] == 'render_checkbox')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Current submitted value
     */
    public function get_submitted_value($option, $input = array(), $out = array())
    {
        if (!$input)
        {
            $input = $this->input;
        }
        if (!$out)
        {
            $out = $this->out;
        }

        if (! $this->option_exists($option))
        {
            throw new \Exception(
                sprintf(
                    'Options "%s" does not exist.',
                    esc_html($option)
                )
            );
        }

        if (!isset($input[$option]) && $this->is_checkbox($option))
        {
            return false;
        }

        if (!isset($input[$option]))
        {
            throw new \Exception(
                sprintf(
                    'Options "%s" does not exist.',
                    esc_html($option)
                )
            );
        }

        if (isset($out[$option]))
        {
            return $out[$option];
        }
        else
        {
            return $input[$option];
        }
    }

    public function getOptionsList()
    {
        return array_keys($this->options());
    }

    public function getOptionValues()
    {
        $result = array();
        foreach ($this->getOptionsList() as $option_name)
        {
            $result[$option_name] = $this->get_current($option_name);
        }

        return $result;
    }

    /**
     * Machine-readable option metadata for the agent abilities layer:
     * plain-text titles/descriptions, defaults, sections and choice lists —
     * no HTML, no render callbacks.
     */
    public function getOptionsMeta()
    {
        $meta = array();
        foreach ($this->options() as $key => $def)
        {
            $entry = array(
                'title' => isset($def['title']) ? \wp_strip_all_tags((string) $def['title']) : (string) $key,
                'default' => isset($def['default']) ? $def['default'] : '',
            );

            if (!empty($def['description']) && is_string($def['description']))
            {
                $description = preg_replace('/<br\s*\/?>/i', ' ', $def['description']);
                $description = trim((string) preg_replace('/\s+/u', ' ', \wp_strip_all_tags($description)));
                $entry['description'] = \ContentEgg\application\helpers\TextHelper::truncate($description, 160);
            }

            if (isset($def['section']))
            {
                $entry['section'] = \wp_strip_all_tags((string) $def['section']);
            }

            foreach (array('dropdown_options', 'checkbox_options') as $choices_key)
            {
                if (isset($def[$choices_key]) && is_array($def[$choices_key]))
                {
                    $entry['choices'] = array_map(static function ($label)
                    {
                        return \wp_strip_all_tags((string) $label);
                    }, $def[$choices_key]);
                }
            }

            if (isset($def['callback']))
            {
                $callback_name = is_array($def['callback']) ? end($def['callback']) : $def['callback'];
                if ($callback_name === 'render_password')
                {
                    $entry['is_password'] = true;
                }
            }

            $meta[$key] = $entry;
        }

        return $meta;
    }

    protected function render_help_icon($args)
    {
        if (!empty($args['help_url']))
        {
            echo '&nbsp;';
            echo '<a class="ms-1" href="' . esc_url($args['help_url']) . '" target="_blank">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286m1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94"/></svg>';
            echo '</a>';
        }
    }

    public static function getOption($option, $default, $slug)
    {
        $options = get_option($slug, []);

        if (is_array($options) && array_key_exists($option, $options))
        {
            return $options[$option];
        }

        return $default !== '' ? $default : null;
    }

    public static function updateOption($option, $value, $slug)
    {
        $options = get_option($slug, []);
        $options[$option] = $value;

        return update_option($slug, $options);
    }

    /**
     * Evaluates validator "when" condition(s).
     *
     * Supported formats:
     * 1) string: 'is_active' (truthy check of that submitted field)
     * 2) array:  ['api_mode' => 'creators'] (field == value)
     * 3) array:  ['is_active' => 1, 'api_mode' => 'creators'] (AND)
     * 4) array:  ['api_mode' => ['paapi5', 'auto']] (field in list)
     *
     * @param mixed $when
     * @return bool
     */
    protected function evaluate_when_condition($when)
    {
        // Backward-compatible: string field name means "truthy"
        if (is_string($when))
        {
            $when_value = $this->get_submitted_value($when);
            return (bool) $when_value;
        }

        // New: associative array means field === expected (AND across all pairs)
        if (is_array($when))
        {
            foreach ($when as $field => $expected)
            {
                $actual = $this->get_submitted_value($field);

                // expected === true => require truthy
                if ($expected === true)
                {
                    if (!(bool) $actual)
                    {
                        return false;
                    }
                    continue;
                }

                // expected === false => require falsy
                if ($expected === false)
                {
                    if ((bool) $actual)
                    {
                        return false;
                    }
                    continue;
                }

                // expected is array => actual must be in list
                if (is_array($expected))
                {
                    // Compare as strings to avoid '1' vs 1 issues
                    $actual_str = is_scalar($actual) ? (string) $actual : '';
                    $expected_strs = array();
                    foreach ($expected as $e)
                    {
                        $expected_strs[] = is_scalar($e) ? (string) $e : '';
                    }
                    if (!in_array($actual_str, $expected_strs, true))
                    {
                        return false;
                    }
                    continue;
                }

                // Scalar expected => compare as strings (safe for posted form values)
                $expected_str = is_scalar($expected) ? (string) $expected : '';
                $actual_str   = is_scalar($actual) ? (string) $actual : '';

                if ($actual_str !== $expected_str)
                {
                    return false;
                }
            }

            return true;
        }

        // Unknown format: fail closed (don’t run validator)
        return false;
    }
}
