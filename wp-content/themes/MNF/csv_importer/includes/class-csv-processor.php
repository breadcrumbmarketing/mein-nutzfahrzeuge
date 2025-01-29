<?php
// csv_importer/includes/class-csv-processor.php

if (!defined('ABSPATH')) exit;

class CSV_Processor {
    private $required_columns = array(
        'kundennummer',
        'interne_nummer',
        'car_type',
        'marke',
        'modell'
    );

    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'cars';
    }

    public function process($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('Datei nicht gefunden');
        }

        $result = array(
            'imported' => 0,
            'errors' => array()
        );

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception('Datei konnte nicht geöffnet werden');
        }

        // Get headers and validate
        $headers = fgetcsv($handle);
        $this->validate_headers($headers);

        // Get current user info
        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $token = time(); // Using timestamp as token

        // Begin transaction
        $this->wpdb->query('START TRANSACTION');

        try {
            $row_number = 1;
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                
                try {
                    $this->process_row($data, $headers, $username, $token);
                    $result['imported']++;
                } catch (Exception $e) {
                    $result['errors'][] = sprintf(
                        'Fehler in Zeile %d: %s',
                        $row_number,
                        $e->getMessage()
                    );
                }
            }

            // If we got here without exceptions, commit the transaction
            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            // If something went wrong, rollback the transaction
            $this->wpdb->query('ROLLBACK');
            throw $e;
        } finally {
            fclose($handle);
        }

        return $result;
    }

    private function validate_headers($headers) {
        if (!$headers) {
            throw new Exception('Keine Spaltenüberschriften gefunden');
        }

        $missing_columns = array();
        foreach ($this->required_columns as $required) {
            if (!in_array($required, $headers)) {
                $missing_columns[] = $required;
            }
        }

        if (!empty($missing_columns)) {
            throw new Exception(sprintf(
                'Fehlende Pflichtspalten: %s',
                implode(', ', $missing_columns)
            ));
        }
    }

    private function process_row($data, $headers, $username, $token) {
        $row_data = array_combine($headers, $data);
        
        // Add username and token
        $row_data['username'] = $username;
        $row_data['token'] = $token;

        // Convert dates
        $date_fields = array('ez', 'hu', 'lieferdatum', 'produktionsdatum', 'highlight_ab', 'highlight_bis');
        foreach ($date_fields as $field) {
            if (!empty($row_data[$field])) {
                $row_data[$field] = $this->convert_date($row_data[$field]);
            }
        }

        // Clean and validate data
        $row_data = $this->sanitize_data($row_data);

        // Insert into database
        $result = $this->wpdb->insert(
            $this->table_name,
            $row_data,
            $this->get_format_array($row_data)
        );

        if ($result === false) {
            throw new Exception(sprintf(
                'Datenbank-Fehler: %s',
                $this->wpdb->last_error
            ));
        }
    }

    private function convert_date($date_string) {
        if (empty($date_string)) return null;

        // Try different date formats
        $formats = array(
            'd.m.Y', // 31.12.2023
            'm.Y',   // 12.2023
            'Y-m-d'  // 2023-12-31
        );

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        throw new Exception(sprintf(
            'Ungültiges Datumsformat: %s',
            $date_string
        ));
    }

    private function sanitize_data($data) {
        foreach ($data as $key => $value) {
            // Convert empty strings to NULL
            if ($value === '') {
                $data[$key] = null;
                continue;
            }

            // Sanitize based on field type
            switch ($key) {
                case 'preis':
                case 'haendlerpreis':
                    $data[$key] = (float) str_replace(
                        array('.', ','),
                        array('', '.'),
                        $value
                    );
                    break;

                case 'kilometer':
                case 'ccm':
                case 'leistung':
                    $data[$key] = (int) $value;
                    break;

                default:
                    // Basic sanitization for text fields
                    if (is_string($value)) {
                        $data[$key] = sanitize_text_field($value);
                    }
            }
        }

        return $data;
    }

    private function get_format_array($data) {
        $formats = array();
        foreach ($data as $value) {
            if (is_null($value)) {
                $formats[] = null;
            } elseif (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }
}