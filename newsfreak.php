<?php

/*
Plugin Name: Newsfreak App Functions
Plugin URI: https://1.envato.market/newsfreak
Description: This is a custom WordPress plugin from MRB Lab to configure the Newsfreak App.
Version: 1.1.3
Author: MRB Lab
Author URI: https://mrb-lab.com
*/


// Include necessary WordPress files
require_once ABSPATH . 'wp-admin/includes/user.php';

// Define a unique prefix for your functions and global variables
define('NEWSFREAK_PLUGIN_PREFIX', 'newsfreak_');


// Register custom REST endpoints
add_action('rest_api_init', function() {
    register_rest_route('wp/v2', 'users/register', [
        'methods' => 'POST',
        'callback' => 'wc_rest_user_endpoint_handler',
    ]);
    register_rest_route('remove_user/v1', 'user/me', [
        'methods' => 'DELETE',
        'callback' => 'delete_my_user_account',
    ]);
    register_rest_route('newsfreak', '/configs', [
        'methods' => 'GET',
        'callback' => 'get_settings_data',
    ]);
    register_rest_route('wp/v2', '/social-login', [
        'methods' => 'POST',
        'callback' => 'wc_rest_social_endpoint_handler',
    ]);
});


//rest api post extended
function newsfreak_rest_prepare_post($data, $post, $request)
{
    $_data = $data->data;
    $_data['custom']["featured_image"] = get_the_post_thumbnail_url($post->ID, "original") ?? '';
    $_data['custom']["author"]["name"] = get_author_name($_data['author']);
    $_data['custom']["author"]["avatar"] = get_avatar_url($_data['author']);
    $_data['custom']["categories"] = get_the_category($_data["id"]);
	$_data['custom']['views'] = function_exists('wpp_get_views') ? wpp_get_views($post->ID) : 0; // Check if function exists
    $data->data = $_data;
    return $data;
}

add_filter('rest_prepare_post', 'newsfreak_rest_prepare_post', 10, 3);



// Enable comment without being loggedin
function filter_rest_allow_anonymous_comments()
{
    return true;
}

add_filter('rest_allow_anonymous_comments', 'filter_rest_allow_anonymous_comments');


