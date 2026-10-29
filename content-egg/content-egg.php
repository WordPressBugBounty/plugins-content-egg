<?php

namespace ContentEgg;

/*
  Plugin Name: Content Egg
  Plugin URI: https://www.keywordrush.com/contentegg
  Description: Multi-network affiliate plugin — imports, price comparison, automation, and structured blocks for affiliate content.
  Version: 12.1.0
  Requires at least: 6.9
  Requires PHP: 8.0
  Tested up to: 7.1.4
  Author: keywordrush.com
  Author URI: https://www.keywordrush.com
  Text Domain: content-egg
  Domain Path: /languages
 */

/*
 * Copyright (c)  www.keywordrush.com  (email: support@keywordrush.com)
 */

defined('\ABSPATH') || die('No direct script access allowed!');

define(__NAMESPACE__ . '\NS', __NAMESPACE__ . '\\');
define(NS . 'PLUGIN_PATH', \plugin_dir_path(__FILE__));
define(NS . 'PLUGIN_FILE', __FILE__);
define(NS . 'PLUGIN_DIR_URL', \plugins_url('', __FILE__));
define(NS . 'PLUGIN_RES', \plugins_url('res', __FILE__));
define(NS . 'CUSTOM_MODULES_DIR', 'content-egg-modules');

// Bundled Action Scheduler, so background jobs never depend on another plugin
// shipping it. Included unconditionally and before plugins_loaded on purpose:
// this only registers our copy's version (plugins_loaded, priority 0), and the
// library itself loads the newest registered copy at priority 1. A
// function_exists() guard would keep this copy out of that choice.
require_once PLUGIN_PATH . 'libraries/action-scheduler/action-scheduler.php';

require_once PLUGIN_PATH . 'loader.php';

\add_action('plugins_loaded', array('\ContentEgg\application\Plugin', 'registerComponents'));
\add_action('init', array('\ContentEgg\application\Plugin', 'getInstance'));

if (\is_admin())
{
  \register_activation_hook(__FILE__, array(\ContentEgg\application\Installer::getInstance(), 'activate'));
  \register_deactivation_hook(__FILE__, array(\ContentEgg\application\Installer::getInstance(), 'deactivate'));
  \register_uninstall_hook(__FILE__, array('\ContentEgg\application\Installer', 'uninstall'));

  \add_action('init', array('\ContentEgg\application\admin\PluginAdmin', 'getInstance'));
}
