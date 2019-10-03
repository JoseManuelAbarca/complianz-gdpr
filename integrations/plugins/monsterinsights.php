<?php
defined('ABSPATH') or die("you do not have acces to this page!");
add_filter('cmplz_default_value', 'cmplz_monsterinsights_set_default', 20, 2);
function cmplz_monsterinsights_set_default($value, $fieldname)
{
    if ($fieldname == 'compile_statistics') {
        return "google-analytics";

    }
    return $value;
}


/**
 * If any of the integrated plugins is used, show a notice here.
 *
 *
 * */

add_action('cmplz_notice_compile_statistics_more_info', 'cmplz_monsterinsights_compile_statistics_more_info_notice');
function cmplz_monsterinsights_compile_statistics_more_info_notice()
{
    cmplz_notice(__("You use Monsterinsights: if you enable the anonymize ip option, please make sure that you have enabled it in Monsterinsights", 'complianz-gdpr'));
}


/**
 * Add conditional classes to the monsterinsights statistics script
 *
 * */

function cmplz_monsterinsights_add_monsterinsights_attributes($attr)
{
    $classes = COMPLIANZ()->cookie->get_statistics_script_classes();
    $attr['class'] = implode(' ', $classes);
    return $attr;
}

add_filter('monsterinsights_tracking_analytics_script_attributes', 'cmplz_monsterinsights_add_monsterinsights_attributes', 10, 1);


function cmplz_monsterinsights_compile_statistics_notice()
{
    cmplz_notice(__("You use Monsterinsights, so the answer to this question should be Google Analytics", 'complianz-gdpr'));

}

add_action('cmplz_notice_compile_statistics', 'cmplz_monsterinsights_compile_statistics_notice');

/**
 * We remove some actions to integrate fully
 * */
function cmplz_monsterinsights_remove_scripts_others()
{

    remove_action('wp_head', 'monsterinsights_tracking_script', 6);
    remove_action('cmplz_statistics_script', array(COMPLIANZ()->cookie, 'get_statistics_script'), 10);

}

add_action('after_setup_theme', 'cmplz_monsterinsights_remove_scripts_others');

/**
 * Execute the monsterinsights script at the right point
 */
add_action('cmplz_before_statistics_script', 'monsterinsights_tracking_script', 10, 1);


/**
 * Remove stuff which is not necessary anymore
 *
 * */

function cmplz_monsterinsights_remove_actions()
{
    remove_action('cmplz_notice_compile_statistics', array(COMPLIANZ()->cookie, 'show_compile_statistics_notice'), 10);
}

add_action('init', 'cmplz_monsterinsights_remove_actions');

/**
 * Hide the stats configuration options when monsterinsights is enabled.
 * @param $fields
 * @return mixed
 */

function cmplz_monsterinsights_filter_fields($fields)
{
    unset($fields['configuration_by_complianz']);
    unset($fields['UA_code']);

    return $fields;
}

add_filter('cmplz_fields', 'cmplz_monsterinsights_filter_fields');


function cmplz_monsterinsights_filter_warnings($warnings)
{
    if (($key = array_search('ga-needs-configuring', $warnings)) !== false) {
        unset($warnings[$key]);
    }
    return $warnings;
}
add_filter('cmplz_warnings', 'cmplz_monsterinsights_filter_warnings');





