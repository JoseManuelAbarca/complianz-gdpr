<?php
/*100% match*/

defined('ABSPATH') or die("you do not have acces to this page!");

if (!class_exists("cmplz_admin")) {
    class cmplz_admin
    {
        private static $_this;
        public $error_message = "";
        public $success_message = "";

        function __construct()
        {
            if (isset(self::$_this))
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'complianz'), get_class($this)));

            self::$_this = $this;
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_menu', array($this, 'register_admin_page'));
            add_action('admin_notices', array($this, 'show_notices'), 10);
            add_action('admin_init', array($this, 'process_support_request'));

            $plugin = cmplz_plugin;
            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));

            //Add actions for dashboard components
            add_action("cmplz_dashboard_third_block", array($this, 'dashboard_third_block'));
            add_action("cmplz_dashboard_footer", array($this, 'dashboard_footer'));
            add_action("cmplz_dashboard_second_block", array($this, 'dashboard_second_block'));

            //some custom warnings
            add_filter('cmplz_warnings_types', array($this, 'filter_warnings'));

            add_action('cmplz_tools', array($this, 'dashboard_tools'));


        }

        static function this()
        {
            return self::$_this;
        }

        public function enqueue_assets($hook)
        {
            if ((strpos($hook, 'complianz') === FALSE) && strpos($hook, 'cmplz') === FALSE) return;

            wp_register_style('cmplz-circle', cmplz_url . 'assets/css/circle.css', array(), cmplz_version);
            wp_enqueue_style('cmplz-circle');

            wp_register_style('cmplz-fontawesome', cmplz_url . 'assets/fontawesome/fontawesome-all.css', "", cmplz_version);
            wp_enqueue_style('cmplz-fontawesome');

            wp_register_style('cmplz', trailingslashit(cmplz_url) . 'assets/css/style.css', "", cmplz_version);
            wp_enqueue_style('cmplz');

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('cmplz-ace', cmplz_url . "assets/ace/ace.js", array(), cmplz_version, false);

            $minified = (defined('WP_DEBUG') && WP_DEBUG) ? '' : '.min';
            wp_enqueue_script('cmplz-admin', cmplz_url . "assets/js/admin$minified.js", array('wp-color-picker'), cmplz_version, true);

            $progress = COMPLIANZ()->cookie->get_progress_count();
            wp_localize_script(
                'cmplz-admin',
                'complianz_admin',
                array(
                    'admin_url' => admin_url('admin-ajax.php'),
                    'progress' => $progress,
                )
            );
        }


        public function plugin_settings_link($links)
        {
            $settings_link = '<a href="' . admin_url("admin.php?page=complianz") . '">' . __("Settings", 'complianz') . '</a>';
            array_unshift($links, $settings_link);

            $faq_link = '<a target="_blank" href="https://complianz.io/support">' . __('Support', 'complianz') . '</a>';
            array_unshift($links, $faq_link);

            return $links;
        }

        public function filter_warnings($warnings)
        {

            if (!COMPLIANZ()->wizard->wizard_completed() && COMPLIANZ()->wizard->all_fields_completed()) {
                $warnings['wizard-incomplete']['label_error'] = __('All fields have been completed, but you have not clicked the finish button yet.', 'complianz');
            }
            return $warnings;
        }

        public function get_warnings($cache = true)
        {
            $warnings = $cache ? get_transient('complianz_warnings') : false;
            //re-check if there are no warnings, or if the transient has expired
            if (!$warnings || count($warnings) > 0) {
                $warnings = array();

                if (!COMPLIANZ()->wizard->wizard_completed()) {
                    $warnings[] = 'wizard-incomplete';
                }

                if (COMPLIANZ()->cookie->cookies_changed()) {
                    $warnings[] = 'cookies-changed';
                }

                if (COMPLIANZ()->cookie->plugins_updated() || COMPLIANZ()->cookie->plugins_changed()) {
                    $warnings[] = 'plugins-changed';
                }

                if (COMPLIANZ()->cookie->uses_google_analytics() && !COMPLIANZ()->cookie->analytics_configured()) {
                    $warnings[] = 'ga-needs-configuring';
                }

                if (!is_ssl()) {
                    $warnings[] = 'no-ssl';
                }

                set_transient('complianz_warnings', $warnings, HOUR_IN_SECONDS);
            }
            return $warnings;
        }

        // Register a custom menu page.
        public function register_admin_page()
        {
            if (cmplz_wp_privacy_version() && !current_user_can('manage_privacy_options')) return;

            $warnings = $this->get_warnings();
            $warning_count = count($warnings);
            $warning_title = esc_attr(sprintf('%d plugin warnings', $warning_count));
            $menu_label = sprintf(__('Complianz %s', 'complianz'), "<span class='update-plugins count-$warning_count' title='$warning_title'><span class='update-count'>" . number_format_i18n($warning_count) . "</span></span>");


            global $cmplz_admin_page;
            $cmplz_admin_page = add_menu_page(
                __('Complianz', 'complianz'),
                $menu_label,
                'manage_options',
                'complianz',
                array($this, 'main_page'),
                cmplz_url . 'assets/images/menu-icon.png',
                CMPLZ_MAIN_MENU_POSITION
            );


            add_submenu_page(
                'complianz',
                __('Wizard', 'complianz'),
                __('Wizard', 'complianz'),
                'manage_options',
                'cmplz-wizard',
                array($this, 'wizard_page')
            );

            add_submenu_page(
                'complianz',
                __('Cookie warning', 'complianz'),
                __('Cookie warning', 'complianz'),
                'manage_options',
                "cmplz-cookie-warning",
                array($this, 'cookie_page')
            );

            do_action('cmplz_admin_menu');

        }


        public function wizard_page()
        {

            ?>
            <div class="wrap">
                <div class="cmplz-wizard-title"><h1><?php _e("Wizard", 'complianz') ?></h1></div>

                <?php if (defined('cmplz_free') || COMPLIANZ()->license->license_is_valid()) { ?>
                    <?php COMPLIANZ()->wizard->wizard('wizard'); ?>
                <?php } else {
                    cmplz_notice(__('Your license needs to be activated to unlock the wizard', 'complianz'));
                }
                ?>
            </div>
            <?php
        }

        public function main_page()
        {

            ?>
            <div class="wrap" id="complianz">
                <div class="dashboard">
                    <?php $this->get_status_overview() ?>
                    <?php


                    if ($this->error_message != "") echo $this->error_message;
                    if ($this->success_message != "") echo $this->success_message;
                    ?>

                </div>
            </div>
            <?php
        }


        public function dashboard_second_block()
        {
            ?>

            <div class="cmplz-support-top cmplz-dashboard-text">
                <div class="cmplz-dashboard-support-title"> <?php echo __('Tools', 'complianz'); ?> </div>
            </div>
            <?php
            ?>
            <div class="cmplz-dashboard-support-content cmplz-dashboard-text">
                    <ul>
                        <?php do_action('cmplz_tools')?>
                        <li style="border-bottom: 1px solid;"><i class="fas fa-plus"></i><?php echo sprintf(__("For the most common issues see the Complianz %sknowledge base%s", "complianz"), '<a href="https://complianz.io/support">', '</a>'); ?> </li>
                        <li style="border-bottom: 1px solid;"><i class="fas fa-plus"></i><?php echo sprintf(__("Check out the Complianz %sWordPress plugin page%s", "complianz"), '<a href="https://wordpress.org/plugins/complianz-gdpr/">', '</a>'); ?> </li>
                        <li style="bottom-border: 0px;"><i class="fas fa-plus"></i><?php echo sprintf(__("Upgrade to Complianz premium for %spremium support%s", "complianz"), '<a href="https://complianz.io/pricing">', '</a>'); ?> </li>
                    </ul>
            </div>

            <?php
        }

        public function dashboard_tools(){
            ?>
            <li style="border-bottom: 1px solid;"><i class="fas fa-plus"></i>
                <a href="<?php echo admin_url('tools.php?page=export_personal_data')?>"><?php _e("Export personal data", "complianz"); ?></a>
            </li>
            <li style="border-bottom: 1px solid;"><i class="fas fa-plus"></i>
                <a href="<?php echo admin_url('tools.php?page=remove_personal_data')?>"><?php _e("Erase personal data", "complianz"); ?></a>
            </li>

            <?php
        }


        public function dashboard_third_block()
        {
            ?>
            <div class="cmplz-documents-top cmplz-dashboard-text">
                <div class="cmplz-documents-title"> <?php echo __('Upgrade to premium' , 'complianz'); ?> </div>
            </div>
            <div class="cmplz-upgrade-table">
                <table class="cmplz-dashboard-documents-table cmplz-dashboard-text">
                    <ul style="margin: 5px;">
                        <li><i class="fas fa-plus" style="margin-bottom: 5px;"></i><?php echo __('Privacy Statement, Disclaimer & Processing Agreement', 'complianz');?></li>
                        <li><i class="fas fa-plus" style="margin-bottom: 5px;"></i><?php echo __('Legal Updates, GEO Location & Do Not Track', 'complianz');?></li>
                        <li style="border-bottom: 0px;"><i class="fas fa-plus"></i><?php echo __('Multiple Languages & Premium Support', 'complianz');?></li>
                    </ul>
                </table>
            </div>
            <div class="cmplz-documents-bottom cmplz-dashboard-text">
                <div class="cmplz-premium-cta"> <?php echo __('Discover all premium benefits' , 'complianz'); ?> <a href="https://complianz.io" target="_blank"><div><button class="button cmplz-continue-button cmplz-btn-cta"><?php echo __('Read more' , 'complianz'); ?> <i class="fa fa-angle-right"></i></button></div></a>
                </div>
            </div>

            <?php
        }

        public function dashboard_footer()
        {
            ?>
            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Really Simple SSL', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Trusted by over 1 million WordPress users', 'complianz'); ?></div>
                <a href="https://really-simple-ssl.com" target="_blank">
                    <span class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </span>
                </a>
            </div>
            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Feature requests', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Need new features or languages? Let us know!', 'complianz'); ?></div>
                <a href="https://complianz.io/contact" target="_blank">
                    <span class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </span>
                </a>
            </div>
            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Tutorials', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Check out our tutorials on complianz.io!', 'complianz'); ?></div>
                <a href="https://complianz.io/tutorials/" target="_blank">
                    <span class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i></span></a>
            </div>
            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Our blog', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Stay up to date with the latest news', 'complianz'); ?></div>
                <a href="https://complianz.io/articles" target="_blank">
                    <span class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </span>
                </a>
            </div>

            <?php
        }


        public function get_status_overview()
        {

            ?>
            <div class="cmplz-dashboard-container">
                <div class="cmplz-dashboard-header">
                    <div class="cmplz-header-top">
                    </div>
                </div>
                <div class="cmplz-dashboard-content-container">
                    <div class="cmplz-logo">
                        <img src="<?php echo cmplz_url . 'assets/images/cmplz-logo.png'?>"> <?php echo apply_filters('cmplz_logo_extension', __('Free', 'complianz'))?>
                    </div>
                    <div class="cmplz-completed-text">
                    <div class="cmplz-header-text">

                          <?php printf(__("%s completed.", "complianz"), COMPLIANZ()->wizard->wizard_percentage_complete() . "%") ?>
                         <?php
                         if (COMPLIANZ()->wizard->wizard_percentage_complete() < 100) {
                             _e('Complianz GDPR is not configured completely.', 'complianz'); ?>
                            <div>
                                <a href="<?php echo admin_url('admin.php?page=cmplz-wizard')?>" class="button cmplz-continue-button">
                                    <?php echo __('Continue', 'complianz'); ?>
                                    <i class="fa fa-angle-right"></i>
                                </a>
                            </div>
                             <?php
                         } else {
                             _e('Great! Complianz GDPR has been configured.', 'complianz');
                         }
                         ?>
                    </div>
                    </div>
                    <div class="cmplz-dashboard-progress cmplz-dashboard-item">
                        <div class="cmplz-dashboard-progress-top cmplz-dashboard-text">
                            <div class="cmplz-dashboard-top-text">
                                <div class="cmplz-dashboard-top-text-title"><?php echo __('Your progress', 'complianz'); ?> </div>
                                <?php if (COMPLIANZ()->wizard->wizard_percentage_complete() < 100) {
                                    $text = __('Almost ready', 'complianz');
                                } else {
                                    $text = __('Well done! You are GDPR compliant.', 'complianz');
                                }

                                echo "<div class='cmplz-dashboard-top-text-subtitle'>" . $text . "</div>";
                                ?>
                            </div>
                            <div class="cmplz-percentage-complete green c100 p<?php echo COMPLIANZ()->wizard->wizard_percentage_complete(); ?>">
                                <span><?php echo COMPLIANZ()->wizard->wizard_percentage_complete(); ?>%</span>
                                <div class="slice">
                                    <div class="bar"></div>
                                    <div class="fill"></div>
                                </div>
                            </div>


                            <div class="cmplz-continue-wizard-btn">
                                <?php if (COMPLIANZ()->wizard->wizard_percentage_complete() < 100) { ?>
                                    <a href="<?php home_url() . '/wp-admin/admin.php?page=cmplz-wizard' ?>">
                                        <div>
                                            <a href="<?php echo admin_url('admin.php?page=cmplz-wizard')?>" class="button cmplz-continue-button">
                                            <?php echo __('Continue', 'complianz'); ?>
                                                <i class="fa fa-angle-right"></i></a>
                                        </div>
                                    </a>
                                <?php } ?>
                            </div>


                        </div>
                        <table class="cmplz-steps-table cmplz-dashboard-text">
                            <?php
                            do_action('cmplz_warnings');
                            if (COMPLIANZ()->cookie->cookie_warning_required() && !COMPLIANZ()->wizard->wizard_completed()) {
                                $this->get_dashboard_element(__('Your site requires a cookie warning, but the wizard is not completed yet', 'complianz'), 'error');
                            }
                            if (COMPLIANZ()->cookie->cookie_warning_required() && COMPLIANZ()->wizard->wizard_completed()) {
                                $this->get_dashboard_element(__('Your site requires a cookie warning, which has been enabled', 'complianz'), 'success');
                            }
                            if (!COMPLIANZ()->cookie->cookie_warning_required()) {
                                $this->get_dashboard_element(__('Your site does not require a cookie warning. No cookie warning has been enabled.', 'complianz'), 'success');
                            }

                            if (defined('cmplz_free') && !COMPLIANZ()->document->page_exists('privacy-statement')){
                                $this->get_dashboard_element(sprintf(__('You do not have a privacy policy validated by Complianz GDPR yet. Upgrade to %spremium%s to generate a custom privacy policy', 'complianz'), '<a href="https://complianz.io">', '</a>'), 'warning');
                            }

                            if (!COMPLIANZ()->document->page_exists('cookie-statement')){
                                $this->get_dashboard_element(sprintf(__('You do not have a cookie policy validated by Complianz GDPR yet.', 'complianz'), '<a href="https://complianz.io">', '</a>'), 'error');
                            }

                            $last_cookie_scan = COMPLIANZ()->cookie->get_last_cookie_scan_date();
                            if ($last_cookie_scan) {
                                $this->get_dashboard_element(sprintf(__('Last cookie scan on %s', 'complianz'), $last_cookie_scan), 'success');

                            } else {
                                $this->get_dashboard_element(sprintf(__('No cookie scan yet', 'complianz'), $last_cookie_scan), 'error');
                            }

                            $warnings = $this->get_warnings(false);
                            $warning_types = apply_filters('cmplz_warnings_types', COMPLIANZ()->config->warning_types);

                            foreach ($warning_types as $key => $type) {
                                if (in_array($key, $warnings)) {
                                    $this->get_dashboard_element($type['label_error'], 'error');
                                } else {
                                    $this->get_dashboard_element($type['label_ok'], 'success');
                                }
                            }

                            if (defined('cmplz_free')) {
                                $this->get_dashboard_element(sprintf(__('The browser setting Do No Track is not respected yet. Upgrade to %spremium%s to make your site DNT compliant', 'complianz'), '<a href="https://complianz.io">', '</a>'), 'warning');
                            }
                            ?>
                        </table>
                    </div>

                    <div class="cmplz-dashboard-support cmplz-dashboard-item">
                        <?php do_action("cmplz_dashboard_second_block")?>
                    </div>

                    <div class="cmplz-dashboard-documents cmplz-dashboard-item">
                        <?php do_action("cmplz_dashboard_third_block") ?>
                    </div>
                <div class="cmplz-dashboard-footer">
                    <?php do_action("cmplz_dashboard_footer") ?>
                </div>
                </div>
            </div>
            <?php
        }

        public function process_support_request()
        {
            if (isset($_POST['cmplz_support_request']) && isset($_POST['cmplz_support_email'])) {
                if (!is_email($_POST['cmplz_support_email'])) {
                    $this->error_message = __('Email address not valid', 'complianz');
                    return;
                }

                if (!wp_verify_nonce($_POST['cmplz_nonce'], 'cmplz_support')) return;

                $email = sanitize_email($_POST['cmplz_support_email']);
                $subject = sanitize_text_field($_POST['cmplz_support_subject']);

                $allowed_tags = wp_kses_allowed_html('post');
                $support_request = wp_kses($_POST['cmplz_support_request'], $allowed_tags);

                $license = get_option('cmplz_license_key');
                $user_info = get_userdata(get_current_user_id());
                $nicename = $user_info->user_nicename;

                $headers[] = "Reply-to: $nicename <$email>" . "\r\n";

                $to = "support@complianz.io";
                $message = "License: $license <br><br>";
                $message .= $support_request . "<br><br>";
                $message .= $nicename;
                add_filter('wp_mail_content_type', function ($content_type) {
                    return 'text/html';
                });

                $success = wp_mail($to, $subject, $message, $headers);

                // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
                remove_filter('wp_mail_content_type', 'set_html_content_type');
                if ($success) {
                    $this->success_message = sprintf(__("Your support request has been received. We will reply shortly. You can track the status of your request at %scomplianz.io%s", "complianz"), '<a href="https://complianz.io/support">', '</a>');

                } else {
                    $this->error_message = __("Something went wrong while submitting the support request", "complianz");

                }
            }
        }

        public function get_dashboard_element($content, $type = 'error')
        {
            $icon = "";
            switch ($type){
                case 'error':
                    $icon = 'fa-times';
                    break;
                case 'success':
                    $icon = 'fa-check';
                    break;
                case 'warning':
                    $icon = 'fa-exclamation-circle';
                    break;
                }

            $type = ($type=='success') ? 'success' : 'error';

            ?>
            <tr class="<?php echo "cmplz-".$type?>">
                <td><i class="fa <?php echo $icon ?>"></i></td>
                <td><?php echo $content ?></td>
            </tr>
            <?php
        }

        public function ctb_section_text()
        {

        }

        public function cookie_page()
        {
            ?>
            <style>
                textarea {
                    width: 450px;
                    height: 100px;
                }
            </style>
            <div class="wrap cookie-warning">
                <h1><?php _e("Cookie warning settings", 'complianz') ?></h1>

                <?php
                if (!COMPLIANZ()->wizard->wizard_completed()) {
                    cmplz_notice(__('Please complete the wizard to check if you need a cookie warning.', 'complianz'));
                } else {
                    if (!COMPLIANZ()->cookie->cookie_warning_required()) {
                        cmplz_notice(__('Your website does not require a cookie warning, so these settings do not apply.', 'complianz'));
                    } else {
                        _e('Your website requires a cookie warning, these settings will determine how the popup will look.', 'complianz');
                    }
                }
                ?>
                <form action="" method="post">

                    <table class="form-table">
                        <?php

                        COMPLIANZ()->field->get_fields('cookie_settings');

                        COMPLIANZ()->field->save_button();

                        ?>

                    </table>
                </form>
            </div>
            <?php
        }


        public function show_notices()
        {
            if (!is_user_logged_in()) return;

            if (version_compare(phpversion(), '5.6', '<')) {
                ?>
                // php version isn't high enough
                <div id="message" class="error fade notice cmplz-wp-notice">
                    <h2><?php echo __("PHP version problem", "complianz"); ?></h2>
                <p>
                    <?php _e("Complianz GDPR requires at least PHP version 5.6. Please upgrade your PHP version before continuing.", "complianz"); ?>
                </p>
                </div>
                <?php
            }
        }


        public function get_help_tip($str)
        {
            ?>
            <span class="cmplz-tooltip-right tooltip-right" data-cmplz-tooltip="<?php echo $str ?>">
              <span class="dashicons dashicons-editor-help"></span>
            </span>
            <?php

        }

        public function send_mail($message, $from_name, $from_email)
        {
            $subject = "Support request from $from_name";
            $to = "support@complianz.io";
            $headers = array();
            add_filter('wp_mail_content_type', function ($content_type) {
                return 'text/html';
            });

            $headers[] = "Reply-To: $from_name <$from_email>" . "\r\n";
            $success = wp_mail($to, $subject, $message, $headers);

            // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
            remove_filter('wp_mail_content_type', 'set_html_content_type');
            return $success;
        }

    }
} //class closure
