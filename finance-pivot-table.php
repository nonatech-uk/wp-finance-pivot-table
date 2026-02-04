<?php
/**
 * Plugin Name: Finance Pivot Table
 * Plugin URI: https://github.com/nonatech-uk/wp-finance-pivot-table
 * Description: Interactive pivot table for displaying financial data from CSV files. Use shortcode [finance_pivot_table].
 * Version: 1.3.0
 * Author: NonaTech Services Ltd
 * License: GPL v2 or later
 * Text Domain: finance-pivot-table
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FINANCE_PIVOT_TABLE_VERSION', '1.3.0');
define('FINANCE_PIVOT_TABLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FINANCE_PIVOT_TABLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include class files
require_once FINANCE_PIVOT_TABLE_PLUGIN_DIR . 'includes/class-finance-data-loader.php';
require_once FINANCE_PIVOT_TABLE_PLUGIN_DIR . 'includes/class-finance-pivot-table.php';
require_once FINANCE_PIVOT_TABLE_PLUGIN_DIR . 'includes/class-finance-pivot-table-updater.php';

// Initialize plugin
function finance_pivot_table_init() {
    $plugin = new Finance_Pivot_Table();
    $plugin->init();

    // Initialize updater
    new Finance_Pivot_Table_Updater(
        __FILE__,
        'nonatech-uk/wp-finance-pivot-table',
        FINANCE_PIVOT_TABLE_VERSION
    );
}
add_action('plugins_loaded', 'finance_pivot_table_init');

// Activation hook - set default options
function finance_pivot_table_activate() {
    if (get_option('finance_pivot_table_data_dir') === false) {
        add_option('finance_pivot_table_data_dir', '/var/www/html/wp-content/uploads/public-docs/Finance/data');
    }
}
register_activation_hook(__FILE__, 'finance_pivot_table_activate');
