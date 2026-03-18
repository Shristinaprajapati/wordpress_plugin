<?php
/**
 * Plugin Name: Perpetual Register Admin
 * Description: Admin interface for uploading CSV files and displaying Perpetual Register tabs
 * Version: 1.0
 */

//Create db table
register_activation_hook(__FILE__, 'pra_create_table');
function pra_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'perpetual_register';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table with all 4 columns matching your database
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL,
        entry varchar(255) NOT NULL,
        lifeStats text,
        sort varchar(255) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

//upload interface

// Add admin menu
add_action('admin_menu', 'pra_add_admin_menu');
function pra_add_admin_menu() {
    add_menu_page(
        'Perpetual Register Manager',
        'Perpetual Register',
        'manage_options',
        'perpetual-register',
        'pra_admin_page',
        'dashicons-list-view',
        30
    );
}

// Admin page HTML
function pra_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'perpetual_register';
    ?>
    <div class="wrap">
        <h1>Update Perpetual Register</h1>
        
        <?php
        // Handle form submission
        if (isset($_POST['submit_csv']) && isset($_FILES['csv_file'])) {
            pra_handle_csv_upload();
        }
        ?>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Upload New CSV File</h2>
            
            <form method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file">Choose CSV File</label></th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                            <p class="description">File must be CSV format with columns: Id, entry, lifeStats, sort</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th>Update Method</th>
                        <td>
                            <p>
                                <label>
                                    <input type="radio" name="update_method" value="replace" checked>
                                    <strong>Replace All Data</strong><br>
                                    <small>This will DELETE all existing entries and import the new CSV</small>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="radio" name="update_method" value="append">
                                    <strong>Append New Data</strong><br>
                                    <small>This will ADD the CSV data to existing entries</small>
                                </label>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_csv" class="button button-primary" value="Upload and Process">
                </p>
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Current Register Status</h2>
            <?php
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists) {
                // Get total count
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo "<p><strong>Total records:</strong> " . ($count ? $count : 0) . "</p>";
                
                if ($count > 0) {
                    // Show latest entries
                    $latest = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");
                    if (!empty($latest)) {
                        echo "<p><strong>Latest entries:</strong></p>";
                        echo "<ul>";
                        foreach ($latest as $row) {
                            echo "<li>" . esc_html($row->entry) . " " . esc_html($row->lifeStats) . "</li>";
                        }
                        echo "</ul>";
                    }
                } else {
                    echo "<p>No records found. Upload a CSV file to add entries.</p>";
                }
            } else {
                echo "<p>Table doesn't exist yet. Upload a CSV to create it.</p>";
            }
            ?>
        </div>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Instructions</h2>
            <p><strong>Replace:</strong> Use this when you want to completely replace the register with new data. All old entries will be deleted.</p>
            <p><strong>Append:</strong> Use this when you have new names to add. Existing entries stay, new ones are added.</p>
            <p><strong>CSV Format:</strong> Your file must have these columns: Id, entry, lifeStats, sort</p>
        </div>
    </div>
    <?php
}

// Handle CSV upload
function pra_handle_csv_upload() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'perpetual_register';
    
    $file = $_FILES['csv_file'];
    $update_method = $_POST['update_method'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>Error uploading file. Please try again.</p></div>';
        return;
    }
    
    // Check file extension
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        echo '<div class="notice notice-error"><p>Please upload a CSV file.</p></div>';
        return;
    }
    
    // Read CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Could not read file.</p></div>';
        return;
    }
    
    $headers = fgetcsv($handle);
    
    // Validate headers - EXPECT 4 COLUMNS
    $expected_headers = ['Id', 'entry', 'lifeStats', 'sort'];
    
    if ($headers !== $expected_headers) {
        echo '<div class="notice notice-error"><p>Invalid CSV format. Expected columns: Id, entry, lifeStats, sort</p></div>';
        echo '<p>Your headers: ' . implode(', ', $headers) . '</p>';
        fclose($handle);
        return;
    }
    
    // Read data
    $data = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 4) {
            $data[] = [
                'id' => (int)$row[0],
                'entry' => $row[1],
                'lifeStats' => $row[2] ?? '',
                'sort' => $row[3] ?? ''
            ];
        }
    }
    fclose($handle);
    
    if (empty($data)) {
        echo '<div class="notice notice-error"><p>No data found in CSV file.</p></div>';
        return;
    }
    
    // Create table if it doesn't exist
    pra_create_table();
    
    $total_rows = count($data);
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // If replace, clear table
        if ($update_method === 'replace') {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
        
        // Insert data
        foreach ($data as $row) {
            // Check if ID already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE id = %d",
                $row['id']
            ));
            
            if ($exists) {
                // Record exists
                if ($update_method === 'append') {
                    // Skip duplicates when appending
                    $skipped++;
                    continue;
                } else {
                    // Update existing (for replace mode)
                    $result = $wpdb->update(
                        $table_name,
                        [
                            'entry' => $row['entry'],
                            'lifeStats' => $row['lifeStats'],
                            'sort' => $row['sort']
                        ],
                        ['id' => $row['id']]
                    );
                    
                    if ($result !== false) {
                        $updated++;
                    }
                }
            } else {
                // New record - includes sort column
                $result = $wpdb->insert(
                    $table_name,
                    [
                        'id' => $row['id'],
                        'entry' => $row['entry'],
                        'lifeStats' => $row['lifeStats'],
                        'sort' => $row['sort']
                    ]
                );
                
                if ($result) {
                    $inserted++;
                } else {
                    error_log('Insert failed: ' . $wpdb->last_error);
                }
            }
        }
        
        $wpdb->query('COMMIT');
        
        // Verify the count after insert
        $verify_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Show success message
        echo '<div class="notice notice-success"><p>';
        echo "✅ CSV processed successfully!<br>";
        echo "Total rows in file: $total_rows<br>";
        echo "New records inserted: $inserted<br>";
        if ($updated > 0) echo "Records updated: $updated<br>";
        if ($skipped > 0) echo "Skipped (duplicates): $skipped<br>";
        echo "Total records in database now: " . $verify_count . "<br>";
        echo '</p></div>';
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        echo '<div class="notice notice-error"><p>Error: ' . $e->getMessage() . '</p></div>';
    }
}

