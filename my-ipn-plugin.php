<?php
/*
Plugin Name: My IPN Plugin
Description: A plugin to handle PayPal IPN messages
Version: 1.0
Author: Pasha Loguinov
*/

// Run when the plugin is activated
function create_paypal_transactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_transactions';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        txn_id mediumtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

register_activation_hook(__FILE__, 'create_paypal_transactions_table');

register_activation_hook(__FILE__, function() {
    add_rewrite_rule('^ipn-listener$', 'index.php?ipn_listener=1', 'top');
    flush_rewrite_rules();
});

add_filter('query_vars', function($vars) {
    $vars[] = 'ipn_listener';
    return $vars;
});

add_action('parse_request', function($wp) {
    error_log('parse_request action triggered');
    if (array_key_exists('ipn_listener', $wp->query_vars)) {
        error_log('IPN listener triggered');
        do_action('handle_ipn');
    }
});

// Add an option to store the receiver email
function my_plugin_activate() {
    add_option('my_plugin_receiver_email', '');
}
register_activation_hook(__FILE__, 'my_plugin_activate');

// Add a menu item for the settings page
function my_plugin_menu() {
    add_options_page(
        'My IPN Plugin Settings',
        'My IPN Plugin',
        'manage_options',
        'my-ipn-plugin-settings',
        'my_ipn_plugin_settings_page'
    );
}
add_action('admin_menu', 'my_ipn_plugin_menu');

// Display the settings page
function my_ipn_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>My IPN Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my_ipn_plugin_settings_group');
            do_settings_sections('my-ipn-plugin-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register the settings
function my_ipn_plugin_settings_init() {
    register_setting('my_ipn_plugin_settings_group', 'my_ipn_plugin_receiver_email');

    add_settings_section(
        'my_ipn_plugin_settings_section',
        'Receiver Email Settings',
        null,
        'my-ipn-plugin-settings'
    );

    add_settings_field(
        'my_ipn_plugin_receiver_email',
        'Receiver Email',
        'my_ipn_plugin_receiver_email_render',
        'my-ipn-plugin-settings',
        'my_ipn_plugin_settings_section'
    );
}
add_action('admin_init', 'my_ipn_plugin_settings_init');

// Render the email input field
function my_ipn_plugin_receiver_email_render() {
    $receiver_email = get_option('my_ipn_plugin_receiver_email');
    ?>
    <input type="email" name="my_ipn_plugin_receiver_email" value="<?php echo esc_attr($receiver_email); ?>" />
    <?php
}

function my_ipn_plugin_deactivate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'paypal_transactions';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_deactivation_hook(__FILE__, 'my_ipn_plugin_deactivate');

// Include the IPN listener
require 'ipn-listener.php';