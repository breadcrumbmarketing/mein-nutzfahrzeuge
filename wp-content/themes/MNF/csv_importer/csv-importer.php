<?php
/**
 * CSV Importer Module for Mobile.de Data
 * 
 * Plugin folder structure:
 * csv_importer/
 * ├── css/
 * │   └── style.css
 * ├── js/
 * │   └── script.js
 * ├── includes/
 * │   ├── class-csv-importer.php
 * │   └── class-csv-processor.php
 * └── csv-importer.php
 */

// Main plugin file: csv_importer/csv-importer.php
if (!defined('ABSPATH')) exit;

class MobileDe_CSV_Importer {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function init() {
        add_shortcode('mobile_de_upload', array($this, 'render_upload_form'));
        add_shortcode('mobile_de_results', array($this, 'render_results'));
        
        // Register AJAX handlers
        add_action('wp_ajax_process_csv_upload', array($this, 'process_csv_upload'));
        add_action('wp_ajax_nopriv_process_csv_upload', array($this, 'process_csv_upload'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'mobile-de-importer',
            get_template_directory_uri() . '/csv_importer/css/style.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'mobile-de-importer',
            get_template_directory_uri() . '/csv_importer/js/script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('mobile-de-importer', 'mobileDeImporter', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mobile_de_import_nonce'),
            'messages' => array(
                'uploading' => 'Datei wird hochgeladen...',
                'processing' => 'CSV wird verarbeitet...',
                'error' => 'Ein Fehler ist aufgetreten',
                'success' => 'Import erfolgreich'
            )
        ));
    }

    public function render_upload_form() {
        ob_start();
        ?>
        <div class="mobile-de-uploader">
            <form id="csvUploadForm" method="post" enctype="multipart/form-data">
                <div class="upload-wrapper">
                    <label for="csvFile">CSV-Datei auswählen</label>
                    <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
                </div>
                
                <div class="submit-wrapper">
                    <button type="submit" class="submit-button">
                        Datei importieren
                    </button>
                </div>

                <div id="uploadProgress" class="progress-bar" style="display: none;">
                    <div class="progress"></div>
                </div>

                <div id="uploadMessage" class="message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_results() {
        ob_start();
        ?>
        <div id="importResults" class="import-results">
            <div class="results-content">
                <h3>Import Ergebnisse</h3>
                <div id="resultsSummary"></div>
                <div id="errorsLog"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function process_csv_upload() {
        check_ajax_referer('mobile_de_import_nonce', 'nonce');

        $response = array(
            'success' => false,
            'message' => '',
            'data' => array(
                'imported' => 0,
                'errors' => array()
            )
        );

        if (!isset($_FILES['csvFile'])) {
            $response['message'] = 'Keine Datei hochgeladen';
            wp_send_json($response);
            return;
        }

        require_once 'includes/class-csv-processor.php';
        $processor = new CSV_Processor();
        
        try {
            $result = $processor->process($_FILES['csvFile']['tmp_name']);
            
            $response['success'] = true;
            $response['data'] = $result;
            $response['message'] = sprintf(
                'Import abgeschlossen: %d Fahrzeuge erfolgreich importiert',
                $result['imported']
            );
        } catch (Exception $e) {
            $response['message'] = 'Fehler beim Import: ' . $e->getMessage();
            $response['data']['errors'][] = $e->getMessage();
        }

        wp_send_json($response);
    }
}

MobileDe_CSV_Importer::get_instance();