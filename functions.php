<?php
defined('ABSPATH') or die("you do not have acces to this page!");
if (!function_exists('cmplz_uses_google_analytics')) {

    function cmplz_uses_google_analytics()
    {
        return COMPLIANZ()->cookie->uses_google_analytics();
    }
}


/*
 * This overrides the enabled setting for use_categories, based on the tagmanager settings
 * When tagmanager is enabled, use of TM cats is obligatory
 *
 *
 * */

add_filter('cmplz_fields', 'cmplz_fields_filter', 10, 1);
if (!function_exists('cmplz_fields_filter')) {

    function cmplz_fields_filter($fields)
    {

        $tm_fires_scripts = cmplz_get_value('fire_scripts_in_tagmanager') === 'yes' ? true : false;
        $uses_tagmanager = cmplz_get_value('compile_statistics') === 'google-tag-manager' ? true : false;
        if ($uses_tagmanager && $tm_fires_scripts) $fields['use_categories']['hidden'] = true;

        return $fields;
    }
}

if (!function_exists('cmplz_get_template')) {

    function cmplz_get_template($file)
    {

        $file = trailingslashit(cmplz_path) . 'templates/' . $file;
        $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(cmplz_path) . $file;

        if (file_exists($theme_file)) {
            $file = $theme_file;
        }

        if (strpos($file, '.php') !== FALSE) {
            ob_start();
            require $file;
            $contents = ob_get_clean();
        } else {
            $contents = file_get_contents($file);
        }

        return $contents;
    }
}

if (!function_exists('cmplz_tagmanager_conditional_helptext')) {

    function cmplz_tagmanager_conditional_helptext()
    {
        if (cmplz_no_ip_addresses() && cmplz_statistics_no_sharing_allowed() && cmplz_accepted_processing_agreement()) {
            $text = __("Based on your Analytics configuration you should fire Analytics as a functional cookie on event cmplz_event_functional.", "complianz");
        } else {
            $text = __("Based on your Analytics configuration you should fire Analytics as a non-functional cookie with a category of your choice, for example cmplz_event_0.", "complianz");
        }

        return $text;
    }
}

if (!function_exists('cmplz_revoke_link')) {
    function cmplz_revoke_link($text = false)
    {
        $text = $text ? $text : __('Revoke cookie consent', 'complianz');
        $html = '<a href="#" class="cc-revoke-custom">' . $text . '</a><span class="cmplz-status-accepted">' . sprintf(__('Current status: %s', 'complianz'), __("Accepted", 'complianz')) . '</span><span class="cmplz-status-denied">' . sprintf(__('Current status: %s', 'complianz'), __("Denied", 'complianz')) . '</span>';
        return apply_filters('cmplz_revoke_link', $html);
    }
}

if (!function_exists('cmplz_do_not_sell_personal_data_form')) {

    function cmplz_do_not_sell_personal_data_form()
    {

        $html = cmplz_get_template('do-not-sell-my-personal-data-form.php');

        return $html;
    }
}
if (!function_exists('cmplz_sells_personal_data')) {

    function cmplz_sells_personal_data()
    {
        return COMPLIANZ()->company->sells_personal_data();
    }
}
if (!function_exists('cmplz_sold_data_12months')) {

    function cmplz_sold_data_12months()
    {
        return COMPLIANZ()->company->sold_data_12months();
    }
}
if (!function_exists('cmplz_disclosed_data_12months')) {

    function cmplz_disclosed_data_12months()
    {
        return COMPLIANZ()->company->disclosed_data_12months();
    }
}

/*
 * For usage very early in the execution order, use the $page option. This bypasses the class usage.
 *
 *
 * */