/* Handle Register User request. */
function wc_rest_user_endpoint_handler($request = null)
{
    $response = array();
    $parameters = $request->get_json_params();
    $username = sanitize_text_field($parameters['username']);
    $email = sanitize_text_field($parameters['email']);
    $password = sanitize_text_field($parameters['password']);
    // $role = sanitize_text_field($parameters['role']);
    $error = new WP_Error();
    if (empty($username)) {
        $error->add(400, __("Username field 'username' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    if (empty($email)) {
        $error->add(401, __("Email field 'email' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    if (empty($password)) {
        $error->add(404, __("Password field 'password' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }

    $user_id = username_exists($username);
    if (!$user_id && email_exists($email) == false) {
        $user_id = wp_create_user($username, $password, $email);
        if (!is_wp_error($user_id)) {
            // Ger User Meta Data (Sensitive, Password included. DO NOT pass to front end.)
            $user = get_user_by('id', $user_id);
            // $user->set_role($role);
            $user->set_role('subscriber');
            // WooCommerce specific code
            if (class_exists('WooCommerce')) {
                $user->set_role('customer');
            }
            // Ger User Data (Non-Sensitive, Pass to front end.)
            $response['code'] = 200;
            $response['message'] = __("User '" . $username . "' Registration was Successful", "wp-rest-user");
        } else {
            return $user_id;
        }
    } else {
        $error->add(406, __("Email/Username already exists, please try 'Reset Password'", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    return new WP_REST_Response($response, 123);
}

//getting user info by id
add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {

    if (WP_REST_Server::READABLE !== $request->get_method()) {
        return $response;
    }

    if (!preg_match('~/wp/v2/users/\d+~', $request->get_route())) {
        return $response;
    }

    add_filter('get_usernumposts', function ($count) {
        return $count > 0 ? $count : 1;
    });

    return $response;
}, 10, 3);

//delete user
function delete_my_user_account($request)
{
    if (is_user_logged_in()) {
        // Can't delete admin accounts
        if (current_user_can('manage_options')) {
            wp_send_json(
                array(
                    'status' => 'fail',
                    'title' => __('Error!', 'wp-delete-user-accounts'),
                    'message' => __('Administrators cannot delete their own accounts.', 'wp-delete-user-accounts')
                )
            );
        }

        // Get the current user data
        $user_id = get_current_user_id();

        // Get user meta data
        $meta = get_user_meta($user_id);

        // Delete user's meta data
        foreach ($meta as $key => $val) {
            delete_user_meta($user_id, $key);
        }


        // User Logout
        wp_logout();

        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }

        // Delete the user's account
        $deleted = wp_delete_user($user_id);


        if ($deleted) {

            // Success
            return array(
                'status' => 'success',
                'title' => __('Success!', 'wp-delete-user-accounts'),
                'message' => __('Your account was successfully deleted. Fair well.', 'wp-delete-user-accounts')
            );

        } else {

            return array(
                'status' => 'fail',
                'title' => __('Error!', 'wp-delete-user-accounts'),
                'message' => __('Request failed.', 'wp-delete-user-accounts')
            );
        }
    }
}


//newsfreak configs with pod
function get_settings_data()
{

    $homeCategories = get_option('newsfreak_configs_home_categories');
    $postDetailsLayout = get_option('newsfreak_configs_post_details_layout');
    $blockedCategories = get_option('newsfreak_configs_blocked_categories');
    $supportEmail = get_option('newsfreak_configs_support_email');
    $privacyPolicy = get_option('newsfreak_configs_privacy_policy');
    $postIntervalCount = get_option('newsfreak_configs_post_interval_count');


    $fb = get_option('newsfreak_configs_fb');
    $youtube = get_option('newsfreak_configs_youtube');
    $instagram = get_option('newsfreak_configs_instagram');
    $twitter = get_option('newsfreak_configs_twitter');
    $threads = get_option('newsfreak_configs_threads');

    $menubarEnabled = get_option('newsfreak_configs_menubar_enabled');
    $logoPositionCenter = get_option('newsfreak_configs_logo_position_center');
    $popularPostEnabled = get_option('newsfreak_configs_popular_post_enabled');
    $featuredPostEnabled = get_option('newsfreak_configs_featured_post_enabled');
    $welcomeScreenEnabled = get_option('newsfreak_configs_welcome_screen_enabled');
    $commentsEnabled = get_option('newsfreak_configs_comments_enabled');
    $loginEnabled = get_option('newsfreak_configs_login_enabled');
    $socialLoginsEnabled = get_option('newsfreak_configs_social_logins_enabled');
    $fbLoginEnabled = get_option('newsfreak_configs_fb_login_enabled');
    $multiLanguageEnabled = get_option('newsfreak_configs_multilanguage_enabled');
    $purchaseCode = get_option('newsfreak_configs_purchase_code');
    $purchaseValid = get_option('newsfreak_configs_purchase_valid');
    $onboardingEnabled = get_option('newsfreak_configs_onboarding_enabled');
    $socialEmbeddedEnabled = get_option('newsfreak_configs_social_embedded_enabled');
    $videoTabEnbaled = get_option('newsfreak_configs_video_tab_enbaled');
	$postViewsEnabled = get_option('newsfreak_configs_post_views_enabled');
	$dateTimeEnabled = get_option('newsfreak_configs_datetime_enabled');
	$featurePostsAutoSlide = get_option('newsfreak_configs_feature_posts_autoslide');

    $customAdsEnabled = get_option('newsfreak_configs_custom_ads_enabled');
    $customAdDestinationUrl = get_option('newsfreak_configs_custom_ad_destination_url');
    $customAdPlacements = get_option('newsfreak_configs_custom_ad_placements');

    $admobEnabled = get_option('newsfreak_configs_admob_enabled');
    $bannerAdsEnabled = get_option('newsfreak_configs_banner_ads_enabled');
    $interstitialAdsEnabled = get_option('newsfreak_configs_interstitial_ads_enabled');
    $clickAmount = get_option('newsfreak_configs_click_amount');
    $nativeAdsEnabled = get_option('newsfreak_configs_native_ads_enabled');
    $nativeAdPlacements = get_option('newsfreak_configs_native_ad_placements');


    $customAdAsset = get_option('newsfreak_configs_custom_ad_asset');
    $assetUrl = pods_image_url($customAdAsset, 'null');





    $isValid = false;

    if ($purchaseValid == 'true') {
        $isValid = true;
    } else {
        $isValid = verify_purchase($purchaseCode);
        if ($isValid == true) {
            update_field_value();
        }
    }

    $settings = null;
    if ($isValid == true) {
        $settings = array(
            'home_categories' => $homeCategories,
            'post_details_layout' => $postDetailsLayout,
            'blocked_categories' => $blockedCategories,
            'post_interval_count' => $postIntervalCount,
            'support_email' => $supportEmail,
            'privacy_policy_url' => $privacyPolicy,
            'fb_url' => $fb,
            'youtube_url' => $youtube,
            'instagram_url' => $instagram,
            'twitter_url' => $twitter,
            'threads_url' => $threads,
            'menubar_enabled' => $menubarEnabled,
            'logo_position_center' => $logoPositionCenter,
            'popular_post_enabled' => $popularPostEnabled,
            'featured_post_enabled' => $featuredPostEnabled,
            'welcome_screen_enabled' => $welcomeScreenEnabled,
            'comments_enabled' => $commentsEnabled,
            'login_enabled' => $loginEnabled,
            'social_logins_enabled' => $socialLoginsEnabled,
            'fb_login_enabled' => $fbLoginEnabled,
            'multilanguage_enabled' => $multiLanguageEnabled,
            'valid' => $isValid,
            'social_embedded_enabled' => $socialEmbeddedEnabled,
            'onboarding_enabled' => $onboardingEnabled,
            'video_tab_enabled' => $videoTabEnbaled,
			'post_views_enabled' => $postViewsEnabled,
			'datetime_enabled' => $dateTimeEnabled,
			'feature_posts_autoslide' => $featurePostsAutoSlide,
            'custom_ads_enabled' => $customAdsEnabled,
            'custom_ad_asset' => $assetUrl,
            'custom_ad_destination_url' => $customAdDestinationUrl,
            'custom_ad_placements' => $customAdPlacements,
            'admob_enabled' => $admobEnabled,
            'banner_ads_enabled' => $bannerAdsEnabled,
            'interstitial_ads_enabled' => $interstitialAdsEnabled,
            'click_amount' => $clickAmount,
            'native_ads_enabled' => $nativeAdsEnabled,
            'native_ad_placements' => $nativeAdPlacements,
        );
    }


    return $settings;
}


//update pod field data
function update_field_value()
{
    $pod_name = 'newsfreak_configs';
    $field_name = 'purchase_code';
    $new_value = 'true';


    //Get the pod object using the pod name.
    $pod = pods($pod_name);

    $data = array('purchase_valid' => $new_value, );
    $pod->save($data);
}

//verify purchase
function verify_purchase($code)
{

    $data = null;
    $isValid = false;
    $newsfreakId = 32743254;
    $url = 'https://mrb-lab.com/wp-json/envato/v1/verify-purchase/' . $code;
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        $data = null;
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            $data = json_decode($response_body);
        } else {
            $data = null;
        }
    }
    if ($data != null) {
        $x = $data->validated;
        $y = $data->purchase;
        if (empty($x) || empty($y)) {
            $isValid = false;
        } else {
            $itemId = $data->purchase->item->id;
            if ($newsfreakId == $itemId) {
                $isValid = true;
            } else {
                $isValid = false;
            }
        }
    } else {
        $isValid = false;
    }

    return $isValid;
}

// featured post query
function featured_post_query($args, $request)
{
    // Check if the request is for the default posts endpoint and a specific custom parameter exists
    if ($request->get_route() === '/wp/v2/posts' && $request->get_param('featured')) {
        $args['meta_query'] = array(
            array(
                'key' => 'featured',
                'value' => '1',
                'compare' => '=',
            ),
        );
    }

    return $args;
}
add_filter('rest_post_query', 'featured_post_query', 10, 2);



// video post query
function video_post_query($args, $request)
{
    // Check if the request is for the default posts endpoint and a specific custom parameter exists
    if ($request->get_route() === '/wp/v2/posts' && $request->get_param('video')) {
        $args['meta_query'] = array(
            array(
                'key' => 'video_post',
                'value' => '1',
                'compare' => '=',
            ),
        );
    }

    return $args;
}
add_filter('rest_post_query', 'video_post_query', 10, 2);



// social logins
function wc_rest_social_endpoint_handler($request = null)
{
    $response = array();
    $username = $request->get_param('username');
    $email = $request->get_param('email');

    $error = new WP_Error();
    if (empty($username)) {
        $error->add(400, __("Username field 'username' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    if (empty($email)) {
        $error->add(401, __("Email field 'email' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }

    $user_id = username_exists($username);
    if (!$user_id && email_exists($email) == false) {
        $random_password = wp_generate_password();
        $user_id = wp_create_user($username, $random_password, $email);
        if (!is_wp_error($user_id)) {
            $user = get_user_by('id', $user_id);
            $user->set_role('subscriber');

            // WooCommerce specific code
            if (class_exists('WooCommerce')) {
                $user->set_role('customer');
            }

            $response = array(
                'code' => 200,
                'message' => 'New User',
                'email' => $user->data->user_email,
                'username' => $user->data->user_login,
            );
        } else {
            $error->add(406, __("Error on creating new account'", 'wp-rest-user'), array('status' => 400));
            return $error;
        }
    } else {
        $user = get_user_by('id', $user_id);
        if ($user != false) {
            $response = array(
                'code' => 200,
                'message' => 'User Exists',
                'email' => $user->data->user_email,
                'username' => $user->data->user_login,
            );
        } else {
            $error->add(406, __("Error on login account'", 'wp-rest-user'), array('status' => 400));
            return $error;
        }
    }
    return new WP_REST_Response($response, 123);
}


//  Onesignal push with thumbnail and to remove url (v3x - 1.1.3)
add_filter('onesignal_send_notification', 'custom_onesignal_send_notification', 10, 4);

function custom_onesignal_send_notification($fields, $new_status, $old_status, $post) {
    if (!isset($post->ID)) {
        return $fields;
    }

    // Get post thumbnail URL
    $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'full');

    // Add post ID and thumbnail
    $fields['data']['post_id'] = $post->ID;
    $fields['data']['thumbnail'] = $thumbnail_url;

    // Modify web URL
    if (isset($fields['url'])) {
        $fields['web_url'] = $fields['url'];
        unset($fields['url']);
    }

    return $fields;
}

// REST API: Add support for Event post type
add_filter('rest_prepare_event', 'newsfreak_rest_prepare_event', 10, 3);

function newsfreak_rest_prepare_event($data, $post, $request)
{
    $_data = $data->data;
    // Add featured image
    $_data['custom']['featured_image'] = get_the_post_thumbnail_url($post->ID, 'original') ?? '';
    // Add author info
    $_data['custom']['author']['name'] = get_author_name($_data['author']);
    $_data['custom']['author']['avatar'] = get_avatar_url($_data['author']);
    // Add event meta fields
    $_data['custom']['start_date'] = get_post_meta($post->ID, 'start_date', true);
    $_data['custom']['end_date'] = get_post_meta($post->ID, 'end_date', true);
    $_data['custom']['location'] = get_post_meta($post->ID, 'location', true);
    $_data['custom']['description'] = get_post_meta($post->ID, 'description', true);
    $data->data = $_data;
    return $data;
}
