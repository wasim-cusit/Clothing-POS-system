<?php
/**
 * Settings Helper Functions
 * Provides easy access to application settings
 */

/**
 * Get a setting value by key
 * @param string $key Setting key
 * @param string $default Default value if setting not found
 * @return string Setting value
 */
function get_setting($key, $default = '') {
    global $pdo;
    
    try {
        // First check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() == 0) {
            // Settings table doesn't exist, return default
            return $default;
        }
        
        // Query the settings table
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we have a result, return it (even if it's "0" or other valid values)
        if ($result && $result['setting_value'] !== null) {
            return $result['setting_value'];
        }
        
        // If no result, return default
        return $default;
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Error in get_setting for key '{$key}': " . $e->getMessage());
        
        // Only fallback to system_settings for specific legacy keys
        $legacy_keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_logo', 'currency_symbol'];
        
        if (in_array($key, $legacy_keys)) {
            try {
                $stmt = $pdo->prepare("SELECT company_name, company_address, company_phone, company_email, company_logo, currency FROM system_settings LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $fallback_map = [
                        'company_name' => $result['company_name'],
                        'company_address' => $result['company_address'],
                        'company_phone' => $result['company_phone'],
                        'company_email' => $result['company_email'],
                        'company_logo' => $result['company_logo'],
                        'currency_symbol' => $result['currency']
                    ];
                    
                    if (isset($fallback_map[$key]) && $fallback_map[$key] !== null) {
                        return $fallback_map[$key];
                    }
                }
            } catch (Exception $e2) {
                // If fallback also fails, return default
                error_log("Fallback error in get_setting for key '{$key}': " . $e2->getMessage());
            }
        }
        
        return $default;
    }
}

/**
 * Set a setting value
 * @param string $key Setting key
 * @param string $value Setting value
 * @param string $description Setting description (optional)
 * @return bool Success status
 */
function set_setting($key, $value, $description = '') {
    global $pdo;
    
    try {
        // First check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() == 0) {
            // Settings table doesn't exist, create it
            create_settings_table();
        }
        
        // Insert or update the setting
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            setting_description = VALUES(setting_description),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($stmt->execute([$key, $value, $description])) {
            // Also update system_settings table for backward compatibility (only for legacy keys)
            $legacy_keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_logo', 'currency_symbol'];
            if (in_array($key, $legacy_keys)) {
                update_system_settings_backward_compatibility($key, $value);
            }
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error in set_setting for key '{$key}': " . $e->getMessage());
        
        // Only fallback to system_settings for specific legacy keys
        $legacy_keys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_logo', 'currency_symbol'];
        if (in_array($key, $legacy_keys)) {
            return update_system_settings_backward_compatibility($key, $value);
        }
        
        return false;
    }
}

/**
 * Create the settings table if it doesn't exist
 * @return bool Success status
 */