if (!function_exists('cmplz_get_value')) {

    function cmplz_get_value($fieldname, $post_id = false, $page = false)

    {
        //we strip the number at the end, in case of the cookie variationid
        $original_fieldname = cmplz_strip_variation_id_from_string($fieldname);

        if (!$page && !isset(COMPLIANZ()->config->fields[$original_fieldname])) return false;

        //if  a post id is passed we retrieve the data from the post
        if (!$page) $page = COMPLIANZ()->config->fields[$original_fieldname]['page'];
        if ($post_id && ($page !== 'wizard')) {
            $value = get_post_meta($post_id, $fieldname, true);
        } else {
            $fields = get_option('complianz_options_' . $page);

            $default = ($page && isset(COMPLIANZ()->config->fields[$original_fieldname]['default'])) ? COMPLIANZ()->config->fields[$original_fieldname]['default'] : '';
            $value = isset($fields[$fieldname]) ? $fields[$fieldname] : $default;

        }

        /*
         * Translate output
         * No translate option for the $page option
         *
         * */
        if (!$page) {
            if (function_exists('icl_translate') || function_exists('pll__')) {
                $type = isset(COMPLIANZ()->config->fields[$original_fieldname]['type']) ? COMPLIANZ()->config->fields[$original_fieldname]['type'] : false;
                if ($type === 'cookies' || $type === 'thirdparties' || $type === 'processors') {
                    if (is_array($value)) {
                        foreach ($value as $key => $key_value) {
                            if (function_exists('pll__')) $value[$key] = pll__($key_value);
                            if (function_exists('icl_translate')) $value[$key] = icl_translate('complianz', $fieldname . "_" . $key, $key_value);
                        }
                    }
                } else {
                    if (isset(COMPLIANZ()->config->fields[$original_fieldname]['translatable']) && COMPLIANZ()->config->fields[$original_fieldname]['translatable']) {
                        if (function_exists('pll__')) $value = pll__($value);
                        if (function_exists('icl_translate')) $value = icl_translate('complianz', $fieldname, $value);
                    }
                }
            }
        }
        return $value;
    }
}

if (!function_exists('cmplz_strip_variation_id_from_string')) {

    function cmplz_strip_variation_id_from_string($string)
    {
        $matches = array();
        if (preg_match('#(\d+)$#', $string, $matches)) {
            return str_replace($matches[1], '', $string);
        }
        return $string;
    }
}
if (!function_exists('cmplz_eu_site_needs_cookie_warning')) {

    function cmplz_eu_site_needs_cookie_warning()
    {
        return COMPLIANZ()->cookie->site_needs_cookie_warning('eu');
    }
}
/*
 *
 * */
