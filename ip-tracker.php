<?php
/*
Plugin Name: IP Tracker for Ad Clicks
Description: A plugin to grab and log the IP addresses of visitors who come from ads, along with gclid, page, and time of visit.
Version: 1.1
Author: Iniubong Peter for Oziel Law
*/

// Hook to create a custom database table when the plugin is activated
register_activation_hook(__FILE__, 'create_oziel_law_ip_tracker_table');

function create_oziel_law_ip_tracker_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'oziel_law_ip_tracker';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL,
        gclid varchar(255),
        page_url text NOT NULL,
        visit_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook to trigger on every page load
add_action('wp', 'track_ip_address');

// Function to track and store visitor details
function track_ip_address()
{
    global $wpdb;

    // Get visitor's IP address
    $ip_address = get_ip_address();

    $gclid = isset($_GET['gclid']) ? sanitize_text_field($_GET['gclid']) : null;

    if ($gclid  === null) return;

    $page_url = esc_url(home_url($_SERVER['REQUEST_URI']));

    $visit_time = current_time('mysql');

    $table_name = $wpdb->prefix . 'oziel_law_ip_tracker';
    $wpdb->insert(
        $table_name,
        array(
            'ip_address' => $ip_address,
            'gclid' => $gclid,
            'page_url' => $page_url,
            'visit_time' => $visit_time,
        )
    );
}

// Function to safely retrieve the visitor's IP address
function get_ip_address()
{
    // var_dump($_SERVER);

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
        // var_dump($_SERVER['HTTP_CLIENT_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP passed from a proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        // var_dump($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        // Standard remote IP
        $ip = $_SERVER['REMOTE_ADDR'];
        // var_dump($_SERVER['REMOTE_ADDR']);
    }

    // In case of multiple IP addresses in HTTP_X_FORWARDED_FOR (comma-separated), take the first one
    return explode(',', $ip)[0];
}

// Add an Admin Menu for the IP Tracker
add_action('admin_menu', 'ip_tracker_admin_menu');

function ip_tracker_admin_menu()
{
    add_menu_page(
        'IP Tracker',
        'IP Tracker',
        'manage_options',
        'ip-tracker',
        'ip_tracker_admin_page',
        'dashicons-visibility',
        6
    );
}

// Function to display data on the admin page
function ip_tracker_admin_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'oziel_law_ip_tracker';

    // Handle deletion action
    if (isset($_POST['delete_ips']) && !empty($_POST['selected_ips'])) {
        $ids_to_delete = array_map('intval', $_POST['selected_ips']);
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($placeholders)", ...$ids_to_delete));
        echo '<div class="updated notice is-dismissible"><p>Selected records deleted.</p></div>';
    }

    // Pagination variables
    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY visit_time DESC LIMIT %d OFFSET %d", $items_per_page, $offset));


    echo '<div class="wrap">';

    if ($results) {
        echo '<h1>IP Tracker Logs</h1>';
        echo '<form method="post">';

        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr>';
        echo '<th class="manage-column column-cb check-column"><input type="checkbox" id="select_all" /></th>';
        echo '<th class="manage-column">IP Address</th>';
        echo '<th class="manage-column">GClID</th>';
        echo '<th class="manage-column">Page URL</th>';
        echo '<th class="manage-column">Visit Time</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($results as $row) {
            echo '<tr>';
            echo '<th class="check-column"><input type="checkbox" name="selected_ips[]" value="' . esc_attr($row->id) . '" /></th>';
            echo '<td>' . esc_html($row->ip_address) . '</td>';
            echo '<td>' . esc_html($row->gclid) . '</td>';
            echo '<td>' . esc_html($row->page_url) . '</td>';
            echo '<td>' . esc_html($row->visit_time) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<div style="margin-top: 10px;"><input type="submit" name="delete_ips" class="button action" value="Delete Selected"></div>';
        echo '</form>';
    } else {
        echo '<p>No records found.</p>';
    }


    // Pagination controls
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages > 1) {
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
        ));
        if ($page_links) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
        }
    }

    echo '</div>';

    echo '<script type="text/javascript">
        document.getElementById("select_all").addEventListener("click", function() {
            var checkboxes = document.querySelectorAll(\'input[name="selected_ips[]"]\');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });
    </script>';
}
