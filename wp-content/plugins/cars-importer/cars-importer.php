<?php
/**
 * Cars CSV Importer for WordPress
 * 
 * This function imports car data from a CSV file into the wp_cars table
 * If a record exists (matched by VIN or internal number), it updates instead of creating new
 */
function import_cars_csv($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cars';
    $results = array(
        'created' => 0,
        'updated' => 0,
        'errors' => array()
    );

    // Check if file exists
    if (!file_exists($file_path)) {
        return array('error' => 'File not found: ' . $file_path);
    }

    // Open CSV file
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return array('error' => 'Could not open file');
    }

    // Get headers from first row
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return array('error' => 'Empty or invalid CSV file');
    }

    // Clean headers (remove BOM if present, trim whitespace)
    $headers = array_map(function($header) {
        return trim(str_replace("\xEF\xBB\xBF", '', $header));
    }, $headers);

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Process each row
        while (($data = fgetcsv($handle, 0, ";", '"', '"')) !== FALSE) {
            // Skip empty rows
            if (empty($data) || count($data) < 2) continue;
            
            // Create associative array with predefined headers
            $row = array();
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim($data[$index], '" ') : '';
            
            // Prepare data for database
            $cleaned_data = prepare_car_data($row);
            
            // Check if record exists (by VIN or internal number)
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

            // Insert or update record
            if ($exists) {
                // Update existing record
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
                // Insert new record
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
        }}

        // If we got here without exceptions, commit the transaction
        $wpdb->query('COMMIT');

    } catch (Exception $e) {
        // If anything went wrong, rollback the transaction
        $wpdb->query('ROLLBACK');
        $results['errors'][] = "Transaction failed: " . $e->getMessage();
    }

    fclose($handle);
    return $results;
}

/**
 * Prepare car data for database insertion/update
 */
function prepare_car_data($row) {
    $cleaned = array();
    
    // Map and clean each field
    foreach ($row as $key => $value) {
        // Skip empty values
        if ($value === '') {
            continue;
        }

        // Clean and format based on field type
        switch ($key) {
            case 'preis':
            case 'haendlerpreis':
            case 'bruttokreditbetrag':
            case 'abschlussgebuehren':
            case 'ratenabsicherung':
            case 'nettokreditbetrag':
                $cleaned[$key] = (float) str_replace(array(',', ' '), array('.', ''), $value);
                break;

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

            case 'soll_zinssatz':
            case 'effektiver_jahreszins':
            case 'verbrauch_innerorts':
            case 'verbrauch_ausserorts':
            case 'verbrauch_kombiniert':
            case 'kombinierter_stromverbrauch':
            case 'mwstsatz':
                $cleaned[$key] = (float) str_replace(array(',', ' '), array('.', ''), $value);
                break;

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

            default:
                $cleaned[$key] = $value;
        }
    }

    return $cleaned;
}

