<?php
/*
Plugin Name: ETimeClockWP Logged In Users
Description: Displays users who are logged in but haven't logged out, the last ten people who have logged out, and the last 30 people who logged in more than 24 hours ago but didn't log out.
Version: 1.1
Author: Robin and Chat GPT
*/

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu item
function etimeclockwp_loggedin_users_menu() {
    add_menu_page(
        __('Logged In Users', 'etimeclockwp'),
        __('Logged In Users', 'etimeclockwp'),
        'manage_options',
        'etimeclockwp-loggedin-users',
        'etimeclockwp_display_loggedin_users_page',
        'dashicons-admin-users'
    );
}
add_action('admin_menu', 'etimeclockwp_loggedin_users_menu');

// Custom query to get logged in users
function etimeclockwp_get_loggedin_users() {
    global $wpdb;

    $current_time = current_time('timestamp');
    $fifteen_hours_ago = $current_time - (15 * 3600);

    $query = $wpdb->prepare("
        SELECT p.ID, p.post_title as user_id, pm1.meta_value as clock_in_time
        FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key LIKE 'etimeclockwp-out_%'
        WHERE pm1.meta_key LIKE 'etimeclockwp-in_%'
        AND pm2.post_id IS NULL
        AND p.post_type = 'etimeclockwp_clock'
        AND p.post_status = 'publish'
        AND pm1.meta_value >= %d
        ORDER BY pm1.meta_value DESC
        LIMIT 20
    ", $fifteen_hours_ago);

    $results = $wpdb->get_results($query);

    return $results;
}

// Custom query to get the last ten logged out users
function etimeclockwp_get_loggedout_users() {
    global $wpdb;

    $query = "
        SELECT p.ID, p.post_title as user_id, pm.meta_value as clock_out_time
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key LIKE 'etimeclockwp-out_%'
        AND p.post_type = 'etimeclockwp_clock'
        AND p.post_status = 'publish'
        ORDER BY pm.meta_value DESC
        LIMIT 10
    ";

    $results = $wpdb->get_results($query);

    return $results;
}

// Custom query to get users logged in more than 24 hours ago and not logged out
function etimeclockwp_get_old_loggedin_users() {
    global $wpdb;

    $current_time = current_time('timestamp');
    $twenty_four_hours_ago = $current_time - (24 * 3600);
    $start_date = strtotime('2024-06-01');

    $query = $wpdb->prepare("
        SELECT p.ID, p.post_title as user_id, pm1.meta_value as clock_in_time
        FROM {$wpdb->prefix}posts p
        JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key LIKE 'etimeclockwp-out_%'
        WHERE pm1.meta_key LIKE 'etimeclockwp-in_%'
        AND pm2.post_id IS NULL
        AND p.post_type = 'etimeclockwp_clock'
        AND p.post_status = 'publish'
        AND pm1.meta_value < %d
        AND pm1.meta_value >= %d
        ORDER BY pm1.meta_value DESC
        LIMIT 30
    ", $twenty_four_hours_ago, $start_date);

    $results = $wpdb->get_results($query);

    return $results;
}

// Function to get the user name by user ID
function etimeclockwp_get_username_by_user_id($user_id) {
    global $wpdb;
    $query = $wpdb->prepare("
        SELECT pm.meta_value
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'etimeclockwp_name'
        AND p.ID = %d
    ", $user_id);
    $result = $wpdb->get_var($query);
    return $result ? $result : $user_id;
}

// Display logged in users, last ten logged out users, and old logged in users
function etimeclockwp_display_loggedin_users_page() {
    $loggedin_users = etimeclockwp_get_loggedin_users();
    $old_loggedin_users = etimeclockwp_get_old_loggedin_users();
    $loggedout_users = etimeclockwp_get_loggedout_users();
    ?>
    <div class="wrap">
        <h1><?php _e('Logged In Users', 'etimeclockwp'); ?></h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th><?php _e('User Name', 'etimeclockwp'); ?></th>
                    <th><?php _e('Clock In Time', 'etimeclockwp'); ?></th>
                    <th><?php _e('Actions', 'etimeclockwp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($loggedin_users)) : ?>
                    <?php foreach ($loggedin_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html(etimeclockwp_get_username_by_user_id($user->user_id)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', intval($user->clock_in_time))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $user->ID . '&action=edit')); ?>">
                                    <?php _e('View/Edit', 'etimeclockwp'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3"><?php _e('No users currently logged in.', 'etimeclockwp'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


        <h1><?php _e('Users Not Logged Out', 'etimeclockwp'); ?></h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th><?php _e('User Name', 'etimeclockwp'); ?></th>
                    <th><?php _e('Clock In Time', 'etimeclockwp'); ?></th>
                    <th><?php _e('Actions', 'etimeclockwp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($old_loggedin_users)) : ?>
                    <?php foreach ($old_loggedin_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html(etimeclockwp_get_username_by_user_id($user->user_id)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', intval($user->clock_in_time))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $user->ID . '&action=edit')); ?>">
                                    <?php _e('View/Edit', 'etimeclockwp'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3"><?php _e('No users logged in more than 24 hours ago without logging out.', 'etimeclockwp'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

</div>
        <h1><?php _e('Last 10 Logged Out Users', 'etimeclockwp'); ?></h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th><?php _e('User Name', 'etimeclockwp'); ?></th>
                    <th><?php _e('Clock Out Time', 'etimeclockwp'); ?></th>
                    <th><?php _e('Actions', 'etimeclockwp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($loggedout_users)) : ?>
                    <?php foreach ($loggedout_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html(etimeclockwp_get_username_by_user_id($user->user_id)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', intval(explode('|', $user->clock_out_time)[0]))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $user->ID . '&action=edit')); ?>">
                                    <?php _e('View/Edit', 'etimeclockwp'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3"><?php _e('No users have logged out recently.', 'etimeclockwp'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
<?php
}

// Register the shortcode
function etimeclockwp_loggedin_users_shortcode() {
    ob_start();
    etimeclockwp_display_loggedin_users_page();
    return ob_get_clean();
}
add_shortcode('etimeclockwp_loggedin_users', 'etimeclockwp_loggedin_users_shortcode');
?>