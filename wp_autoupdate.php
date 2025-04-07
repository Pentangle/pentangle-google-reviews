<?php

include "Parsedown.php";

/**
 * Auto updater file for the Pentangle Google Reviews plugin.
 *
 * This file handles checking for updates on GitHub and providing update details
 * when requested by WordPress.
 */

add_filter('pre_set_site_transient_update_plugins', 'self_update');

/**
 * Check for updates to this plugin.
 *
 * @param object $transient Transient object for update plugins.
 *
 * @return object Transient object for update plugins.
 */
function self_update($transient)
{

    if (! is_object($transient)) {
        $transient = new stdClass();
    }

    if (empty($transient->response) || ! is_array($transient->response)) {
        $transient->response = [];
    }

    $plugin_file = 'pentangle-google-reviews/pentangle-google-reviews.php';

    if (! function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);

    $response = wp_remote_get(
        'https://api.github.com/repos/Pentangle/pentangle-google-reviews/releases/latest',
        [
            'headers' => [
                'Authorization' => 'token ' . GITHUB_ACCESS_TOKEN,
                'User-Agent' => 'WordPress',
                'Accept' => 'application/vnd.github.v3+json',
            ]
        ]
    );

    if (is_wp_error($response)) {
        return $transient;
    }

    $output = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($output['status']) && $output['status'] === '404') {
        return $transient; // No release found
    }

    $new_version_number = str_replace('v', '', $output['tag_name']);
    $is_update_available = version_compare($plugin_data['Version'], $new_version_number, '<');

    if (! $is_update_available) {
        return $transient; // No update available
    }

    $update_array = [
        'id' => $plugin_file,
        'slug' => $plugin_data['TextDomain'],
        'plugin' => $plugin_file,
        'new_version' => $new_version_number,
        'package' => $output['assets'][0]['browser_download_url'],
        'author' => $plugin_data['Author'],
    ];

    $transient->response[$plugin_file] = (object) $update_array;

    return $transient;
}

add_filter('plugins_api', 'self_plugin_details', 10, 3);

/**
 * Provide plugin information for the update details modal.
 *
 * @param mixed  $def    The default response.
 * @param string $action The type of information being requested.
 * @param object $args   Arguments containing the plugin slug.
 *
 * @return object The plugin details or the default response.
 */
function self_plugin_details($def, $action, $args)
{
    if ($action !== 'plugin_information') {
        return $def;
    }

    $plugin_file = 'pentangle-google-reviews/pentangle-google-reviews.php';

    if (! function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);

    // Make sure the request matches our plugin
    if ($args->slug !== $plugin_data['TextDomain']) {
        return $def;
    }

    $response = wp_remote_get(
        'https://api.github.com/repos/Pentangle/pentangle-google-reviews/releases/latest',
        [
            'headers' => [
                'Authorization' => 'token ' . GITHUB_ACCESS_TOKEN,
                'User-Agent' => 'WordPress',
                'Accept' => 'application/vnd.github.v3+json',
            ]
        ]
    );

    if (is_wp_error($response)) {
        return $def;
    }

    $parsedown = new Parsedown();
    $release = json_decode(wp_remote_retrieve_body($response), true);

    $plugin_info = new stdClass();
    $plugin_info->name = $plugin_data['Name'];
    $plugin_info->slug = $plugin_data['TextDomain'];
    $plugin_info->version = str_replace('v', '', $release['tag_name']);
    $plugin_info->author = $plugin_data['Author'];
    $plugin_info->tested = '6.7.2';
    $plugin_info->requires_php = '7.1';
    $plugin_info->last_updated = $release['created_at'];
    $plugin_info->sections = [
        'description' => $plugin_data['Description'],
        'changelog' => isset($release['body']) ? $parsedown->text($release['body']) : 'No changelog available.',
    ];
    $plugin_info->download_link = $release['assets'][0]['browser_download_url'];

    return $plugin_info;
}

function plugin_modal_changelog_styles()
{
    if (is_admin()) : ?>
        <style>
            #section-changelog { display: inline-block !important; }
        </style>
<?php endif;
}
add_action('admin_head', 'plugin_modal_changelog_styles');
