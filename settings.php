<?php
require_once 'includes/auth.php';
require_login();
require_once 'includes/config.php';
require_once 'includes/settings.php';
require_once 'includes/notifications.php';

$activePage = 'settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $success = true;
    $error = '';
    
    try {
        // Company Information
        set_setting('company_name', trim($_POST['company_name']), 'Company/Business Name');
        set_setting('company_tagline', trim($_POST['company_tagline']), 'Company Tagline or Description');
        set_setting('company_phone', trim($_POST['company_phone']), 'Company Phone Number');
        set_setting('company_email', trim($_POST['company_email']), 'Company Email Address');
        set_setting('company_address', trim($_POST['company_address']), 'Company Address');
        set_setting('company_website', trim($_POST['company_website']), 'Company Website');
        set_setting('company_logo', trim($_POST['company_logo']), 'Company Logo URL (optional)');
        
        // Currency Settings
        set_setting('currency_symbol', trim($_POST['currency_symbol']), 'Currency Symbol');
        set_setting('currency_name', trim($_POST['currency_name']), 'Currency Name');
        
        // Invoice Settings
        set_setting('invoice_prefix', trim($_POST['invoice_prefix']), 'Invoice Number Prefix');
        set_setting('purchase_prefix', trim($_POST['purchase_prefix']), 'Purchase Invoice Prefix');
        set_setting('sale_prefix', trim($_POST['sale_prefix']), 'Sale Invoice Prefix');
        
        // Business Settings
        set_setting('footer_text', trim($_POST['footer_text']), 'Footer Text for Invoices');
        set_setting('print_header', trim($_POST['print_header']), 'Print Header Text');
        set_setting('low_stock_threshold', trim($_POST['low_stock_threshold']), 'Low Stock Alert Threshold');
        set_setting('business_hours', trim($_POST['business_hours']), 'Business Hours');
        set_setting('business_days', trim($_POST['business_days']), 'Business Days');
        
        // Date/Time Settings
        set_setting('date_format', $_POST['date_format'], 'Date Format');
        set_setting('time_format', $_POST['time_format'], 'Time Format');
        
        // System Settings
        set_setting('system_name', trim($_POST['system_name']), 'System Name');
        set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0', 'Maintenance Mode');
        set_setting('default_language', $_POST['default_language'], 'Default Language');
        set_setting('timezone', $_POST['timezone'], 'System Timezone');
        
        // Notification Settings
        set_setting('enable_notifications', isset($_POST['enable_notifications']) ? '1' : '0', 'Enable Notifications System');
        set_setting('show_notification_badge', isset($_POST['show_notification_badge']) ? '1' : '0', 'Show Notification Badge');
        set_setting('email_notifications', isset($_POST['email_notifications']) ? '1' : '0', 'Enable Email Notifications');
        set_setting('smtp_server', trim($_POST['smtp_server']), 'SMTP Server');
        set_setting('smtp_port', trim($_POST['smtp_port']), 'SMTP Port');
        set_setting('smtp_username', trim($_POST['smtp_username']), 'SMTP Username');
        set_setting('smtp_password', trim($_POST['smtp_password']), 'SMTP Password');
        set_setting('from_email', trim($_POST['from_email']), 'From Email');
        set_setting('sms_notifications', isset($_POST['sms_notifications']) ? '1' : '0', 'Enable SMS Notifications');
        set_setting('sms_provider', trim($_POST['sms_provider']), 'SMS Provider');
        set_setting('sms_api_key', trim($_POST['sms_api_key']), 'SMS API Key');
        set_setting('sms_api_secret', trim($_POST['sms_api_secret']), 'SMS API Secret');
        set_setting('low_stock_alerts', isset($_POST['low_stock_alerts']) ? '1' : '0', 'Enable Low Stock Alerts');
        set_setting('payment_reminders', isset($_POST['payment_reminders']) ? '1' : '0', 'Enable Payment Reminders');
        set_setting('order_status_updates', isset($_POST['order_status_updates']) ? '1' : '0', 'Enable Order Status Updates');
        set_setting('delivery_reminders', isset($_POST['delivery_reminders']) ? '1' : '0', 'Enable Delivery Reminders');
        set_setting('notification_auto_hide', trim($_POST['notification_auto_hide']), 'Auto-Hide Duration');
        set_setting('notification_position', trim($_POST['notification_position']), 'Notification Position');
        set_setting('notification_sound', isset($_POST['notification_sound']) ? '1' : '0', 'Enable Notification Sounds');
        set_setting('notification_sound_type', trim($_POST['notification_sound_type']), 'Sound Type');
        
        // Backup Settings
        set_setting('auto_backup', isset($_POST['auto_backup']) ? '1' : '0', 'Enable Auto Backup');
        set_setting('backup_frequency', $_POST['backup_frequency'], 'Backup Frequency');
        set_setting('backup_retention', trim($_POST['backup_retention']), 'Backup Retention Days');
        set_setting('backup_location', trim($_POST['backup_location']), 'Backup Storage Location');
        set_setting('backup_type', $_POST['backup_type'], 'Backup Type');
        
        header("Location: settings.php?success=updated");
        exit;
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
        $success = false;
    }
}

