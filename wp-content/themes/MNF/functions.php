<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );


///// from mnf /////


// -----------  //
//  Profile section //
// ---------- //
// Enqueue scripts and styles
function profile_dashboard_inline_styles() {
    if (is_page_template('template-user-profile.php')) {
        $css_file = get_template_directory() . '/assets/css/profile-dashboard.css';
        if (file_exists($css_file)) {
            echo '<style>' . file_get_contents($css_file) . '</style>';
        }
    }
}
add_action('wp_head', 'profile_dashboard_inline_styles');

// Handle profile updates
function handle_profile_update() {
    check_ajax_referer('update_profile_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }
    
    $user_id = get_current_user_id();
    
    // Update user data
    $user_data = array(
        'ID' => $user_id,
        'display_name' => sanitize_text_field($_POST['display_name']),
        'user_email' => sanitize_email($_POST['user_email'])
    );
    
    $user_id = wp_update_user($user_data);
    
    // Update user meta
    update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
    update_user_meta($user_id, 'company', sanitize_text_field($_POST['company']));
    
    wp_send_json_success('Profile updated');
}
add_action('wp_ajax_update_user_profile', 'handle_profile_update');


//_____________________ Login Profile _______________________ //
// Login System Functions - Add to functions.php

function add_login_profile_buttons() {
    if (is_user_logged_in()) {
        ?>
        <div class="MNFL-auth-container">
            <a href="<?php echo get_permalink(get_page_by_path('profile')); ?>" class="MNFL-btn-content MNFL-profile-btn">
                <span class="MNFL-btn-title">Mein Profil</span>
                <span class="MNFL-icon-arrow">
                    <svg width="66px" height="43px" viewBox="0 0 66 43" version="1.1" xmlns="http://www.w3.org/2000/svg">
                        <g id="arrow" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <path id="arrow-icon-one" d="M40.1543933,3.89485454 L43.9763149,0.139296592 C44.1708311,-0.0518420739 44.4826329,-0.0518571125 44.6771675,0.139262789 L65.6916134,20.7848311 C66.0855801,21.1718824 66.0911863,21.8050225 65.704135,22.1989893 C65.7000188,22.2031791 65.6958657,22.2073326 65.6916762,22.2114492 L44.677098,42.8607841 C44.4825957,43.0519059 44.1708242,43.0519358 43.9762853,42.8608513 L40.1545186,39.1069479 C39.9575152,38.9134427 39.9546793,38.5968729 40.1481845,38.3998695 C40.1502893,38.3977268 40.1524132,38.395603 40.1545562,38.3934985 L56.9937789,21.8567812 C57.1908028,21.6632968 57.193672,21.3467273 57.0001876,21.1497035 C56.9980647,21.1475418 56.9959223,21.1453995 56.9937605,21.1432767 L40.1545208,4.60825197 C39.9574869,4.41477773 39.9546013,4.09820839 40.1480756,3.90117456 C40.1501626,3.89904911 40.1522686,3.89694235 40.1543933,3.89485454 Z" fill="#FFFFFF"></path>
                        </g>
                    </svg>
                </span>
            </a>
            <a href="<?php echo wp_logout_url(home_url()); ?>" id="MNFL-logout-btn" class="MNFL-btn-content MNFL-logout-btn">
                <span class="MNFL-btn-title">Abmelden</span>
            </a>
        </div>
        <?php
    } else {
        ?>
        <div class="MNFL-auth-container">
            <button id="MNFL-login-btn" class="MNFL-btn-content">
                <span class="MNFL-btn-title">Anmelden</span>
                <span class="MNFL-icon-arrow">
                    <svg width="66px" height="43px" viewBox="0 0 66 43" version="1.1" xmlns="http://www.w3.org/2000/svg">
                        <g id="arrow" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <path id="arrow-icon-one" d="M40.1543933,3.89485454 L43.9763149,0.139296592 C44.1708311,-0.0518420739 44.4826329,-0.0518571125 44.6771675,0.139262789 L65.6916134,20.7848311 C66.0855801,21.1718824 66.0911863,21.8050225 65.704135,22.1989893 C65.7000188,22.2031791 65.6958657,22.2073326 65.6916762,22.2114492 L44.677098,42.8607841 C44.4825957,43.0519059 44.1708242,43.0519358 43.9762853,42.8608513 L40.1545186,39.1069479 C39.9575152,38.9134427 39.9546793,38.5968729 40.1481845,38.3998695 C40.1502893,38.3977268 40.1524132,38.395603 40.1545562,38.3934985 L56.9937789,21.8567812 C57.1908028,21.6632968 57.193672,21.3467273 57.0001876,21.1497035 C56.9980647,21.1475418 56.9959223,21.1453995 56.9937605,21.1432767 L40.1545208,4.60825197 C39.9574869,4.41477773 39.9546013,4.09820839 40.1480756,3.90117456 C40.1501626,3.89904911 40.1522686,3.89694235 40.1543933,3.89485454 Z" fill="#FFFFFF"></path>
                        </g>
                    </svg>
                </span>
            </button>
        </div>
        <?php
    }
}
// Add login modal to footer
function add_login_modal() {
    ?>
    <div id="MNFL-login-modal" class="MNFL-login-modal" style="display: none;">
        <div class="MNFL-login-modal-content">
            <span class="MNFL-close-modal">&times;</span>
            <h2>Anmelden</h2>
            <form id="MNFL-login-form">
                <div class="MNFL-form-group">
                    <label for="username">Benutzername</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="MNFL-form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Anmelden</button>
            </form>
            <div id="MNFL-login-message"></div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'add_login_modal');

// Handle login AJAX request
function handle_custom_login() {
    check_ajax_referer('custom-login-nonce', 'security');
    
    $credentials = array(
        'user_login' => $_POST['username'],
        'user_password' => $_POST['password'],
        'remember' => true
    );
    
    $user = wp_signon($credentials, false);
    
    if (is_wp_error($user)) {
        wp_send_json_error(array(
            'message' => 'Ungültige Anmeldeinformationen'
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'Erfolgreich angemeldet',
            'redirect' => home_url()
        ));
    }
}
add_action('wp_ajax_nopriv_custom_login', 'handle_custom_login');


// Handle logout AJAX request
function handle_custom_logout() {
    // Don't check user role, just verify the nonce
    check_ajax_referer('custom-login-nonce', 'security');
    
    if (is_user_logged_in()) {
        wp_logout();
        wp_clear_auth_cookie();
        
        wp_send_json_success(array(
            'message' => 'Erfolgreich abgemeldet',
            'redirect' => home_url('/')
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Kein Benutzer angemeldet'
        ));
    }
}
add_action('wp_ajax_custom_logout', 'handle_custom_logout');

// Enqueue scripts and styles
function enqueue_custom_login_scripts() {
    // Enqueue CSS
    wp_enqueue_style(
        'custom-login',
        get_template_directory_uri() . '/assets/css/login.css',
        array(),
        '1.0'
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'custom-login',
        get_template_directory_uri() . '/assets/js/login.js',
        array('jquery'),
        '1.0',
        true
    );
    
    // Fix login script localization
    $login_data = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('custom-login-nonce'),
        'homeUrl' => home_url('/')
    );
    wp_localize_script('custom-login', 'loginAjax', $login_data);
}
add_action('wp_enqueue_scripts', 'enqueue_custom_login_scripts');




