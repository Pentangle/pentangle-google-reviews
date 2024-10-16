<?php
/**
 * Plugin Name: Google Reviews Fetcher
 * Description: A plugin to fetch and display Google reviews using Google Places API, with settings in the WordPress dashboard and caching for better performance.
 * Version: 1.3
 * Author: Pentangle Technology Limited
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register a menu item in the WordPress dashboard
function grf_add_admin_menu()
{
    add_menu_page(
        'Google Reviews Settings',       // Page Title
        'Google Reviews',                // Menu Title
        'manage_options',                // Capability
        'grf-settings',                  // Menu Slug
        'grf_settings_page',             // Callback function
        'dashicons-admin-site',          // Icon
        100                              // Position
    );
}
add_action('admin_menu', 'grf_add_admin_menu');

// Create the settings page
function grf_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Google Reviews Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('grf_settings_group');
            do_settings_sections('grf-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings, sections, and fields
function grf_settings_init()
{
    register_setting('grf_settings_group', 'grf_api_key');
    register_setting('grf_settings_group', 'grf_place_id');

    add_settings_section(
        'grf_settings_section',
        'Google Places API Settings',
        'grf_settings_section_callback',
        'grf-settings'
    );

    add_settings_section(
        'grf_options_section',
        'Google Reviews shortcode settings',
        'grf_options_section_callback',
        'grf-settings'
    );

    add_settings_field(
        'grf_api_key',
        'Google API Key',
        'grf_api_key_render',
        'grf-settings',
        'grf_settings_section'
    );

    add_settings_field(
        'grf_place_id',
        'Google Place ID',
        'grf_place_id_render',
        'grf-settings',
        'grf_settings_section'
    );
}
add_action('admin_init', 'grf_settings_init');

function grf_settings_section_callback()
{
    echo 'To get your Google Places API Key, visit the <a href="https://developers.google.com/places/web-service/get-api-key" target="_blank">Google Developers Console</a>.<br><br>';
    echo 'As this plugin uses a background request to get the dat you must set your restrictions based on the IP address of your server rather than the domain.<br><br>';
    echo 'To find your Place ID, search for your business on <a href="https://developers.google.com/places/place-id" target="_blank">Google Places ID Finder</a>.<br><br>';
    echo 'Enter your Google Places API Key and Place ID below:';
}
function grf_options_section_callback()
{
    echo 'To display the reviews, use the following code in the file:<br>';
    echo '<pre>[google_reviews number="5" min_rating="3"]</pre><br>';

    echo 'If you would like to override the default output, create a file called <code>pentangle-google-reviews.php</code> in your theme folder.<br><br>';
    echo 'The data is cached for 5 minutes to reduce the number of requests to the Google Places API.';
    echo 'The data is available to your template file in the variable <code>$grf_reviews</code>.';

}



function grf_api_key_render()
{
    $api_key = get_option('grf_api_key');
    ?>
    <input type="text" name="grf_api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 400px;" />
    <?php
}

function grf_place_id_render()
{
    $place_id = get_option('grf_place_id');
    ?>
    <input type="text" name="grf_place_id" value="<?php echo esc_attr($place_id); ?>" style="width: 400px;" />
    <?php
}

// Shortcode to display Google Reviews
function grf_display_google_reviews($atts)
{
    // Get API Key and Place ID from the options saved in the dashboard
    $api_key = get_option('grf_api_key');
    $place_id = get_option('grf_place_id');

    // If API Key or Place ID is missing, return an error message
    if (empty($api_key) || empty($place_id)) {
        return '<p>Error: API key or Place ID is not set in the settings page.</p>';
    }

    // Shortcode attributes to override settings if provided
    $atts = shortcode_atts(
        array(
            'number' => 5, // Number of reviews to display
            'min_rating' => 0    // Minimum rating to display reviews
        ),
        $atts
    );

    // Cache key to store/retrieve the reviews JSON
    $cache_key = 'grf_google_reviews_data_' . md5($place_id);

    // Check if cached JSON data exists and is not expired (5-minute expiration)
    $cached_data = get_transient($cache_key);

    // If cached data exists, decode it
    if ($cached_data) {
        $data = json_decode($cached_data, true);
    } else {
        // Call the Google Places API to fetch reviews
        $response = wp_remote_get("https://maps.googleapis.com/maps/api/place/details/json?placeid={$place_id}&key={$api_key}&fields=reviews");

        // Check for errors in the API response
        if (is_wp_error($response)) {
            return '<p>Error fetching reviews.</p>';
        }

        // Only proceed if we get a 200 OK response
        if (wp_remote_retrieve_response_code($response) === 200) {
            // Decode the JSON response
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Check if there are reviews in the response
            if (empty($data['result']['reviews'])) {
                return '<p>No reviews found for this location.</p>';
            }

            // Cache the JSON response for 5 minutes (300 seconds)
            set_transient($cache_key, $body, 5 * MINUTE_IN_SECONDS);
        } else {
            return '<p>Error: Could not retrieve valid reviews data from the API.</p>';
        }
    }

    // Filter reviews by minimum rating
    $filtered_reviews = array_filter($data['result']['reviews'], function($review) use ($atts) {
        return $review['rating'] >= $atts['min_rating'];
    });

    // Limit the number of reviews to display after filtering
    $grf_reviews = array_slice($filtered_reviews, 0, $atts['number']);

    // Start outputting the reviews in HTML
    ob_start();

    //check if there is a file called pentangle-google-reviews.php in the theme folder

    if(file_exists(get_template_directory().'/pentangle-google-reviews.php')){
        include get_template_directory().'/pentangle-google-reviews.php';
    }else{

        echo '<div class="google-reviews">';
        foreach ($grf_reviews as $review) {
            ?>
            <div class="google-review">
                <p><strong><?= esc_html($review['author_name']); ?></strong></p>
                <p>Rating: <?= esc_html($review['rating']); ?>/5</p>
                <p><?= esc_html($review['text']); ?></p>
                <p><em><?= esc_html($review['relative_time_description']) ?></em></p>
            </div>
            <hr />
            <?php
        }
        echo '</div>';


    }



    return ob_get_clean();
}

// Register the shortcode [google_reviews number=""]
add_shortcode('google_reviews', 'grf_display_google_reviews');
