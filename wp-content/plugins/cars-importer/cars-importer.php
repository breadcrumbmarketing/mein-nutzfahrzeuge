<?php
/**
 * Plugin Name: Cars CSV Importer
 * Plugin URI: 
 * Description: Import cars data from CSV files into wp_cars table
 * Version: 1.0
 * Author: Your Name
 * Text Domain: cars-importer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Class
class CarsImporterPlugin {
    
    public function __construct() {
        // Hook for admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hook for admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Hook for plugin activation
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
    }

    /**
     * Add menu item to admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Cars Importer', 'cars-importer'),
            __('Cars Importer', 'cars-importer'),
            'manage_options',
            'cars-importer',
            array($this, 'admin_page'),
            'dashicons-car',
            30
        );
    }

    /**
     * Plugin activation hook
     */
    public function plugin_activation() {
        global $wpdb;
        
        // Check if table exists
        $table_name = $wpdb->prefix . 'cars';
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            add_option('cars_importer_needs_table', true);
        }
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if (get_option('cars_importer_needs_table')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Cars Importer: The required database table is missing. Please create wp_cars table first.', 'cars-importer'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        // Check for table
        if (get_option('cars_importer_needs_table')) {
            echo '<div class="wrap"><h1>' . __('Cars Importer', 'cars-importer') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('The required database table is missing. Please create wp_cars table first.', 'cars-importer') . '</p></div></div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Cars Importer', 'cars-importer'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Import Instructions', 'cars-importer'); ?></h2>
                <p><?php _e('Please ensure your CSV file:', 'cars-importer'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('Has headers matching the database columns', 'cars-importer'); ?></li>
                    <li><?php _e('Uses UTF-8 encoding', 'cars-importer'); ?></li>
                    <li><?php _e('Has dates in YYYY-MM-DD format', 'cars-importer'); ?></li>
                    <li><?php _e('Uses standard CSV formatting', 'cars-importer'); ?></li>
                </ul>
            </div>

            <?php
            // Handle import
            if (isset($_FILES['cars_csv'])) {
                $this->handle_import();
            }
            ?>

            <div class="card">
                <form method="post" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cars_csv"><?php _e('Choose CSV File', 'cars-importer'); ?></label>
                            </th>
                            <td>
                                <input type="file" 
                                       name="cars_csv" 
                                       id="cars_csv" 
                                       accept=".csv" 
                                       required>
                                <p class="description">
                                    <?php _e('Select a CSV file to import', 'cars-importer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Import Cars', 'cars-importer')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the import process
     */
    private function handle_import() {
        // Verify nonce and permissions here if needed
        
        $upload = wp_handle_upload($_FILES['cars_csv'], array('test_form' => false));
            
        if ($upload && !isset($upload['error'])) {
            // Run the import
            $results = $this->import_cars_csv($upload['file']);
            
            // Display results
            echo "<div class='notice notice-success'>";
            echo "<p>" . __('Import completed:', 'cars-importer') . "</p>";
            echo "<p>" . __('Created:', 'cars-importer') . " " . $results['created'] . " " . __('records', 'cars-importer') . "</p>";
            echo "<p>" . __('Updated:', 'cars-importer') . " " . $results['updated'] . " " . __('records', 'cars-importer') . "</p>";
            
            if (!empty($results['errors'])) {
                echo "<p>" . __('Errors occurred:', 'cars-importer') . "</p><ul>";
                foreach ($results['errors'] as $error) {
                    echo "<li>" . esc_html($error) . "</li>";
                }
                echo "</ul>";
            }
            echo "</div>";
            
            // Clean up
            unlink($upload['file']);
        } else {
            echo "<div class='notice notice-error'>";
            echo "<p>" . __('Upload failed:', 'cars-importer') . " " . 
                 (isset($upload['error']) ? esc_html($upload['error']) : __('Unknown error', 'cars-importer')) . "</p>";
            echo "</div>";
        }
    }

    /**
     * Import cars from CSV
     */
    private function import_cars_csv($file_path) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cars';
        $results = array(
            'created' => 0,
            'updated' => 0,
            'errors' => array()
        );

        if (!file_exists($file_path)) {
            return array('error' => 'File not found: ' . $file_path);
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array('error' => 'Could not open file');
        }

        // Get headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return array('error' => 'Empty or invalid CSV file');
        }

        // Clean headers
        $headers = array_map(function($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row = array_combine($headers, $data);
                $row = array_map('trim', $row);
                
                // Prepare data
                $cleaned_data = $this->prepare_car_data($row);
                
                // Check if record exists
                $exists = false;
                if (!empty($cleaned_data['vin'])) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE vin = %s",
                        $cleaned_data['vin']
                    ));
                } elseif (!empty($cleaned_data['interne_nummer'])) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE interne_nummer = %s",
                        $cleaned_data['interne_nummer']
                    ));
                }

                if ($exists) {
                    // Update
                    $where = array();
                    if (!empty($cleaned_data['vin'])) {
                        $where['vin'] = $cleaned_data['vin'];
                    } else {
                        $where['interne_nummer'] = $cleaned_data['interne_nummer'];
                    }
                    
                    $update_result = $wpdb->update(
                        $table_name,
                        $cleaned_data,
                        $where
                    );

                    if ($update_result !== false) {
                        $results['updated']++;
                    } else {
                        $results['errors'][] = "Error updating record: " . $wpdb->last_error;
                    }
                } else {
                    // Insert
                    $insert_result = $wpdb->insert(
                        $table_name,
                        $cleaned_data
                    );

                    if ($insert_result) {
                        $results['created']++;
                    } else {
                        $results['errors'][] = "Error inserting record: " . $wpdb->last_error;
                    }
                }
            }

            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $results['errors'][] = "Transaction failed: " . $e->getMessage();
        }

        fclose($handle);
        return $results;
    }

    /**
     * Prepare car data for database
     */
    private function prepare_car_data($row) {
        $cleaned = array();
        
        foreach ($row as $key => $value) {
            if ($value === '') {
                continue;
            }

            switch ($key) {
                // Decimal fields
                case 'preis':
                case 'haendlerpreis':
                case 'bruttokreditbetrag':
                case 'abschlussgebuehren':
                case 'ratenabsicherung':
                case 'nettokreditbetrag':
                case 'soll_zinssatz':
                case 'effektiver_jahreszins':
                case 'verbrauch_innerorts':
                case 'verbrauch_ausserorts':
                case 'verbrauch_kombiniert':
                case 'kombinierter_stromverbrauch':
                case 'mwstsatz':
                    $cleaned[$key] = (float) str_replace(array(',', ' '), array('.', ''), $value);
                    break;

                // Date fields
                case 'hu':
                case 'ez':
                case 'lieferdatum':
                case 'produktionsdatum':
                    if ($value && $value !== '0000-00-00') {
                        $date = date_create_from_format('Y-m-d', $value);
                        if ($date) {
                            $cleaned[$key] = $date->format('Y-m-d');
                        }
                    }
                    break;

                // Integer fields
                case 'leistung':
                case 'kilometer':
                case 'ccm':
                case 'tragkraft':
                case 'nutzlast':
                case 'gesamtgewicht':
                case 'hubhoehe':
                case 'bauhoehe':
                case 'baujahr':
                case 'betriebsstunden':
                case 'sitze':
                case 'achsen':
                case 'emission':
                case 'lieferfrist':
                case 'ueberfuehrungskosten':
                case 'hsn':
                case 'vorbesitzer':
                case 'fahrzeuglaenge':
                case 'fahrzeugbreite':
                case 'fahrzeughoehe':
                case 'laderaum_europalette':
                case 'laderaum_volumen':
                case 'laderaum_laenge':
                case 'laderaum_breite':
                case 'laderaum_hoehe':
                case 'monatliche_rate':
                case 'laufzeit':
                case 'anzahlung':
                case 'schlussrate':
                case 'schlafplaetze':
                    $cleaned[$key] = (int) $value;
                    break;

                // Boolean fields (char(1))
                case 'mwst':
                    case 'oldtimer':
                    case 'beschaedigtes_fahrzeug':
                    case 'klima':
                    case 'taxi':
                    case 'behindertengerecht':
                    case 'jahreswagen':
                    case 'neufahrzeug':
                    case 'unsere_empfehlung':
                    case 'metallic':
                    case 'garantie':
                    case 'leichtmetallfelgen':
                    case 'esp':
                    case 'abs':
                    case 'anhaengerkupplung':
                    case 'wegfahrsperre':
                    case 'navigationsystem':
                    case 'schiebedach':
                    case 'zentralverriegelung':
                    case 'fensterheber':
                    case 'allradantrieb':
                    case 'umweltplakette':
                    case 'servolenkung':
                    case 'biodiesel':
                    case 'scheckheftgepflegt':
                    case 'katalysator':
                    case 'kickstarter':
                    case 'estarter':
                    case 'vorfuehrfahrzeug':
                    case 'antrieb':
                    case 'schadstoff':
                    case 'kabinenart':
                    case 'tempomat':
                    case 'standheizung':
                    case 'kabine':
                    case 'schutzdach':
                    case 'vollverkleidung':
                    case 'komunal':
                    case 'kran':
                    case 'retarder_intarder':
                    case 'schlafplatz':
                    case 'tv':
                    case 'wc':
                    case 'ladebordwand':
                    case 'hydraulikanlage':
                    case 'schiebetuer':
                    case 'radformel':
                    case 'trennwand':
                    case 'ebs':
                    case 'vermietbar':
                    case 'kompressor':
                    case 'luftfederung':
                    case 'scheibenbremse':
                    case 'fronthydraulik':
                    case 'bss':
                    case 'schnellwechsel':
                    case 'zsa':
                    case 'kueche':
                    case 'kuehlbox':
                    case 'schlafsitze':
                    case 'frontheber':
                    case 'sichtbar_nur_fuer_Haendler':
                    case 'reserviert_5':
                    case 'envkv':
                    case 'xenonscheinwerfer':
                    case 'sitzheizung':
                    case 'partikelfilter':
                    case 'einparkhilfe':
                    case 'hu_au_neu':
                    case 'kraftstoffart':
                    case 'getriebeart':
                    case 'exportfahrzeug':
                    case 'tageszulassung':
                    case 'blickfaenger':
                    case 'seite_1_inserat':
                    case 'e10':
                    case 'pflanzenoel':
                    case 'scr':
                    case 'koffer':
                    case 'sturzbuegel':
                    case 'scheibe':
                    case 'standklima':
                    case 's_s_bereifung':
                    case 'strassenzulassung':
                    case 'etagenbett':
                    case 'festbett':
                    case 'heckgarage':
                    case 'markise':
                    case 'sep_dusche':
                    case 'solaranlage':
                    case 'mittelsitzgruppe':
                    case 'rundsitzgruppe':
                    case 'seitensitzgruppe':
                    case 'hagelschaden':
                    case 'inserat_als_neu_markieren':
                    case 'finanzierungsfeature':
                    case 'interieurfarbe':
                    case 'interieurtyp':
                    case 'airbag':
                    case 'top_inserat':
                    case 'art_des_soll_zinssatzes':
                    case 'elektrische_seitenspiegel':
                    case 'sportfahrwerk':
                    case 'sportpaket':
                    case 'bluetooth':
                    case 'bordcomputer':
                    case 'cd_spieler':
                    case 'elektrische_sitzeinstellung':
                    case 'head_up_display':
                    case 'freisprecheinrichtung':
                    case 'mp3_schnittstelle':
                    case 'multifunktionslenkrad':
                    case 'skisack':
                    case 'tuner_oder_radio':
                    case 'sportsitze':
                    case 'panorama_dach':
                    case 'kindersitzbefestigung':
                    case 'kurvenlicht':
                    case 'lichtsensor':
                    case 'nebelscheinwerfer':
                    case 'tagfahrlicht':
                    case 'traktionskontrolle':
                    case 'start_stop_automatik':
                    case 'regensensor':
                    case 'nichtraucher_fahrzeug':
                    case 'dachreling':
                    case 'unfallfahrzeug':
                    case 'fahrtauglich':
                    case 'einparkhilfe_sensoren_vorne':
                    case 'einparkhilfe_sensoren_hinten':
                    case 'einparkhilfe_kamera':
                    case 'einparkhilfe_selbstlenkendes_system':
                    case 'rotstiftpreis':
                    case 'kleinanzeigen_export':
                    case 'plugin_hybrid':
                    $cleaned[$key] = in_array(strtolower($value), array('1', 'yes', 'true', 'j', 'y')) ? '1' : '0';
                    break;

                // Default (string fields)
                default:
                    $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}

// Initialize plugin
new CarsImporterPlugin();