// Add logout success modal to footer
function add_logout_fallback() {
    if (current_user_can('verkaufer')) {
        add_action('wp_footer', function() {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('#MNFL-logout-btn').on('click', function(e) {
                    if (!e.isDefaultPrevented()) {
                        e.preventDefault();
                        window.location.href = '<?php echo wp_logout_url(home_url('/')); ?>';
                    }
                });
            });
            </script>
            <?php
        });
    }
}
add_action('init', 'add_logout_fallback');
//_____________________ custom role verkaufer _______________________ //
function add_verkaufer_role() {
    // Remove the role first to prevent capabilities from stacking
    remove_role('verkaufer');
    
    // Add new role with required capabilities
    add_role(
        'verkaufer',
        'Verkaufer',
        array(
            'read' => true,                  // Basic reading capability
            'edit_profile' => true,          // Can edit their own profile
            'read_private_pages' => false,
            'read_private_posts' => false,
            'logout' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => false,
            'manage_options' => false,
            'level_0' => true,               // Basic subscriber level capabilities
            'exist' => true,                 // Basic existence in the system
            'read_posts' => true,            // Can read posts
            'read_pages' => true             // Can read pages
        )
    );
}
add_action('init', 'add_verkaufer_role');

// Modify redirect function to be less restrictive
function redirect_verkaufer_users() {
    if (is_admin()) {
        $user = wp_get_current_user();
        
        // Check if user has Verkaufer role and is not on allowed pages
        if (in_array('verkaufer', (array) $user->roles)) {
            $screen = get_current_screen();
            $allowed_screens = array('profile', 'dashboard');
            
            if (!in_array($screen->id, $allowed_screens)) {
                wp_redirect(home_url());
                exit;
            }
        }
    }
}
add_action('admin_init', 'redirect_verkaufer_users');

// Keep admin bar hidden but ensure proper functionality
function remove_admin_bar_for_verkaufer() {
    if (current_user_can('verkaufer')) {
        show_admin_bar(false);
        
        // Ensure AJAX capabilities
        add_filter('user_has_cap', function($allcaps, $caps, $args) {
            $allcaps['wp_ajax_custom_logout'] = true;
            return $allcaps;
        }, 10, 3);
    }
}
add_action('after_setup_theme', 'remove_admin_bar_for_verkaufer');