// Handle reset to defaults
if (isset($_GET['reset']) && $_GET['reset'] === 'defaults') {
    if (reset_settings_to_defaults()) {
        header("Location: settings.php?success=reset");
        exit;
    } else {
        $error = "Error resetting settings to defaults";
    }
}

// Get current settings
$company_info = get_company_info();
$all_settings = get_all_settings();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-4 py-5" style="margin-top: 25px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-gear me-2"></i>System Settings</h2>
                <div>
                    <a href="?reset=defaults" class="btn btn-warning me-2" onclick="return confirm('Are you sure you want to reset all settings to defaults?')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset to Defaults
                    </a>
                    <button type="submit" form="settingsForm" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    if ($_GET['success'] === 'updated') echo "Settings updated successfully!";
                    if ($_GET['success'] === 'reset') echo "Settings reset to defaults successfully!";
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" id="settingsForm">
                <!-- Company Information -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Company Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company_info['name']) ?>" required>
                                <div class="form-text">Your business name as it appears on invoices and reports</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Company Tagline</label>
                                <input type="text" name="company_tagline" class="form-control" value="<?= htmlspecialchars($company_info['tagline']) ?>">
                                <div class="form-text">A short description or slogan for your business</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="company_phone" class="form-control" value="<?= htmlspecialchars($company_info['phone']) ?>" required>
                                <div class="form-text">Primary contact number for your business</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($company_info['email']) ?>" required>
                                <div class="form-text">Primary email address for business communications</div>
                            </div>
                        </div>
                        <div class="row">
                            <!-- <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Website</label>
                                <input type="url" name="company_website" class="form-control" value="<?= htmlspecialchars($company_info['website']) ?>" placeholder="https://example.com">
                                <div class="form-text">Your business website URL</div>
                            </div> -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Company Logo URL</label>
                                <input type="url" name="company_logo" class="form-control" value="<?= htmlspecialchars($company_info['logo']) ?>" placeholder="https://example.com/logo.png">
                                <div class="form-text">URL to your company logo (optional)</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Business Hours</label>
                                <input type="text" name="business_hours" class="form-control" value="<?= htmlspecialchars($company_info['business_hours']) ?>" placeholder="9:00 AM - 6:00 PM">
                                <div class="form-text">Your regular business operating hours</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Business Days</label>
                                <input type="text" name="business_days" class="form-control" value="<?= htmlspecialchars($company_info['business_days']) ?>" placeholder="Monday - Saturday">
                                <div class="form-text">Days of the week you operate</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Address <span class="text-danger">*</span></label>
                                <textarea name="company_address" class="form-control" rows="3" required><?= htmlspecialchars($company_info['address']) ?></textarea>
                                <div class="form-text">Complete business address for invoices and reports</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Currency Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Currency Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Currency Symbol <span class="text-danger">*</span></label>
                                <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($company_info['currency_symbol']) ?>" required maxlength="5">
                                <div class="form-text">Currency symbol (e.g., PKR, $, €, £)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Currency Name <span class="text-danger">*</span></label>
                                <input type="text" name="currency_name" class="form-control" value="<?= htmlspecialchars($company_info['currency_name']) ?>" required>
                                <div class="form-text">Full name of the currency (e.g., Pakistani Rupee)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice & Print Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-printer me-2"></i>Invoice & Print Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Invoice Prefix <span class="text-danger">*</span></label>
                                <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars(get_setting('invoice_prefix', 'INV')) ?>" required maxlength="10">
                                <div class="form-text">Prefix for general invoices</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Purchase Prefix <span class="text-danger">*</span></label>
                                <input type="text" name="purchase_prefix" class="form-control" value="<?= htmlspecialchars(get_setting('purchase_prefix', 'PUR')) ?>" required maxlength="10">
                                <div class="form-text">Prefix for purchase invoices</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Sale Prefix <span class="text-danger">*</span></label>
                                <input type="text" name="sale_prefix" class="form-control" value="<?= htmlspecialchars(get_setting('sale_prefix', 'SALE')) ?>" required maxlength="10">
                                <div class="form-text">Prefix for sale invoices</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Low Stock Threshold</label>
                                <input type="number" name="low_stock_threshold" class="form-control" value="<?= htmlspecialchars(get_setting('low_stock_threshold', '10')) ?>" min="1" max="1000">
                                <div class="form-text">Stock level to trigger low stock alerts</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Footer Text for Invoices</label>
                                <textarea name="footer_text" class="form-control" rows="2"><?= htmlspecialchars(get_setting('footer_text', 'Thank you for your business!')) ?></textarea>
                                <div class="form-text">Text to display at the bottom of invoices</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Print Header Text</label>
                                <textarea name="print_header" class="form-control" rows="2"><?= htmlspecialchars(get_setting('print_header', 'Computer Generated Invoice')) ?></textarea>
                                <div class="form-text">Header text for printed documents</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date/Time Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Date & Time Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Date Format</label>
                                <select name="date_format" class="form-control">
                                    <option value="d/m/Y" <?= get_setting('date_format') == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                                    <option value="m/d/Y" <?= get_setting('date_format') == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                    <option value="Y-m-d" <?= get_setting('date_format') == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                    <option value="d-m-Y" <?= get_setting('date_format') == 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY</option>
                                </select>
                                <div class="form-text">How dates will be displayed throughout the system</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Time Format</label>
                                <select name="time_format" class="form-control">
                                    <option value="H:i:s" <?= get_setting('time_format') == 'H:i:s' ? 'selected' : '' ?>>24 Hour (HH:MM:SS)</option>
                                    <option value="h:i:s A" <?= get_setting('time_format') == 'h:i:s A' ? 'selected' : '' ?>>12 Hour (HH:MM:SS AM/PM)</option>
                                    <option value="H:i" <?= get_setting('time_format') == 'H:i' ? 'selected' : '' ?>>24 Hour (HH:MM)</option>
                                    <option value="h:i A" <?= get_setting('time_format') == 'h:i A' ? 'selected' : '' ?>>12 Hour (HH:MM AM/PM)</option>
                                </select>
                                <div class="form-text">How times will be displayed throughout the system</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>System Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">System Name</label>
                                <input type="text" name="system_name" class="form-control" value="<?= htmlspecialchars(get_setting('system_name', 'Tailor Management System')) ?>">
                                <div class="form-text">Name displayed in the system header and title</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Default Language</label>
                                <select name="default_language" class="form-control">
                                    <option value="en" <?= get_setting('default_language') == 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="ur" <?= get_setting('default_language') == 'ur' ? 'selected' : '' ?>>Urdu</option>
                                    <option value="ar" <?= get_setting('default_language') == 'ar' ? 'selected' : '' ?>>Arabic</option>
                                </select>
                                <div class="form-text">Default language for the system interface</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Timezone</label>
                                <input type="text" name="timezone" class="form-control" value="<?= htmlspecialchars(get_setting('timezone', 'Asia/Karachi')) ?>" placeholder="Asia/Karachi">
                                <div class="form-text">System timezone (e.g., Asia/Karachi, America/New_York)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" <?= is_setting_enabled('maintenance_mode') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="maintenance_mode">
                                        Maintenance Mode
                                    </label>
                                    <div class="form-text">Enable to put the system in maintenance mode</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <!-- General Notification Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3"><i class="bi bi-gear me-2"></i>General Notification Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_notifications" id="enable_notifications" <?= is_setting_enabled('enable_notifications') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="enable_notifications">
                                        Enable Notifications System
                                    </label>
                                    <div class="form-text">Master switch for all notification features</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_notification_badge" id="show_notification_badge" <?= is_setting_enabled('show_notification_badge') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="show_notification_badge">
                                        Show Notification Badge
                                    </label>
                                    <div class="form-text">Display unread notification count in header</div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Notification Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-success mb-3"><i class="bi bi-envelope me-2"></i>Email Notification Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?= is_setting_enabled('email_notifications') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="email_notifications">
                                        Enable Email Notifications
                                    </label>
                                    <div class="form-text">Send notifications via email</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Server</label>
                                <input type="text" name="smtp_server" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_server', 'smtp.gmail.com')) ?>" placeholder="smtp.gmail.com">
                                <div class="form-text">SMTP server for sending emails</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_port', '587')) ?>" placeholder="587">
                                <div class="form-text">SMTP port number</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Username</label>
                                <input type="email" name="smtp_username" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_username', '')) ?>" placeholder="your-email@gmail.com">
                                <div class="form-text">Email address for SMTP authentication</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Password</label>
                                <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars(get_setting('smtp_password', '')) ?>" placeholder="App password or regular password">
                                <div class="form-text">Password for SMTP authentication</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">From Email</label>
                                <input type="email" name="from_email" class="form-control" value="<?= htmlspecialchars(get_setting('from_email', get_setting('company_email', 'noreply@tailorshop.com'))) ?>" placeholder="noreply@tailorshop.com">
                                <div class="form-text">Default sender email address</div>
                            </div>
                        </div>

                        <!-- SMS Notification Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-info mb-3"><i class="bi bi-phone me-2"></i>SMS Notification Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications" <?= is_setting_enabled('sms_notifications') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="sms_notifications">
                                        Enable SMS Notifications
                                    </label>
                                    <div class="form-text">Send notifications via SMS</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMS Provider</label>
                                <select name="sms_provider" class="form-control">
                                    <option value="twilio" <?= get_setting('sms_provider') == 'twilio' ? 'selected' : '' ?>>Twilio</option>
                                    <option value="nexmo" <?= get_setting('sms_provider') == 'nexmo' ? 'selected' : '' ?>>Nexmo (Vonage)</option>
                                    <option value="custom" <?= get_setting('sms_provider') == 'custom' ? 'selected' : '' ?>>Custom API</option>
                                </select>
                                <div class="form-text">SMS service provider</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMS API Key</label>
                                <input type="text" name="sms_api_key" class="form-control" value="<?= htmlspecialchars(get_setting('sms_api_key', '')) ?>" placeholder="Your SMS API key">
                                <div class="form-text">API key from your SMS provider</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMS API Secret</label>
                                <input type="password" name="sms_api_secret" class="form-control" value="<?= htmlspecialchars(get_setting('sms_api_secret', '')) ?>" placeholder="Your SMS API secret">
                                <div class="form-text">API secret from your SMS provider</div>
                            </div>
                        </div>

                        <!-- Business Notification Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-warning mb-3"><i class="bi bi-briefcase me-2"></i>Business Notification Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="low_stock_alerts" id="low_stock_alerts" <?= is_setting_enabled('low_stock_alerts') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="low_stock_alerts">
                                        Low Stock Alerts
                                    </label>
                                    <div class="form-text">Get notified when stock levels are low</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="payment_reminders" id="payment_reminders" <?= is_setting_enabled('payment_reminders') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="payment_reminders">
                                        Payment Reminders
                                    </label>
                                    <div class="form-text">Send payment reminder notifications</div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="order_status_updates" id="order_status_updates" <?= is_setting_enabled('order_status_updates') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="order_status_updates">
                                        Order Status Updates
                                    </label>
                                    <div class="form-text">Notify customers about order status changes</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="delivery_reminders" id="delivery_reminders" <?= is_setting_enabled('delivery_reminders') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="delivery_reminders">
                                        Delivery Reminders
                                    </label>
                                    <div class="form-text">Send delivery date reminders</div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Display Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-secondary mb-3"><i class="bi bi-display me-2"></i>Display Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Auto-Hide Duration</label>
                                <select name="notification_auto_hide" class="form-control">
                                    <option value="3" <?= get_setting('notification_auto_hide') == '3' ? 'selected' : '' ?>>3 seconds</option>
                                    <option value="5" <?= get_setting('notification_auto_hide') == '5' ? 'selected' : '' ?>>5 seconds (Default)</option>
                                    <option value="8" <?= get_setting('notification_auto_hide') == '8' ? 'selected' : '' ?>>8 seconds</option>
                                    <option value="10" <?= get_setting('notification_auto_hide') == '10' ? 'selected' : '' ?>>10 seconds</option>
                                    <option value="0" <?= get_setting('notification_auto_hide') == '0' ? 'selected' : '' ?>>Never (Manual close only)</option>
                                </select>
                                <div class="form-text">How long notifications stay visible</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Notification Position</label>
                                <select name="notification_position" class="form-control">
                                    <option value="top-right" <?= get_setting('notification_position') == 'top-right' ? 'selected' : '' ?>>Top Right</option>
                                    <option value="top-left" <?= get_setting('notification_position') == 'top-left' ? 'selected' : '' ?>>Top Left</option>
                                    <option value="bottom-right" <?= get_setting('notification_position') == 'bottom-right' ? 'selected' : '' ?>>Bottom Right</option>
                                    <option value="bottom-left" <?= get_setting('notification_position') == 'bottom-left' ? 'selected' : '' ?>>Bottom Left</option>
                                    <option value="center" <?= get_setting('notification_position') == 'center' ? 'selected' : '' ?>>Center</option>
                                </select>
                                <div class="form-text">Where notifications appear on screen</div>
                            </div>
                        </div>

                        <!-- Notification Sound Settings -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-dark mb-3"><i class="bi bi-volume-up me-2"></i>Sound Settings</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notification_sound" id="notification_sound" <?= is_setting_enabled('notification_sound') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="notification_sound">
                                        Enable Notification Sounds
                                    </label>
                                    <div class="form-text">Play sound when new notifications arrive</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Sound Type</label>
                                <select name="notification_sound_type" class="form-control">
                                    <option value="default" <?= get_setting('notification_sound_type') == 'default' ? 'selected' : '' ?>>Default</option>
                                    <option value="chime" <?= get_setting('notification_sound_type') == 'chime' ? 'selected' : '' ?>>Chime</option>
                                    <option value="bell" <?= get_setting('notification_sound_type') == 'bell' ? 'selected' : '' ?>>Bell</option>
                                    <option value="custom" <?= get_setting('notification_sound_type') == 'custom' ? 'selected' : '' ?>>Custom</option>
                                </select>
                                <div class="form-text">Type of notification sound</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Backup Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_backup" id="auto_backup" <?= is_setting_enabled('auto_backup') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="auto_backup">
                                        Auto Backup
                                    </label>
                                    <div class="form-text">Automatically backup the system</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Backup Frequency</label>
                                <select name="backup_frequency" class="form-control">
                                    <option value="daily" <?= get_setting('backup_frequency') == 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="weekly" <?= get_setting('backup_frequency') == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                    <option value="monthly" <?= get_setting('backup_frequency') == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                </select>
                                <div class="form-text">How often to perform backups</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Retention Days</label>
                                <input type="number" name="backup_retention" class="form-control" value="<?= htmlspecialchars(get_setting('backup_retention', '30')) ?>" min="1" max="365">
                                <div class="form-text">How long to keep backup files</div>
                            </div>
                        </div>
                        
                        <!-- Backup Configuration -->
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Backup Location</label>
                                <input type="text" name="backup_location" class="form-control" value="<?= htmlspecialchars(get_setting('backup_location', 'backups/')) ?>" placeholder="backups/">
                                <div class="form-text">Directory where backup files will be stored</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Backup Type</label>
                                <select name="backup_type" class="form-control">
                                    <option value="full" <?= get_setting('backup_type') == 'full' ? 'selected' : '' ?>>Full Backup (Database + Files)</option>
                                    <option value="database" <?= get_setting('backup_type') == 'database' ? 'selected' : '' ?>>Database Only</option>
                                    <option value="files" <?= get_setting('backup_type') == 'files' ? 'selected' : '' ?>>Files Only</option>
                                </select>
                                <div class="form-text">Type of backup to perform</div>
                            </div>
                        </div>
                        
                        <!-- Backup Status and Actions -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Backup Status</h6>
                                    <p class="mb-1"><strong>Last Backup:</strong> 
                                        <?php 
                                        $last_backup = get_setting('last_backup_date', 'Never');
                                        echo $last_backup !== 'Never' ? date('d/m/Y H:i', strtotime($last_backup)) : 'Never';
                                        ?>
                                    </p>
                                    <p class="mb-1"><strong>Next Backup:</strong> 
                                        <?php 
                                        if (is_setting_enabled('auto_backup')) {
                                            $frequency = get_setting('backup_frequency', 'daily');
                                            $last = get_setting('last_backup_date');
                                            if ($last && $last !== 'Never') {
                                                $next = '';
                                                switch($frequency) {
                                                    case 'daily':
                                                        $next = date('d/m/Y H:i', strtotime($last . ' +1 day'));
                                                        break;
                                                    case 'weekly':
                                                        $next = date('d/m/Y H:i', strtotime($last . ' +1 week'));
                                                        break;
                                                    case 'monthly':
                                                        $next = date('d/m/Y H:i', strtotime($last . ' +1 month'));
                                                        break;
                                                }
                                                echo $next;
                                            } else {
                                                echo 'Not scheduled';
                                            }
                                        } else {
                                            echo 'Auto backup disabled';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid gap-2">
                                    <a href="backup.php" class="btn btn-success">
                                        <i class="bi bi-download me-2"></i>Create Manual Backup
                                    </a>
                                    <a href="backup.php" class="btn btn-info">
                                        <i class="bi bi-clock-history me-2"></i>View Backup History
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mb-4">
                    <button type="submit" class="btn btn-primary btn-lg" name="update_settings">
                        <i class="bi bi-save me-2"></i>Save All Settings
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
// Auto-save functionality
let autoSaveTimer;
const form = document.getElementById('settingsForm');
const inputs = form.querySelectorAll('input, select, textarea');

inputs.forEach(input => {
    input.addEventListener('change', () => {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            // Show auto-save indicator
            const saveBtn = form.querySelector('button[type="submit"]');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="bi bi-clock me-2"></i>Auto-saving...';
            saveBtn.disabled = true;
            
            // Auto-save after 2 seconds of no changes
            setTimeout(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }, 2000);
        }, 2000);
    });
});

// Form validation
form.addEventListener('submit', function(e) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields marked with *');
    }
});

// Real-time preview for company name
document.querySelector('input[name="company_name"]').addEventListener('input', function() {
    const preview = document.querySelector('.navbar-brand') || document.title;
    if (preview) {
        preview.textContent = this.value || 'TAILOR SHOP';
    }
});

// Play notification sound
function playNotificationSound() {
    const soundType = document.querySelector('select[name="notification_sound_type"]').value;
    
    try {
        // Create audio context for custom sounds
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        if (soundType === 'custom') {
            // For custom sounds, you would load an audio file
            // For now, we'll use a simple beep
            playBeep(audioContext);
        } else {
            // Generate different tones for different sound types
            const frequencies = {
                'default': 800,
                'chime': 1200,
                'bell': 600
            };
            
            const frequency = frequencies[soundType] || 800;
            playBeep(audioContext, frequency);
        }
    } catch (error) {
        // Audio playback not supported
    }
}

// Generate a simple beep sound
function playBeep(audioContext, frequency = 800, duration = 200) {
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = frequency;
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + duration / 1000);
}

</script>

<?php include 'includes/footer.php'; ?>
