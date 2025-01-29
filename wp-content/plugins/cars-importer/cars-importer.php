<?php
/*
Plugin Name: Auto CSV Importer
Description: Import auto data from CSV into wp_autos table
Version: 1.0
Author: Hamy Vosugh
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register activation hook
register_activation_hook(__FILE__, 'auto_importer_activate');

function auto_importer_activate() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}autos (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `category_id` bigint(20) UNSIGNED DEFAULT NULL,
        `category_name` varchar(255) DEFAULT NULL,
        `body_type` varchar(100) DEFAULT NULL,
        `manufacturer` varchar(100) DEFAULT NULL,
        `model` varchar(100) DEFAULT NULL,
        `power_kw` int(11) DEFAULT NULL,
        `vin` varchar(50) DEFAULT NULL,
        `color` varchar(50) DEFAULT NULL,
        `condition_id` tinyint(4) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `status` tinyint(4) DEFAULT NULL,
        `currency` varchar(10) DEFAULT NULL,
        `vat_rate` decimal(5,2) DEFAULT NULL,
        `featured` tinyint(1) DEFAULT '0',
        `sold` tinyint(1) DEFAULT '0',
        `price` decimal(10,2) DEFAULT NULL,
        `vat_amount` decimal(10,2) DEFAULT NULL,
        `power_hp` int(11) DEFAULT NULL,
        `net_price` decimal(10,2) DEFAULT NULL,
        `finance_bank` varchar(100) DEFAULT NULL,
        `finance_rate` decimal(4,2) DEFAULT NULL,
        `country_code` varchar(2) DEFAULT NULL,
        `video_url` varchar(255) DEFAULT NULL,
        `energy_rating` varchar(5) DEFAULT NULL,
        `fuel_type` varchar(50) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add menu item to WordPress admin
add_action('admin_menu', 'auto_importer_menu');

function auto_importer_menu() {
    add_menu_page(
        'Auto Importer',
        'Auto Importer',
        'manage_options',
        'auto-importer',
        'auto_importer_page'
    );
}

// Create the admin page
function auto_importer_page() {
    ?>
    <div class="wrap">
        <h2>Auto CSV Importer</h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="import_autos_csv" />
            <?php wp_nonce_field('import_autos_csv', 'auto_importer_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="csv_file">Select CSV File</label></th>
                    <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required /></td>
                </tr>
            </table>
            <?php submit_button('Import CSV'); ?>
        </form>
    </div>
    <?php
}

// Handle the CSV import
add_action('admin_post_import_autos_csv', 'handle_csv_import');

function handle_csv_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    check_admin_referer('import_autos_csv', 'auto_importer_nonce');

    if (!isset($_FILES['csv_file'])) {
        wp_redirect(admin_url('admin.php?page=auto-importer&error=nofile'));
        exit;
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('admin.php?page=auto-importer&error=upload'));
        exit;
    }

    global $wpdb;
    $handle = fopen($file['tmp_name'], 'r');
    $imported = 0;

    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        // Skip empty rows
        if (empty($data[0])) continue;

        $wpdb->insert(
            $wpdb->prefix . 'autos',
            array(
                'category_id' => $data[0],
                'category_name' => $data[1],
                'body_type' => $data[2],
                'manufacturer' => $data[3],
                'model' => $data[4],
                'power_kw' => $data[5],
                'color' => $data[16],
                'description' => $data[20],
                'price' => $data[107],
                'vat_rate' => $data[23],
                // Add more fields as needed
            ),
            array(
                '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f'
            )
        );
        $imported++;
    }
    fclose($handle);

    wp_redirect(admin_url('admin.php?page=auto-importer&imported=' . $imported));
    exit;
}

// Register shortcode for frontend upload form
add_shortcode('auto_csv_upload', 'auto_csv_upload_shortcode');

function auto_csv_upload_shortcode() {
    if (!current_user_can('manage_options')) {
        return 'Unauthorized access';
    }

    ob_start();
    ?>
    <div class="auto-csv-upload-form">
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="import_autos_csv" />
            <?php wp_nonce_field('import_autos_csv', 'auto_importer_nonce'); ?>
            
            <div class="form-group">
                <label for="csv_file">Select CSV File:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
            </div>
            
            <div class="form-group">
                <input type="submit" value="Import CSV" class="button button-primary" />
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Add some basic styles
add_action('wp_head', 'auto_importer_styles');

function auto_importer_styles() {
    ?>
    <style>
        .auto-csv-upload-form {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .auto-csv-upload-form .form-group {
            margin-bottom: 15px;
        }
        .auto-csv-upload-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
    <?php
}