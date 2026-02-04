<?php
/**
 * Finance Data Loader
 * Parses CSV files and returns structured financial data
 * PHP port of build-data.py logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finance_Data_Loader {

    private static $month_names = array(
        '', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    );

    /**
     * Get all financial data from CSV files in directory
     *
     * @param string $data_dir Path to directory containing CSV files
     * @return array Structured data for JavaScript
     */
    public function get_financial_data($data_dir) {
        $data = array();
        $metadata = array();
        $balances = $this->load_balances($data_dir);

        if (!is_dir($data_dir)) {
            return array(
                'FINANCIAL_DATA' => array(),
                'FISCAL_YEARS' => array(),
                'YEAR_METADATA' => array(),
                'error' => 'Directory not found: ' . $data_dir
            );
        }

        // Find all CSV files
        $csv_files = glob($data_dir . '/*.{csv,CSV}', GLOB_BRACE);

        if (empty($csv_files)) {
            return array(
                'FINANCIAL_DATA' => array(),
                'FISCAL_YEARS' => array(),
                'YEAR_METADATA' => array(),
                'error' => 'No CSV files found'
            );
        }

        // Track processed files to avoid duplicates
        $seen = array();

        foreach ($csv_files as $filepath) {
            $filename = basename($filepath);
            $filename_lower = strtolower($filename);

            if (isset($seen[$filename_lower])) {
                continue;
            }
            $seen[$filename_lower] = true;

            // Parse the file
            $rows = $this->parse_csv_file($filepath);

            // Extract year and currency from filename
            list($fiscal_year, $data_to, $is_complete) = $this->extract_year_and_currency($filename);

            $data[$fiscal_year] = $rows;
            $metadata[$fiscal_year] = array(
                'dataTo' => $data_to,
                'isComplete' => $is_complete,
                'rowCount' => count($rows),
                'sourceFile' => $filename,
                'openingBalance' => isset($balances[$fiscal_year]) ? $balances[$fiscal_year] : null
            );
        }

        // Sort years in REVERSE order (most recent first)
        $sorted_years = array_keys($data);
        usort($sorted_years, function($a, $b) {
            $year_a = (int) explode('-', $a)[0];
            $year_b = (int) explode('-', $b)[0];
            return $year_b - $year_a; // Descending
        });

        // Rebuild arrays in sorted order
        $sorted_data = array();
        $sorted_metadata = array();
        foreach ($sorted_years as $year) {
            $sorted_data[$year] = $data[$year];
            $sorted_metadata[$year] = $metadata[$year];
        }

        return array(
            'FINANCIAL_DATA' => $sorted_data,
            'FISCAL_YEARS' => $sorted_years,
            'YEAR_METADATA' => $sorted_metadata
        );
    }

    /**
     * Extract fiscal year and data currency from filename
     *
     * @param string $filename The CSV filename
     * @return array [fiscal_year, data_to_date, is_complete]
     */
    private function extract_year_and_currency($filename) {
        // Pattern 1: Full year "2022-3" or "2023-4" etc.
        $year_match = preg_match('/(\d{4})-(\d)\b/', $filename, $year_matches);

        // Pattern 2: Date in filename "DD-MM-YYYY"
        $date_match = preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $filename, $date_matches);

        // Pattern 3: Short date "to DD-MM" (assumes current context)
        $short_date_match = preg_match('/to\s+(\d{1,2})-(\d{1,2})/i', $filename, $short_matches);

        if ($year_match && !$date_match) {
            // Full year file
            $start_year = (int) $year_matches[1];
            $end_digit = (int) $year_matches[2];
            $fiscal_year = "{$start_year}-{$end_digit}";

            // Full year ends 31 March of following year
            $end_year = ($end_digit != 0) ? $start_year + 1 : $start_year + 10;
            $data_to = "31 Mar {$end_year}";

            return array($fiscal_year, $data_to, true);
        }

        if ($date_match) {
            // Partial year with full date
            $day = (int) $date_matches[1];
            $month = (int) $date_matches[2];
            $year = (int) $date_matches[3];

            // Determine fiscal year (April start)
            if ($month >= 4) {
                $fiscal_year = "{$year}-" . (($year + 1) % 10);
            } else {
                $fiscal_year = ($year - 1) . "-" . ($year % 10);
            }

            $data_to = "{$day} " . self::$month_names[$month] . " {$year}";
            return array($fiscal_year, $data_to, false);
        }

        if ($year_match && $short_date_match) {
            // Year with short date "2025-6 to 30-11"
            $start_year = (int) $year_matches[1];
            $end_digit = (int) $year_matches[2];
            $fiscal_year = "{$start_year}-{$end_digit}";

            $day = (int) $short_matches[1];
            $month = (int) $short_matches[2];

            // Determine the year for the date
            $date_year = ($month >= 4) ? $start_year : $start_year + 1;

            $data_to = "{$day} " . self::$month_names[$month] . " {$date_year}";
            return array($fiscal_year, $data_to, false);
        }

        return array('Unknown', null, false);
    }

    /**
     * Parse a CSV file and return array of row data
     *
     * @param string $filepath Full path to CSV file
     * @return array Array of associative arrays (one per row)
     */
    private function parse_csv_file($filepath) {
        $rows = array();

        if (($handle = fopen($filepath, 'r')) === false) {
            return $rows;
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return $rows;
        }

        // Trim headers
        $headers = array_map('trim', $headers);

        // Read data rows
        while (($data = fgetcsv($handle)) !== false) {
            $row = array();
            foreach ($headers as $i => $header) {
                $row[$header] = isset($data[$i]) ? trim($data[$i]) : '';
            }

            // Convert numeric fields
            foreach (array('amount', 'vat', 'total_amount') as $field) {
                if (isset($row[$field])) {
                    $row[$field] = $this->parse_number($row[$field]);
                } else {
                    $row[$field] = 0;
                }
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Parse a number from string, handling empty/invalid values
     *
     * @param mixed $value The value to parse
     * @return float Parsed number or 0
     */
    private function parse_number($value) {
        if ($value === '' || $value === null) {
            return 0;
        }
        $num = floatval($value);
        return is_nan($num) ? 0 : $num;
    }

    /**
     * Load opening balances from balances.json file
     *
     * @param string $data_dir Path to directory containing balances.json
     * @return array Associative array of fiscal_year => opening_balance
     */
    private function load_balances($data_dir) {
        $balances_file = $data_dir . '/balances.json';

        if (!file_exists($balances_file)) {
            return array();
        }

        $json = file_get_contents($balances_file);
        if ($json === false) {
            return array();
        }

        $balances = json_decode($json, true);
        if (!is_array($balances)) {
            return array();
        }

        return $balances;
    }
}