if (!function_exists('cmplz_eu_site_needs_cookie_warning_cats')) {

    function cmplz_eu_site_needs_cookie_warning_cats()
    {
        if (cmplz_eu_site_needs_cookie_warning() && cmplz_get_value('use_categories')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('cmplz_company_in_eu')) {

    function cmplz_company_in_eu()
    {
        $country_code = cmplz_get_value('country_company');
        $in_eu = (cmplz_get_region_for_country($country_code) === 'eu');
        return $in_eu;
    }
}

/*
 * Check if this company has this region selected.
 *
 *
 * */
if (!function_exists('cmplz_has_region')) {

    function cmplz_has_region($code)
    {
        $regions = cmplz_get_regions(false);
        if (isset($regions[$code])) return true;
        return false;
    }
}

if (!function_exists('cmplz_get_regions')) {

    function cmplz_get_regions($labels = true)
    {
        $regions = cmplz_get_value('regions', false, 'wizard');

        if (!is_array($regions) && !empty($regions)) $regions = array($regions => 1);
        $output = array();
        if (!empty($regions)) {
            foreach ($regions as $region => $enabled) {
                if (!$enabled) continue;
                $label = $labels && isset(COMPLIANZ()->config->regions[$region]) ? COMPLIANZ()->config->regions[$region]['label'] : '';
                $output[$region] = $label;
            }
        }

        return $output;
    }
}

if (!function_exists('cmplz_multiple_regions')) {

    function cmplz_multiple_regions()
    {
        //if geo ip is not enabled, return false anyway
        if (!cmplz_get_value('use_country', false, 'cookie_settings')) return false;

        $regions = cmplz_get_regions();
        return count($regions) > 1;

    }
}

if (!function_exists('cmplz_get_region_for_country')) {

    function cmplz_get_region_for_country($country_code)
    {
        $regions = COMPLIANZ()->config->regions;

        foreach ($regions as $region_code => $region) {
            if (in_array($country_code, $region['countries'])) return $region_code;
        }

        return false;
    }
}

if (!function_exists('cmplz_notice')) {

    function cmplz_notice($msg, $type='notice', $hide = false, $echo=true)
    {
        if ($msg == '') return;

        $hide_class = $hide ? "cmplz-hide" : "";
        $html = '<div class="cmplz-panel cmplz-'.$type.' ' . $hide_class . '">' . $msg . '</div>';
        if ($echo) {
            echo $html;
        } else {
            return $html;
        }
    }
}

if (!function_exists('cmplz_panel')) {

    function cmplz_panel($title, $html, $custom_btn='', $validate=false)
    {
        if ($title=='') return;

        $slide = ($html == '') ? false : true;
        $validate_icon = $validate ? '<span class="cmplz-multiple-field-validation"><i class="fa fa-times"></i></span>' : '';

        ?>
        <div class="cmplz-panel cmplz-slide-panel">
            <div class="cmplz-panel-title">

                <span class="cmplz-panel-toggle">
                    <i class="toggle fa fa-caret-right"></i>
                    <span class="cmplz-title"><?php echo $title?></span>
                 </span>


                <?php echo $validate_icon?>
                <span><?php echo $custom_btn?></span>
            </div>
                <div class="cmplz-panel-content">
                <?php echo $html?>
                </div>
        </div>
        <?php

    }
}

if (!function_exists('cmplz_list_item')) {

    function cmplz_list_item($title, $link, $btn, $selected)
    {
        if ($title=='') return;
        $selected = $selected ? "selected" : '';
        ?>
        <a class = "cmplz-panel-link" href="<?php echo $link?>">
        <div class="cmplz-panel cmplz-link-panel <?php echo $selected?>">
            <div class="cmplz-panel-title">

                <span class="cmplz-panel-toggle">
                    <i class="fa fa-edit"></i>
                    <span class="cmplz-title"><?php echo $title?></span>

                 </span>

                <?php echo $btn?>
            </div>
        </div>
        </a>
        <?php

    }
}

/*
 * Check if the scan detected social media on the site.
 *
 *
 * */
if (!function_exists('cmplz_scan_detected_social_media')) {

    function cmplz_scan_detected_social_media()
    {
        $social_media = get_option('cmplz_detected_social_media');

        //nothing scanned yet, or nothing found
        if (!$social_media || (count($social_media) == 0)) return false;
        return $social_media;
    }
}

if (!function_exists('cmplz_scan_detected_thirdparty_services')) {

    function cmplz_scan_detected_thirdparty_services()
    {
        $thirdparty = get_option('cmplz_detected_thirdparty_services');
        //nothing scanned yet, or nothing found
        if (!$thirdparty || (count($thirdparty) == 0)) return false;

        return $thirdparty;
    }
}

if (!function_exists('cmplz_update_option')) {

    function cmplz_update_option($page, $fieldname, $value)
    {
        $options = get_option('complianz_options_' . $page);
        $options[$fieldname] = $value;
        if (!empty($options)) update_option('complianz_options_' . $page, $options);
    }
}


if (!function_exists('cmplz_uses_statistics')) {

    function cmplz_uses_statistics()
    {
        $stats = cmplz_get_value('compile_statistics');
        if ($stats !== 'no') return true;

        return false;
    }
}

if (!function_exists('cmplz_uses_only_functional_cookies')) {
    function cmplz_uses_only_functional_cookies()
    {
        return COMPLIANZ()->cookie->uses_only_functional_cookies();
    }
}

if (!function_exists('cmplz_third_party_cookies_active')) {

    function cmplz_third_party_cookies_active()
    {
        return COMPLIANZ()->cookie->third_party_cookies_active();
    }
}

if (!function_exists('cmplz_strip_spaces')) {

    function cmplz_strip_spaces($string)
    {
        return preg_replace('/\s*/m', '', $string);

    }
}

if (!function_exists('cmplz_localize_date')) {

    function cmplz_localize_date($date)
    {
        $month = date('F', strtotime($date)); //june
        $month_localized = __($month); //juni
        $date = str_replace($month, $month_localized, $date);
        $weekday = date('l', strtotime($date)); //wednesday
        $weekday_localized = __($weekday); //woensdag
        $date = str_replace($weekday, $weekday_localized, $date);
        return $date;
    }
}

if (!function_exists('cmplz_wp_privacy_version')) {

    function cmplz_wp_privacy_version()
    {
        global $wp_version;
        return ($wp_version >= '4.9.6');
    }
}

/*
 * callback for privacy document Check if there is a text entered in the custom privacy statement text
 *
 * */
if (!function_exists('cmplz_has_custom_privacy_policy')) {
    function cmplz_has_custom_privacy_policy()
    {
        $policy = cmplz_get_value('custom_privacy_policy_text');
        if (empty($policy)) return false;

        return true;
    }
}

/*
 * callback for privacy statement document, check if google is allowed to share data with other services
 *
 * */
if (!function_exists('cmplz_statistics_no_sharing_allowed')) {
    function cmplz_statistics_no_sharing_allowed()
    {

        $fields = get_option('complianz_options_wizard', false, 'wizard');
        $value = isset($fields['compile_statistics']) ? $fields['compile_statistics'] : false;

        $statistics = cmplz_get_value('compile_statistics', false, 'wizard');
        $tagmanager = ($statistics === 'google-tag-manager') ? true : false;
        $google_analytics = ($statistics === 'google-analytics') ? true : false;

        if ($google_analytics || $tagmanager) {
            $thirdparty = $google_analytics ? cmplz_get_value('compile_statistics_more_info', false, 'wizard') : cmplz_get_value('compile_statistics_more_info_tag_manager', false, 'wizard');

            $no_sharing = (isset($thirdparty['no-sharing']) && ($thirdparty['no-sharing'] == 1)) ? true : false;
            if ($no_sharing) {
                return true;
            } else {
                return false;
            }
        }

        //only applies to google
        return false;
    }
}

/*
 * callback for privacy statement document. Check if ip addresses are stored.
 *
 * */
if (!function_exists('cmplz_no_ip_addresses')) {
    function cmplz_no_ip_addresses()
    {
        $statistics = cmplz_get_value('compile_statistics', false, 'wizard');
        $tagmanager = ($statistics === 'google-tag-manager') ? true : false;
        $matomo = ($statistics === 'matomo') ? true : false;
        $google_analytics = ($statistics === 'google-analytics') ? true : false;

        //not anonymous stats.
        if ($statistics === 'yes') {
            return false;
        }

        if ($google_analytics || $tagmanager) {
            $thirdparty = $google_analytics ? cmplz_get_value('compile_statistics_more_info', false, 'wizard') : cmplz_get_value('compile_statistics_more_info_tag_manager', false, 'wizard');
            $ip_anonymous = (isset($thirdparty['ip-addresses-blocked']) && ($thirdparty['ip-addresses-blocked'] == 1)) ? true : false;
            if ($ip_anonymous) {
                return true;
            } else {
                return false;
            }
        }

        if ($matomo) {
            if (cmplz_get_value('matomo_anonymized', false, 'wizard') === 'yes') {
                return true;
            } else {
                return false;
            }
        }


        return true;
    }
}

if (!function_exists('cmplz_accepted_processing_agreement')) {
    function cmplz_accepted_processing_agreement()
    {
        $statistics = cmplz_get_value('compile_statistics', false, 'wizard');
        $tagmanager = ($statistics === 'google-tag-manager') ? true : false;
        $google_analytics = ($statistics === 'google-analytics') ? true : false;

        if ($google_analytics || $tagmanager) {
            $thirdparty = $google_analytics ? cmplz_get_value('compile_statistics_more_info', false, 'wizard') : cmplz_get_value('compile_statistics_more_info_tag_manager', false, 'wizard');
            $accepted_google_data_processing_agreement = (isset($thirdparty['accepted']) && ($thirdparty['accepted'] == 1)) ? true : false;
            if ($accepted_google_data_processing_agreement) {
                return true;
            } else {
                return false;
            }
        }

        //only applies to google
        return false;
    }
}
if (!function_exists('cmplz_init_cookie_blocker')) {
    function cmplz_init_cookie_blocker()
    {
        if (!cmplz_third_party_cookies_active()) return;

        //don't fire on the back-end
        if (is_admin()) return;

        if (defined('CMPLZ_DO_NOT_BLOCK') && CMPLZ_DO_NOT_BLOCK) return;

        if (cmplz_get_value('disable_cookie_block')) return;

        /* Do not block when visitors are from outside EU or US, if geoip is enabled */
        //check cache, as otherwise all users would get the same output, while this is user specific
        if (!defined('wp_cache') && class_exists('cmplz_geoip') && COMPLIANZ()->geoip->geoip_enabled() && (COMPLIANZ()->geoip->region() !== 'eu') && (COMPLIANZ()->geoip->region() !== 'us')) return;

        /* Do not block if the cookie policy is already accepted */
        //check cache, as otherwise all users would get the same output, while this is user specific
        //removed: this might cause issues when cached, but not in wp

        //do not block cookies during the scan
        if (isset($_GET['complianz_scan_token']) && (sanitize_title($_GET['complianz_scan_token']) == get_option('complianz_scan_token'))) return;

        /* Do not fix mixed content when call is coming from wp_api or from xmlrpc or feed */
        if (defined('JSON_REQUEST') && JSON_REQUEST) return;
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) return;

        add_action("template_redirect", array(COMPLIANZ()->cookie_blocker, "start_buffer"));
        add_action("shutdown", array(COMPLIANZ()->cookie_blocker, "end_buffer"), 999);
    }
}


/*
 * By default, the region which is returned is the region as selected in the wizard settings.
 *
 *
 * */
if (!function_exists('cmplz_ajax_user_settings')) {
    function cmplz_ajax_user_settings()
    {

        $data = apply_filters('cmplz_user_data', array());
        $data['version'] = cmplz_version;
        $data['region'] = apply_filters('cmplz_user_region', COMPLIANZ()->company->get_default_region());
        $data['do_not_track'] = apply_filters('cmplz_dnt_enabled', false);

        $response = json_encode($data);
        header("Content-Type: application/json");
        echo $response;
        exit;
    }
}

add_action('wp_ajax_nopriv_cmplz_track_status', 'cmplz_ajax_track_status');
add_action('wp_ajax_cmplz_track_status', 'cmplz_ajax_track_status');
if (!function_exists('cmplz_ajax_track_status')) {
    function cmplz_ajax_track_status()
    {

        do_action('cmplz_track_status');

        $response = json_encode(array(
            'success' => true,
        ));
        header("Content-Type: application/json");
        echo $response;
        exit;
    }
}

/*
 * Get string of supported laws
 *
 * */
if (!function_exists('cmplz_supported_laws')) {

    function cmplz_supported_laws()
    {
        $regions = cmplz_get_regions();

        $arr = array();
        foreach ($regions as $region => $enabled) {
            //fallback
            //if (!isset(COMPLIANZ()->config->regions[$region])) return __("GDPR", 'complianz');

            $arr[] = COMPLIANZ()->config->regions[$region]['law'];
        }

        if (count($arr) == 0) return __('(select a region)', 'complianz');
        return implode('/', $arr);
    }
}

if (!function_exists('cmplz_get_option')) {
    function cmplz_get_option($name)
    {
        return get_option($name);
    }
}

if (!function_exists('cmplz_esc_html')) {
    function cmplz_esc_html($html)
    {
        return esc_html($html);
    }
}

if (!function_exists('cmplz_esc_url_raw')) {
    function cmplz_esc_url_raw($url)
    {
        return esc_url_raw($url);
    }
}

if (!function_exists('cmplz_is_admin')) {

    function cmplz_is_admin()
    {
        return is_admin();
    }
}

register_activation_hook(__FILE__, 'cmplz_set_activation_time_stamp');
if (!function_exists('cmplz_set_activation_time_stamp')) {
    function cmplz_set_activation_time_stamp($networkwide)
    {
        update_option('cmplz_activation_time', time());
    }
}


/*
 * For all legal documents for the US, privacy statement, dataleaks or processing agreements, the language should always be en_US
 *
 * */

add_filter('locale', 'cmplz_set_plugin_language', 9, 1);
if (!function_exists('cmplz_set_plugin_language')) {
    function cmplz_set_plugin_language($locale)
    {
        $domain = 'complianz';
        global $post;
        if ($domain === 'complianz') {
            $post_id = false;
            if (isset($_GET['post'])) $post_id = $_GET['post'];
            if (isset($_GET['post_id'])) $post_id = $_GET['post_id'];
            $region = (isset($_GET['region'])) ? $_GET['region'] : false;

            if ($post_id && $region) {
                $post_type = get_post_type($post_id);

                if ($region === 'us' && ($post_type === 'cmplz-dataleak' || $post_type === 'cmplz-processing')) {
                    $locale = 'en_US';
                }
            }

            $cmplz_lang = isset($_GET['clang']) ? $_GET['clang'] : false;
            if ($cmplz_lang == 'en') {
                $locale = 'en_US';
            }

        }

        return $locale;
    }
}

/*
 *
 * To make sure the US documents are loaded entirely in English on the front-end,
 * We check if the locale is a not en- locale, and if so, redirect with a query arg.
 * This allows us to recognize the page on the next page load is needing a force US language.
 *
 *
 * */

add_action('wp', 'cmplz_add_query_arg');
if (!function_exists('cmplz_add_query_arg')) {
    function cmplz_add_query_arg()
    {
        $cmplz_lang = isset($_GET['clang']) ? $_GET['clang'] : false;
        if (!$cmplz_lang) {
            global $wp;
            $type = false;

            $post = get_queried_object();
            $locale = get_locale();

            //if the locale is english, don't add any query args.
            if (strpos($locale, 'en-') !== false) return;

            if ($post && property_exists($post, 'post_content')) {
                $pattern = '/cmplz-document type="(.*?)"/i';

                if (preg_match_all($pattern, $post->post_content, $matches, PREG_PATTERN_ORDER)) {
                    if (isset($matches[1][0])) $type = $matches[1][0];
                }

                if (strpos($type, '-us') !== FALSE) {
                    wp_redirect(home_url(add_query_arg('clang', 'en', $wp->request)));
                    exit;
                }
            }

        }
    }
}

if (!function_exists('cmplz_array_filter_multidimensional')) {
    function cmplz_array_filter_multidimensional($array, $filter_key, $filter_value)
    {
        $new = array_filter($array, function ($var) use ($filter_value, $filter_key) {
            return isset($var[$filter_key]) ? ($var[$filter_key] == $filter_value) : false;
        });

        return $new;
    }
}

if (!function_exists('cmplz_allowed_html')){
    function cmplz_allowed_html() {

        $allowed_tags = array(
            'a' => array(
                'class' => array(),
                'href'  => array(),
                'rel'   => array(),
                'title' => array(),
                'target' => array(),
            ),
            'b' => array(),
            'blockquote' => array(
                'cite'  => array(),
            ),
            'div' => array(
                'class' => array(),
                'title' => array(),
                'id' => array(),
            ),
            'h1' => array(

            ),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'i' => array(),
            'input' => array(
                'type' =>array(),
                'class'=>array(),
                'id'=>array(),
                'required' => array(),
                'value' => array(),
                'placeholder'=>array(),
            ),
            'img' => array(
                'alt'    => array(),
                'class'  => array(),
                'height' => array(),
                'src'    => array(),
                'width'  => array(),
            ),
            'label' => array(),
            'li' => array(
                'class' => array(),
                'id' => array(),
            ),
            'ol' => array(
                'class' => array(),
                'id' => array(),
            ),
            'p' => array(
                'class' => array(),
                'id' => array(),
            ),
            'span' => array(
                'class' => array(),
                'title' => array(),
                'id' => array(),
            ),
            'strong' => array(),
            'ul' => array(
                'class' => array(),
                'id' => array(),
            ),
        );

        return apply_filters("cmplz_allowed_html",$allowed_tags);
    }
}