//shortcode for the frontend display

add_shortcode('perpetual_register_tabs', 'display_perpetual_register_tabs');
function display_perpetual_register_tabs($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'perpetual_register';
    
    // Get all data - using sort column for ordering if available
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY sort, entry");
    
    // If no data, show message
    if (empty($results)) {
        return '<p>No entries found in the Perpetual Register. Please upload a CSV file.</p>';
    }
    
    // Group names by first letter for tabs
    $tabs = [];
    foreach ($results as $row) {
        $first_letter = strtoupper(substr($row->entry, 0, 1));
        if (!isset($tabs[$first_letter])) {
            $tabs[$first_letter] = [];
        }
        $tabs[$first_letter][] = $row;
    }
    
    // For each tab, group by first 2 letters
    foreach ($tabs as $letter => &$entries) {
        $grouped = [];
        foreach ($entries as $entry) {
            $words = explode(' ', $entry->entry);
            $first_word = $words[0];
            $group_key = strtoupper(substr($first_word, 0, 2));
            
            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = [];
            }
            $grouped[$group_key][] = $entry;
        }
        $entries = $grouped;
    }
    
    ob_start();
    ?>
    
    <div class="perpetual-register-divi-style">
        <!-- A-Z Tabs -->
        <div class="et_pb_tabs et_pb_module">
            <ul class="et_pb_tabs_controls clearfix">
                <?php foreach (range('A', 'Z') as $letter): 
                    $active_class = ($letter == 'A') ? 'et_pb_tab_active' : '';
                ?>
                    <li class="<?php echo $active_class; ?>">
                        <a href="#"><?php echo $letter; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="et_pb_all_tabs">
                <?php foreach (range('A', 'Z') as $letter_index => $letter): 
                    $active_style = ($letter == 'A') ? 'display: block;' : 'display: none;';
                ?>
                    <div class="et_pb_tab" style="<?php echo $active_style; ?>">
                        <div class="et_pb_tab_content">
                            <?php
                            $letter_entries = isset($tabs[$letter]) ? $tabs[$letter] : [];
                            
                            if (empty($letter_entries)) {
                                echo '<p>No entries for letter ' . $letter . '</p>';
                            } else {
                                echo '<table><tbody><tr><td valign="top">';
                                
                                foreach ($letter_entries as $group_key => $group_entries) {
                                    echo '<span style="color: #e14426; font-weight: bold; display: block; margin-top: 15px;">' . $group_key . '</span>';
                                    foreach ($group_entries as $entry) {
                                        echo esc_html($entry->entry);
                                        if (!empty($entry->lifeStats)) {
                                            echo ' ' . esc_html($entry->lifeStats);
                                        }
                                        echo '<br>';
                                    }
                                }
                                
                                echo '</td></tr></tbody></table>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <style>
    .perpetual-register-divi-style .et_pb_tabs_controls {
        list-style: none !important;
        padding: 0 !important;
        display: flex;
        flex-wrap: wrap;
        background: #f9f9f9;
        border: 1px solid #d9d9d9;
        border-bottom: none;
        margin: 0;
    }
    
    .perpetual-register-divi-style .et_pb_tabs_controls li {
        margin: 0;
        padding: 0;
        border-right: 1px solid #d9d9d9;
        background: #f9f9f9;
    }
    
    .perpetual-register-divi-style .et_pb_tabs_controls li a {
        display: block;
        padding: 10px 20px;
        color: #333;
        text-decoration: none;
        font-weight: 600;
    }
    
    .perpetual-register-divi-style .et_pb_tabs_controls li.et_pb_tab_active {
        background: #fff;
    }
    
    .perpetual-register-divi-style .et_pb_all_tabs {
        background: #fff;
        border: 1px solid #d9d9d9;
        padding: 20px;
    }
    
    .perpetual-register-divi-style table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .perpetual-register-divi-style table td {
        vertical-align: top;
        padding: 0 15px;
    }
    
    .perpetual-register-divi-style table td span {
        color: #e14426;
        font-weight: bold;
        display: block;
        margin-top: 15px;
    }
    
    .perpetual-register-divi-style table td span:first-of-type {
        margin-top: 0;
    }
    
    .perpetual-register-divi-style table td br {
        line-height: 1.6;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .perpetual-register-divi-style .et_pb_tabs_controls li a {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .perpetual-register-divi-style table td {
            display: block;
            width: 100% !important;
            padding: 0;
        }
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('.perpetual-register-divi-style .et_pb_tabs_controls li').click(function(e) {
            e.preventDefault();
            
            var index = $(this).index();
            
            $('.perpetual-register-divi-style .et_pb_tabs_controls li').removeClass('et_pb_tab_active');
            $(this).addClass('et_pb_tab_active');
            
            $('.perpetual-register-divi-style .et_pb_all_tabs .et_pb_tab').hide();
            $('.perpetual-register-divi-style .et_pb_all_tabs .et_pb_tab').eq(index).show();
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}