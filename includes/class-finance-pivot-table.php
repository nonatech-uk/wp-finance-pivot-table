<?php
/**
 * Main Finance Pivot Table class
 * Handles shortcode registration, admin settings, and asset loading
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finance_Pivot_Table {

    private $data_loader;
    private $has_shortcode = false;

    public function __construct() {
        $this->data_loader = new Finance_Data_Loader();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Shortcode
        add_shortcode('finance_pivot_table', array($this, 'render_shortcode'));

        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Assets - enqueue on wp_enqueue_scripts, but only output if shortcode present
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_footer', array($this, 'maybe_enqueue_assets'), 1);
    }

    /**
     * Register assets (but don't enqueue yet)
     */
    public function register_assets() {
        wp_register_style(
            'finance-pivot-table',
            FINANCE_PIVOT_TABLE_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            FINANCE_PIVOT_TABLE_VERSION
        );

        wp_register_script(
            'finance-pivot-table',
            FINANCE_PIVOT_TABLE_PLUGIN_URL . 'assets/js/pivot-table.js',
            array(),
            FINANCE_PIVOT_TABLE_VERSION,
            true
        );

        wp_register_script(
            'finance-pivot-table-app',
            FINANCE_PIVOT_TABLE_PLUGIN_URL . 'assets/js/app.js',
            array('finance-pivot-table'),
            FINANCE_PIVOT_TABLE_VERSION,
            true
        );
    }

    /**
     * Enqueue assets only if shortcode was used
     */
    public function maybe_enqueue_assets() {
        if ($this->has_shortcode) {
            wp_enqueue_style('finance-pivot-table');
            wp_enqueue_script('finance-pivot-table');
            wp_enqueue_script('finance-pivot-table-app');

            // Load and pass data to JavaScript
            $data_dir = get_option('finance_pivot_table_data_dir', '/var/www/html/wp-content/uploads/public-docs/Finance/data');
            $data = $this->data_loader->get_financial_data($data_dir);

            wp_localize_script('finance-pivot-table-app', 'FinancePivotData', $data);
        }
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        $this->has_shortcode = true;

        // Check if data directory is configured and accessible
        $data_dir = get_option('finance_pivot_table_data_dir', '/var/www/html/wp-content/uploads/public-docs/Finance/data');

        if (empty($data_dir)) {
            return '<div class="finance-pivot-error">Finance Pivot Table: No data directory configured. Please configure in Settings &rarr; Finance Pivot Table.</div>';
        }

        if (!is_dir($data_dir)) {
            return '<div class="finance-pivot-error">Finance Pivot Table: Data directory not found: ' . esc_html($data_dir) . '</div>';
        }

        // Check for CSV files
        $csv_files = glob($data_dir . '/*.{csv,CSV}', GLOB_BRACE);
        if (empty($csv_files)) {
            return '<div class="finance-pivot-error">Finance Pivot Table: No CSV files found in ' . esc_html($data_dir) . '</div>';
        }

        // Output the container HTML
        ob_start();
        include FINANCE_PIVOT_TABLE_PLUGIN_DIR . 'templates/shortcode-output.php';
        return ob_get_clean();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Parish Finance Settings',
            'Parish Finance',
            'manage_options',
            'finance-pivot-table',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'finance_pivot_table_settings',
            'finance_pivot_table_data_dir',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_data_dir'),
                'default' => '/var/www/html/wp-content/uploads/public-docs/Finance/data'
            )
        );

        add_settings_section(
            'finance_pivot_table_main',
            'Data Source Settings',
            array($this, 'render_settings_section'),
            'finance-pivot-table'
        );

        add_settings_field(
            'finance_pivot_table_data_dir',
            'CSV Data Directory',
            array($this, 'render_data_dir_field'),
            'finance-pivot-table',
            'finance_pivot_table_main'
        );
    }

    /**
     * Sanitize data directory input
     */
    public function sanitize_data_dir($value) {
        // Remove trailing slash
        return rtrim(sanitize_text_field($value), '/');
    }

    /**
     * Render settings section description
     */
    public function render_settings_section() {
        echo '<p>Configure the location of your CSV financial data files.</p>';
    }

    /**
     * Render data directory field
     */
    public function render_data_dir_field() {
        $value = get_option('finance_pivot_table_data_dir', '/var/www/html/wp-content/uploads/public-docs/Finance/data');
        ?>
        <input type="text"
               name="finance_pivot_table_data_dir"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="/data/finance">
        <p class="description">
            Absolute path to the directory containing CSV files (inside the container).<br>
            Example: <code>/data/finance</code> if host directory is mounted there.
        </p>
        <?php

        // Show status of current directory
        if (!empty($value)) {
            if (is_dir($value)) {
                $csv_files = glob($value . '/*.{csv,CSV}', GLOB_BRACE);
                $count = count($csv_files);
                echo '<p style="color: green;">&#10004; Directory exists. Found ' . $count . ' CSV file(s).</p>';

                if ($count > 0) {
                    echo '<details><summary>Files found:</summary><ul>';
                    foreach ($csv_files as $file) {
                        echo '<li>' . esc_html(basename($file)) . '</li>';
                    }
                    echo '</ul></details>';
                }
            } else {
                echo '<p style="color: red;">&#10008; Directory not found: ' . esc_html($value) . '</p>';
            }
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('finance_pivot_table_settings');
                do_settings_sections('finance-pivot-table');
                submit_button('Save Settings');
                ?>
            </form>

            <hr>

            <h2>Usage</h2>
            <p>Add the following shortcode to any page or post:</p>
            <pre><code>[finance_pivot_table]</code></pre>

            <h2>CSV File Format</h2>
            <p>The plugin reads CSV files with the following expected columns:</p>
            <pre><code>type, date, payee, reference, vat, centre, centre_name, account, account_name, amount, total_amount, detail, bankivity_category</code></pre>

            <h3>Filename Conventions</h3>
            <p>The fiscal year and data currency are extracted from filenames:</p>
            <ul>
                <li><code>Receipts and Payments 2022-3.CSV</code> &rarr; Year 2022/23, Complete</li>
                <li><code>Cashbook Report 30-11-2025.CSV</code> &rarr; Year 2025/26, Data to 30 Nov 2025</li>
            </ul>
        </div>
        <?php
    }
}
