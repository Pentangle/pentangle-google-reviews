<?php

/**
 * Plugin Name: Google Reviews Fetcher
 * Description: A plugin to fetch and display Google reviews using Google Places API, with settings in the WordPress dashboard and caching for better performance.
 * Version: 1.4.4
 * Author: Pentangle Technology Limited
 * Update URI: https://github.com/Pentangle/pentangle-google-reviews/
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

    // Check if settings have been saved
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="updated notice is-dismissible"><p>Settings saved successfully!</p></div>';
    }

    // Handle cache clear request
    if (isset($_POST['grf_clear_cache'])) {
        grf_clear_cache();
        echo '<div class="updated"><p>Google Reviews cache cleared successfully!</p></div>';
    }

    if ( ! get_option('grf_github_token') || empty(get_option('grf_github_token')) ) {
        wp_admin_notice(
            '<strong>Google Reviews</strong>: Please set the GitHub Access Token in the plugin settings to enable auto-updates',
            ['type' => 'warning']
        );
    }

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

        <?php grf_options_section_callback(); ?>

        <!-- Add cache clearing button -->
        <h2>Clear Cache</h2>
        <form method="post">
            <input type="hidden" name="grf_clear_cache" value="1">
            <input type="submit" class="button-primary" value="Clear Google Reviews Cache">
        </form>
    </div>
<?php
}

// Register settings, sections, and fields
function grf_settings_init()
{
    register_setting('grf_settings_group', 'grf_api_key');
    register_setting('grf_settings_group', 'grf_place_id');
    register_setting('grf_settings_group', 'grf_github_token', function($value) {
        return grf_encrypt_data($value);
    });

    add_settings_section(
        'grf_settings_section',
        'Google Places API Settings',
        'grf_settings_section_callback',
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

    add_settings_field(
        'grf_github_token',
        'GitHub Token <small>(For Auto-Updates)</small>',
        'grf_github_token_render',
        'grf-settings',
        'grf_settings_section'
    );
}

add_action('admin_init', 'grf_settings_init');

function grf_settings_section_callback()
{
    echo 'To get your Google Places API Key, visit the <a href="https://developers.google.com/places/web-service/get-api-key" target="_blank">Google Developers Console</a>.<br><br>';
    echo 'As this plugin uses a background request to get the data you must set your restrictions based on the IP address of your server rather than the domain.<br><br>';
    echo 'To find your Place ID, search for your business on <a href="https://developers.google.com/places/place-id" target="_blank">Google Places ID Finder</a>.<br><br>';
    echo 'Enter your Google Places API Key and Place ID below:';
}

function grf_options_section_callback()
{
    echo '<h2>Displaying Reviews</h2>';
    echo 'To display the reviews, use the following code in the file:<br>';
    echo '<pre>[google_reviews number="5" min_rating="3"]</pre><br>';
    echo 'If you would like to override the default output, create a file called <code>pentangle-google-reviews.php</code> in your theme folder.<br><br>';
    echo 'The data is cached for 5 minutes to reduce the number of requests to the Google Places API.<br><br>';
    echo 'The data is available to your template file in the variable <code>$grf_reviews</code>.<br><br>';
    echo 'The average rating and total number of reviews are available in the variable <code>$grf_review_data</code>.<br><br>';
    echo 'The Google logo is available in the plugin folder as <code>google_g_icon_download.png</code> using <code>plugin_dir_url(__FILE__).\'google_g_icon_download.png\'</code><br><br>';
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

function grf_encrypt_data($data) {
    if (empty($data)) return '';
    $key = substr(AUTH_KEY, 0, 32);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function grf_decrypt_data($data) {
    if (empty($data)) return '';
    $key = substr(AUTH_KEY, 0, 32);
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
}

function grf_github_token_render()
{
    $github_token = get_option('grf_github_token');
?>
    <input type="password" name="grf_github_token" value="<?php echo esc_attr($github_token); ?>" style="width: 400px;" />
    <?php
}

// Clear transient cache function
function grf_clear_cache()
{
    global $wpdb;
    $sql = "DELETE
            FROM  $wpdb->options
            WHERE `option_name` LIKE '%grf_google_reviews_data_%'
            ORDER BY `option_name`";

    $results = $wpdb->query($sql);
}


// Shortcode to display Google Reviews
function grf_display_google_reviews($atts)
{
    // Get API Key and Place ID from the options saved in the dashboard
    $api_key = get_option('grf_api_key');
    $place_id = get_option('grf_place_id');

    if (isset($atts['place_id'])) {
        $place_id = $atts['place_id'];
    }
    //check if there is a file called pentangle-google-reviews.php in the theme folder

    $template = 'pentangle-google-reviews';

    if (isset($atts['template'])) {
        $template = $atts['template'];
    }


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
        $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid={$place_id}&key={$api_key}&reviews_sort=newest";
        $response = wp_remote_get($url);

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

                //write the response to the wp error log
                error_log($url);
                error_log(print_r($data, 1));
                return '<p>No reviews found for this location.</p>';
            }

            // Cache the JSON response for 5 minutes (300 seconds)
            set_transient($cache_key, $body, 5 * MINUTE_IN_SECONDS);
        } else {
            return '<p>Error: Could not retrieve valid reviews data from the API.</p>';
        }
    }

    foreach ($data['result']['reviews'] as $key => $review) {
        $data['result']['reviews'][$key]['stars'] = grf_generate_stars($review['rating']);
    }

    // Limit the number of reviews to display after filtering
    $grf_reviews = array_slice($data['result']['reviews'], 0, $atts['number']);
    $grf_review_data = ['rating' => $data['result']['rating'], 'user_ratings_total' => $data['result']['user_ratings_total']];

    // Start outputting the reviews in HTML
    ob_start();

    if (file_exists(get_template_directory() . '/' . $template . '.php')) {
        include get_template_directory() . '/' . $template . '.php';
    } else {
        pentangle_google_review_css();
        echo '<div class="google-reviews">';
        foreach ($grf_reviews as $review) {
    ?>
            <div class="google-review">
                <img src="<?= esc_url($review['profile_photo_url']); ?>"
                    alt="<?= $review['author_name']; ?> Reviewer Image"
                    style="width: 50px; height: 50px; border-radius: 50%;">
                <p><strong><?= esc_html($review['author_name']); ?></strong></p>
                <p>Rating: <?= $review['stars']; ?></p>
                <p><?= esc_html($review['text']); ?></p>
                <p><em><?= esc_html($review['relative_time_description']) ?></em></p>
            </div>
            <hr />
<?php
        }

        //create a link to the google_g_icon_download.png in the plugin folder
        echo '<div class="overall-rating">';
        echo '<img src="' . plugin_dir_url(__FILE__) . 'google_g_icon_download.png" alt="Google Reviews" style="width: 100px; height: 100px;">';
        echo '<p>Average Rating: ' . $grf_review_data['rating'] . ' out of 5 based on ' . $grf_review_data['user_ratings_total'] . ' reviews</p>';
        echo '<p><a href="https://www.google.com/search?q=' . urlencode($data['result']['name']) . '&ludocid=' . $place_id . '&hl=en" target="_blank">Read more reviews on Google</a></p>';
        echo '</div>';
        echo '</div>';
    }


    return ob_get_clean();
}

function pentangle_google_review_css()
{
    $plugin_url = plugin_dir_url(__FILE__);
    wp_enqueue_style('gr_styles', $plugin_url . "/css/plugin-style.css");
}

function grf_generate_stars($rating)
{

    //load the star.svg file and repeat it 5 times changing the colour from yellow to gray

    $stars = '';
    for ($i = 0; $i <= 4; $i++) {

        //check if the rating is less than the current star and change the file to star-empty or star-half for any over 0.5

        if ($rating - $i >= 1) {
            $file = 'star-full';
        } elseif ($rating - $i > 0.5) {
            $file = 'star-half';
        } else {
            $file = 'star-empty';
        }

        //$file = ($i <= $rating) ? 'star-full' : 'star-empty';
        $stars .= '<img src="' . plugin_dir_url(__FILE__) . $file . '.svg" class="review-star">';
    }
    return $stars;
}

add_action('init', 'pentangle_activate_wp');
function pentangle_activate_wp()
{
    require_once('wp_autoupdate.php');
}

// Register the shortcode [google_reviews number=""]
add_shortcode('google_reviews', 'grf_display_google_reviews');