// Add specific AJAX capabilities for Verkaufer role
function add_verkaufer_ajax_caps() {
    $role = get_role('verkaufer');
    if ($role) {
        $role->add_cap('wp_ajax_custom_logout', true);
    }
}
add_action('admin_init', 'add_verkaufer_ajax_caps');

// Add this after your role definition
function verkaufer_init() {
    if (current_user_can('verkaufer')) {
        add_action('wp_ajax_custom_logout', 'handle_custom_logout', 10);
    }
}
add_action('init', 'verkaufer_init');




// add importer 

add_action('admin_menu', 'register_car_importer_menu');

function register_car_importer_menu() {
    add_menu_page(
        'Car CSV Importer',
        'Car Importer',
        'manage_options',
        'car-csv-importer',
        'car_importer_page',
        'dashicons-upload',
        30
    );
}

function car_importer_page() {
    global $wpdb;
    // Fix: Changed table name from 'car' to 'cars'
    $table_name = $wpdb->prefix . 'cars';
    
    if (isset($_POST['submit']) && isset($_FILES['csv_file'])) {
        if (!wp_verify_nonce($_POST['csv_import_nonce'], 'csv_import')) {
            wp_die('Security check failed');
        }

        $file = $_FILES['csv_file'];
        if ($file['error'] == UPLOAD_ERR_OK) {
            $handle = fopen($file['tmp_name'], 'r');
            
            // Skip header row
            $headers = fgetcsv($handle);
            
            $wpdb->query('START TRANSACTION');
            
            try {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Map your CSV columns to match the table structure from the screenshot
                    $insert_data = array(
                        'username' => null,
                        'token' => null,
                        'kundennummer' => $data[0] ?? null,
                        'interne_nummer' => $data[1] ?? null,
                        'car_type' => $data[2] ?? null,
                        'marke' => $data[3] ?? null,
                        'modell' => $data[4] ?? null,
                        'leistung' => $data[5] ?? null,
                        'hu' => $data[6] ?? null,
                        'ez' => $data[7] ?? null,
                        'kilometer' => $data[8] ?? null,
                        'preis' => $data[9] ?? null,
                        'mwst' => $data[10] ?? null,
                        'oldtimer' => $data[11] ?? null,
                        'vin' => $data[12] ?? null,
                        'beschaedigtes_fahrzeug' => $data[13] ?? null,
                        'farbe' => $data[14] ?? null,
                        'klima' => $data[15] ?? null,
                        'taxi' => $data[16] ?? null,
                        'behindertengerecht' => $data[17] ?? null,
                        'jahreswagen' => $data[18] ?? null,
                        'neufahrzeug' => $data[19] ?? null,
                        'unsere_empfehlung' => $data[20] ?? null
                    );
                    
                    $result = $wpdb->insert(
                        $table_name,
                        $insert_data
                    );
                    
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                }
                
                $wpdb->query('COMMIT');
                echo '<div class="notice notice-success"><p>CSV import completed successfully!</p></div>';
                
            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>Error importing CSV: ' . esc_html($e->getMessage()) . '</p></div>';
            }
            
            fclose($handle);
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Car CSV Importer</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('csv_import', 'csv_import_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="csv_file">Select CSV File</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </td>
                </tr>
            </table>
            <?php submit_button('Import CSV'); ?>
        </form>
    </div>
    <?php
}

// Optional: Update activation hook with correct table name
register_activation_hook(__FILE__, 'create_cars_table');

function create_cars_table() {
    global $wpdb;
    // Fix: Changed table name from 'car' to 'cars'
    $table_name = $wpdb->prefix . 'cars';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        username varchar(100) NULL,
        token int(11) NULL,
        kundennummer varchar(40) NULL,
        interne_nummer varchar(40) NULL,
        car_type varchar(50) NULL,
        marke varchar(100) NULL,
        modell varchar(100) NULL,
        leistung int(11) NULL,
        hu date NULL,
        ez date NULL,
        kilometer int(11) NULL,
        preis decimal(10,2) NULL,
        mwst tinyint(1) NULL,
        oldtimer tinyint(1) NULL,
        vin varchar(17) NULL,
        beschaedigtes_fahrzeug tinyint(1) NULL,
        farbe varchar(32) NULL,
        klima tinyint(1) NULL,
        taxi tinyint(1) NULL,
        behindertengerecht tinyint(1) NULL,
        jahreswagen tinyint(1) NULL,
        neufahrzeug tinyint(1) NULL,
        unsere_empfehlung tinyint(1) NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