function create_settings_table() {
    global $pdo;
    
    try {
        // Create settings table
        $sql = "CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(255) NOT NULL,
            `setting_value` text,
            `setting_description` text,
            `setting_type` varchar(50) DEFAULT 'text',
            `setting_group` varchar(100) DEFAULT 'general',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`),
            KEY `setting_group` (`setting_group`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if ($pdo->exec($sql)) {
            // Insert default settings if table is empty
            $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $default_settings = [
                    ['company_name', 'WASIM', 'Company/Business Name'],
                    ['company_tagline', 'Professional Tailoring Services', 'Company Tagline or Description'],
                    ['company_phone', '+92 323 9507813', 'Company Phone Number'],
                    ['company_email', 'info@tailorshop.com', 'Company Email Address'],
                    ['company_address', 'Address shop #1 hameed plaza main university road Pakistan', 'Company Address'],
                    ['company_website', 'www.tailorshop.com', 'Company Website'],
                    ['company_logo', '', 'Company Logo URL (optional)'],
                    ['currency_symbol', 'PKR', 'Currency Symbol'],
                    ['currency_name', 'Pakistani Rupee', 'Currency Name'],
                    ['invoice_prefix', 'INV', 'Invoice Number Prefix'],
                    ['purchase_prefix', 'PUR', 'Purchase Invoice Prefix'],
                    ['sale_prefix', 'SALE', 'Sale Invoice Prefix'],
                    ['footer_text', 'Thank you for your business!', 'Footer Text for Invoices'],
                    ['print_header', 'Computer Generated Invoice', 'Print Header Text'],
                    ['low_stock_threshold', '10', 'Low Stock Alert Threshold'],
                    ['business_hours', '9:00 AM - 6:00 PM', 'Business Hours'],
                    ['business_days', 'Monday - Saturday', 'Business Days'],
                    ['date_format', 'd/m/Y', 'Date Format'],
                    ['time_format', 'H:i:s', 'Time Format'],
                    ['auto_backup', '1', 'Enable Auto Backup'],
                    ['backup_frequency', 'weekly', 'Backup Frequency'],
                    ['backup_retention', '30', 'Backup Retention Days'],
                    ['backup_location', 'backups/', 'Backup Storage Location'],
                    ['backup_type', 'full', 'Backup Type'],
                    ['enable_notifications', '1', 'Enable Notifications System'],
                    ['show_notification_badge', '1', 'Show Notification Badge'],
                    ['email_notifications', '0', 'Enable Email Notifications'],
                    ['smtp_server', 'smtp.gmail.com', 'SMTP Server'],
                    ['smtp_port', '587', 'SMTP Port'],
                    ['smtp_username', '', 'SMTP Username'],
                    ['smtp_password', '', 'SMTP Password'],
                    ['from_email', 'noreply@tailorshop.com', 'From Email Address'],
                    ['sms_notifications', '0', 'Enable SMS Notifications'],
                    ['sms_provider', 'twilio', 'SMS Provider'],
                    ['sms_api_key', '', 'SMS API Key'],
                    ['sms_api_secret', '', 'SMS API Secret'],
                    ['low_stock_alerts', '1', 'Enable Low Stock Alerts'],
                    ['payment_reminders', '1', 'Enable Payment Reminders'],
                    ['order_status_updates', '1', 'Enable Order Status Updates'],
                    ['delivery_reminders', '1', 'Enable Delivery Reminders'],
                    ['notification_auto_hide', '5', 'Notification Auto-Hide Duration'],
                    ['notification_position', 'top-right', 'Notification Position'],
                    ['notification_sound', '0', 'Enable Notification Sounds'],
                    ['notification_sound_type', 'default', 'Notification Sound Type']
                ];
                
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
                
                foreach ($default_settings as $setting) {
                    $stmt->execute($setting);
                }
            }
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error creating settings table: " . $e->getMessage());
        return false;
    }
}

/**
 * Update system_settings table for backward compatibility
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool Success status
 */
function update_system_settings_backward_compatibility($key, $value) {
    global $pdo;
    
    try {
        $mapping = [
            'company_name' => 'company_name',
            'company_address' => 'company_address',
            'company_phone' => 'company_phone',
            'company_email' => 'company_email',
            'company_logo' => 'company_logo',
            'currency_symbol' => 'currency'
        ];
        
        if (isset($mapping[$key])) {
            $column = $mapping[$key];
            $stmt = $pdo->prepare("UPDATE system_settings SET $column = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
            return $stmt->execute([$value]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all settings as an associative array
 * @return array All settings
 */
function get_all_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_description, setting_type, setting_group FROM settings ORDER BY setting_group, setting_key");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['setting_description'],
                'type' => $row['setting_type'],
                'group' => $row['setting_group']
            ];
        }
        
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get settings by group
 * @param string $group Group name
 * @return array Settings in the specified group
 */
function get_settings_by_group($group) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_description, setting_type FROM settings WHERE setting_group = ? ORDER BY setting_key");
        $stmt->execute([$group]);
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['setting_description'],
                'type' => $row['setting_type']
            ];
        }
        
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get company information as an array
 * @return array Company information
 */
function get_company_info() {
    return [
        'name' => get_setting('company_name', 'WASIM'),
        'tagline' => get_setting('company_tagline', 'Professional Tailoring Services'),
        'phone' => get_setting('company_phone', '+92 323 9507813'),
        'email' => get_setting('company_email', 'info@tailorshop.com'),
        'address' => get_setting('company_address', 'Address shop #1 hameed plaza main university road, Peshawar, Pakistan'),
        'website' => get_setting('company_website', 'www.tailorshop.com'),
        'logo' => get_setting('company_logo', ''),
        'currency_symbol' => get_setting('currency_symbol', 'PKR'),
        'currency_name' => get_setting('currency_name', 'Pakistani Rupee'),
        'business_hours' => get_setting('business_hours', '9:00 AM - 6:00 PM'),
        'business_days' => get_setting('business_days', 'Monday - Saturday')
    ];
}

/**
 * Format currency with proper symbol
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function format_currency($amount) {
    $symbol = get_setting('currency_symbol', 'PKR');
    return $symbol . ' ' . number_format($amount, 2);
}

/**
 * Format date according to settings
 * @param string $date Date string
 * @return string Formatted date
 */
function format_date($date) {
    $format = get_setting('date_format', 'd/m/Y');
    return date($format, strtotime($date));
}

/**
 * Format time according to settings
 * @param string $time Time string
 * @return string Formatted time
 */
function format_time($time) {
    $format = get_setting('time_format', 'H:i:s');
    return date($format, strtotime($time));
}

/**
 * Generate invoice number with prefix
 * @param string $type Type of invoice (purchase, sale, etc.)
 * @return string Generated invoice number
 */
function generate_invoice_number($type = 'invoice') {
    $prefix = get_setting($type . '_prefix', 'INV');
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid()), 0, 4));
    return $prefix . '-' . $date . '-' . $random;
}

/**
 * Check if a setting is enabled (boolean)
 * @param string $key Setting key
 * @return bool True if enabled
 */
function is_setting_enabled($key) {
    $value = get_setting($key, '0');
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
}

/**
 * Get setting options for select fields
 * @param string $key Setting key
 * @return array Available options
 */
function get_setting_options($key) {
    $options_map = [
        'date_format' => [
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            'Y-m-d' => 'YYYY-MM-DD',
            'd-m-Y' => 'DD-MM-YYYY'
        ],
        'time_format' => [
            'H:i:s' => '24 Hour (HH:MM:SS)',
            'h:i:s A' => '12 Hour (HH:MM:SS AM/PM)',
            'H:i' => '24 Hour (HH:MM)',
            'h:i A' => '12 Hour (HH:MM AM/PM)'
        ],
        'backup_frequency' => [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly'
        ],
        'backup_type' => [
            'full' => 'Full Backup (Database + Files)',
            'database' => 'Database Only',
            'files' => 'Files Only'
        ],
        'default_language' => [
            'en' => 'English',
            'ur' => 'Urdu',
            'ar' => 'Arabic'
        ]
    ];
    
    return isset($options_map[$key]) ? $options_map[$key] : [];
}

/**
 * Reset settings to defaults
 * @return bool Success status
 */
function reset_settings_to_defaults() {
    global $pdo;
    
    try {
        // Delete all current settings
        $pdo->exec("DELETE FROM settings");
        
        // Re-run the default settings insertion
        $default_settings = [
            ['company_name', 'WASEM WEARS', 'Company/Business Name'],
            ['company_tagline', 'Professional Tailoring Services', 'Company Tagline or Description'],
            ['company_phone', '+92 323 9507813', 'Company Phone Number'],
            ['company_email', 'info@tailorshop.com', 'Company Email Address'],
            ['company_address', 'Address shop #1 hameed plaza main university road Pakistan', 'Company Address'],
            ['company_website', 'www.tailorshop.com', 'Company Website'],
            ['company_logo', '', 'Company Logo URL (optional)'],
            ['currency_symbol', 'PKR', 'Currency Symbol'],
            ['currency_name', 'Pakistani Rupee', 'Currency Name'],
            ['invoice_prefix', 'INV', 'Invoice Number Prefix'],
            ['purchase_prefix', 'PUR', 'Purchase Invoice Prefix'],
            ['sale_prefix', 'SALE', 'Sale Invoice Prefix'],
            ['footer_text', 'Thank you for your business!', 'Footer Text for Invoices'],
            ['print_header', 'Computer Generated Invoice', 'Print Header Text'],
            ['low_stock_threshold', '10', 'Low Stock Alert Threshold'],
            ['business_hours', '9:00 AM - 6:00 PM', 'Business Hours'],
            ['business_days', 'Monday - Saturday', 'Business Days'],
            ['date_format', 'd/m/Y', 'Date Format'],
            ['time_format', 'H:i:s', 'Time Format'],
            ['auto_backup', '1', 'Enable Auto Backup'],
            ['backup_frequency', 'weekly', 'Backup Frequency'],
            ['backup_retention', '30', 'Backup Retention Days'],
            ['backup_location', 'backups/', 'Backup Storage Location'],
            ['backup_type', 'full', 'Backup Type'],
            // Notification Settings
            ['enable_notifications', '1', 'Enable Notifications System'],
            ['show_notification_badge', '1', 'Show Notification Badge'],
            ['email_notifications', '0', 'Enable Email Notifications'],
            ['smtp_server', 'smtp.gmail.com', 'SMTP Server'],
            ['smtp_port', '587', 'SMTP Port'],
            ['smtp_username', '', 'SMTP Username'],
            ['smtp_password', '', 'SMTP Password'],
            ['from_email', 'noreply@tailorshop.com', 'From Email Address'],
            ['sms_notifications', '0', 'Enable SMS Notifications'],
            ['sms_provider', 'twilio', 'SMS Provider'],
            ['sms_api_key', '', 'SMS API Key'],
            ['sms_api_secret', '', 'SMS API Secret'],
            ['low_stock_alerts', '1', 'Enable Low Stock Alerts'],
            ['payment_reminders', '1', 'Enable Payment Reminders'],
            ['order_status_updates', '1', 'Enable Order Status Updates'],
            ['delivery_reminders', '1', 'Enable Delivery Reminders'],
            ['notification_auto_hide', '5', 'Notification Auto-Hide Duration'],
            ['notification_position', 'top-right', 'Notification Position'],
            ['notification_sound', '0', 'Enable Notification Sounds'],
            ['notification_sound_type', 'default', 'Notification Sound Type']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_description) VALUES (?, ?, ?)");
        
        foreach ($default_settings as $setting) {
            $stmt->execute($setting);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create database backup
 * @param string $backup_dir Backup directory
 * @param string $filename Backup filename
 * @return bool Success status
 */
function create_database_backup($backup_dir, $filename) {
    global $pdo;
    
    try {
        $backup_file = $backup_dir . $filename . '.sql';
        
        // Create SQL backup content
        $sql_content = "-- Tailor Management System Database Backup\n";
        $sql_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- Database: tailor_db\n\n";
        
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        // Backup each table
        foreach ($tables as $table) {
            $sql_content .= "-- Table structure for table `{$table}`\n";
            
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql_content .= $row[1] . ";\n\n";
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $sql_content .= "-- Data for table `{$table}`\n";
                $sql_content .= "INSERT INTO `{$table}` VALUES\n";
                
                $insert_values = [];
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $insert_values[] = "(" . implode(', ', $values) . ")";
                }
                
                $sql_content .= implode(",\n", $insert_values) . ";\n\n";
            }
        }
        
        // Write to file
        if (file_put_contents($backup_file, $sql_content)) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create files backup
 * @param string $backup_dir Backup directory
 * @param string $filename Backup filename
 * @return bool Success status
 */
function create_files_backup($backup_dir, $filename) {
    try {
        $backup_file = $backup_dir . $filename . '.zip';
        
        // Create a simple file list backup for now
        $file_list = [];
        scan_directory('.', $file_list);
        
        $content = json_encode($file_list, JSON_PRETTY_PRINT);
        file_put_contents($backup_file, $content);
        
        return file_exists($backup_file);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create full backup
 * @param string $backup_dir Backup directory
 * @param string $filename Backup filename
 * @return bool Success status
 */
function create_full_backup($backup_dir, $filename) {
    // For now, just create database backup
    // In a real implementation, you'd backup both database and files
    return create_database_backup($backup_dir, $filename);
}

/**
 * Scan directory for files
 * @param string $dir Directory to scan
 * @param array &$files Array to store file information
 * @param array $exclude Directories to exclude
 */
function scan_directory($dir, &$files, $exclude = ['vendor', 'node_modules', '.git', 'uploads']) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $exclude)) continue;
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            scan_directory($path, $files, $exclude);
        } else {
            $files[] = [
                'path' => $path,
                'size' => filesize($path),
                'mtime' => filemtime($path)
            ];
        }
    }
}

/**
 * Get backup files list
 * @return array List of backup files
 */
function get_backup_files() {
    $backup_dir = get_setting('backup_location', 'backups/');
    $backup_dir = rtrim($backup_dir, '/') . '/';
    
    if (!is_dir($backup_dir)) {
        return [];
    }
    
    $files = [];
    $items = scandir($backup_dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $file_path = $backup_dir . $item;
        if (is_file($file_path)) {
            $type = 'unknown';
            if (strpos($item, 'database') !== false) $type = 'database';
            elseif (strpos($item, 'files') !== false) $type = 'files';
            elseif (strpos($item, 'full') !== false) $type = 'full';
            
            $files[] = [
                'name' => $item,
                'type' => $type,
                'size' => filesize($file_path),
                'mtime' => filemtime($file_path)
            ];
        }
    }
    
    // Sort by creation time (newest first)
    usort($files, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    return $files;
}

/**
 * Format file size for display
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Debug function to check settings table status
 * @return array Debug information
 */
function debug_settings_table() {
    global $pdo;
    
    $debug = [];
    
    try {
        // Check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        $debug['table_exists'] = $stmt->rowCount() > 0;
        
        if ($debug['table_exists']) {
            // Get table structure
            $stmt = $pdo->query("DESCRIBE settings");
            $debug['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get settings count
            $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
            $debug['settings_count'] = $stmt->fetchColumn();
            
            // Get sample settings
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings LIMIT 5");
            $debug['sample_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Check system_settings table
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
            $debug['system_settings_exists'] = $stmt->rowCount() > 0;
            
            if ($debug['system_settings_exists']) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
                $debug['system_settings_count'] = $stmt->fetchColumn();
            }
        } catch (Exception $e) {
            $debug['system_settings_error'] = $e->getMessage();
        }
        
    } catch (Exception $e) {
        $debug['error'] = $e->getMessage();
    }
    
    return $debug;
}
?>
