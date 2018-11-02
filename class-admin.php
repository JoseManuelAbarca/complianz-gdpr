<?php
/*100% match*/

defined('ABSPATH') or die("you do not have acces to this page!");

if (!class_exists("cmplz_admin")) {
    class cmplz_admin
    {
        private static $_this;
        public $error_message = "";
        public $success_message = "";
        public $task_count=0;

        function __construct()
        {
            if (isset(self::$_this))
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'complianz'), get_class($this)));

            self::$_this = $this;
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_menu', array($this, 'register_admin_page'), 20);
            add_action('admin_notices', array($this, 'show_notices'), 10);
            add_action('admin_init', array($this, 'process_support_request'));

            $plugin = cmplz_plugin;
            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));

            //Add actions for dashboard components
            add_action("cmplz_dashboard_third_block", array($this, 'dashboard_third_block'));
            add_action("cmplz_dashboard_footer", array($this, 'dashboard_footer'));
            add_action("cmplz_dashboard_second_block", array($this, 'dashboard_second_block'));
            add_action("cmplz_documents_footer", array($this, 'documents_footer'));
            add_action("cmplz_documents", array($this, 'documents'));

            //some dashboard elements
            add_action('cmplz_dashboard_elements_error', array($this, 'dashboard_elements'));

            //some custom warnings
            add_filter('cmplz_warnings_types', array($this, 'filter_warnings'));

            add_action('cmplz_tools', array($this, 'dashboard_tools'));

            add_action('admin_init', array($this, 'check_upgrade'), 10, 2);

            add_action('cmplz_show_message', array($this,'show_message'));

        }

        static function this()
        {
            return self::$_this;


        }

        public function show_message(){
            if (!empty($this->error_message)){
                cmplz_notice($this->error_message, 'warning');
                $this->error_message = "";
            }

            if (!empty($this->success_message)){
                cmplz_notice($this->success_message, 'success', true);
                $this->success_message = "";
            }
        }

        public function check_upgrade()
        {
            $prev_version = get_option('cmplz-current-version', '1.0.0');

            if (version_compare($prev_version, '1.1.2', '<')) {
                //move cookieblock settings to page general settings
                $default = isset(COMPLIANZ()->config->fields['disable_cookie_block']['default']) ? COMPLIANZ()->config->fields['disable_cookie_block']['default'] : '';
                $fields = get_option('complianz_options_cookie_settings');
                $value = isset($fields['disable_cookie_block']) ? $fields['disable_cookie_block'] : $default;

                $general = get_option('complianz_options_settings');
                $general['disable_cookie_block'] = $value;
            }

            //as of 1.1.10, publish date is stored in variable.
            if (version_compare($prev_version, '1.2.0', '<')) {
                $date = get_option('cmplz_publish_date');
                if (empty($date)) {
                    COMPLIANZ()->cookie->update_cookie_policy_date();
                }
            }

            if (version_compare($prev_version, '2.0', '<')) {
                //add category eu existing dataleaks and processing agreements.
                $posts = get_posts(array('post_type' => array('cmplz-dataleak', 'cmplz-processing'), 'post_status'=> array('publish', 'pending', 'draft', 'auto-draft'), 'posts_per_page'=>-1));
                foreach ($posts as $post){
                    if (!COMPLIANZ()->document->get_region($post->ID)){
                        COMPLIANZ()->document->set_region($post->ID, 'eu');
                    }
                }

                $pages = COMPLIANZ()->config->pages;
                foreach ($pages as $type => $page) {
                    if ($page['public'] == true){
                        $post_id = COMPLIANZ()->document->get_shortcode_page_id($type);
                        if ($post_id) COMPLIANZ()->document->set_page_url($post_id, $type);
                    }
                }
            }

            /*
             * If the legal documents have changed, we notify the user of this.
             *
             * */

            if (CMPLZ_LEGAL_VERSION > get_option('cmplz_legal_version',0)){
                update_option('cmplz_plugin_new_features', true);
                update_option('cmplz_legal_version', CMPLZ_LEGAL_VERSION);
            }

            update_option('cmplz-current-version', cmplz_version);
        }



        public function complianz_plugin_has_new_features(){
            return get_option('cmplz_plugin_new_features');
        }

        public function reset_complianz_plugin_has_new_features(){
            return update_option('cmplz_plugin_new_features', false);
        }

        public function enqueue_assets($hook)
        {
            if ((strpos($hook, 'complianz') === FALSE) && strpos($hook, 'cmplz') === FALSE) return;

            wp_register_style('cmplz-circle', cmplz_url . 'core/assets/css/circle.css', array(), cmplz_version);
            wp_enqueue_style('cmplz-circle');

            wp_register_style('cmplz-fontawesome', cmplz_url . 'core/assets/fontawesome/fontawesome-all.css', "", cmplz_version);
            wp_enqueue_style('cmplz-fontawesome');

            wp_register_style('cmplz', trailingslashit(cmplz_url) . 'core/assets/css/style.css', "", cmplz_version);
            wp_enqueue_style('cmplz');

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('cmplz-ace', cmplz_url . "core/assets/ace/ace.js", array(), cmplz_version, false);

            $minified = (defined('WP_DEBUG') && WP_DEBUG) ? '' : '.min';
            wp_enqueue_script('cmplz-admin', cmplz_url . "core/assets/js/admin$minified.js", array('jquery', 'wp-color-picker'), cmplz_version, true);

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

            if (!COMPLIANZ()->wizard->wizard_completed_once() && COMPLIANZ()->wizard->all_required_fields_completed('wizard')) {
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

                if (cmplz_has_region('eu') && !COMPLIANZ()->document->page_exists('cookie-statement')) {
                    $warnings[] = 'no-cookie-policy';
                }

                if (cmplz_has_region('us') && !COMPLIANZ()->document->page_exists('cookie-statement-us')) {
                    $warnings[] = 'no-cookie-policy-us';
                }

                if (!COMPLIANZ()->wizard->wizard_completed_once() || !COMPLIANZ()->wizard->all_required_fields_completed('wizard')) {
                    $warnings[] = 'wizard-incomplete';
                }

                if (COMPLIANZ()->cookie->plugins_updated() || COMPLIANZ()->cookie->plugins_changed()) {
                    $warnings[] = 'plugins-changed';
                }

                if (COMPLIANZ()->cookie->uses_google_analytics() && !COMPLIANZ()->cookie->analytics_configured()) {
                    $warnings[] = 'ga-needs-configuring';
                }

                if (COMPLIANZ()->cookie->uses_google_tagmanager() && !COMPLIANZ()->cookie->tagmanager_configured()) {
                    $warnings[] = 'gtm-needs-configuring';
                }

                if (COMPLIANZ()->cookie->uses_matomo() && !COMPLIANZ()->cookie->matomo_configured()) {
                    $warnings[] = 'matomo-needs-configuring';
                }

                if (!is_ssl()) {
                    $warnings[] = 'no-ssl';
                }

                if ($this->complianz_plugin_has_new_features()) {
                    $warnings[] = 'complianz-gdpr-feature-update';
                }

                $warnings = apply_filters('cmplz_warnings', $warnings);

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
                cmplz_url . 'core/assets/images/menu-icon.png',
                CMPLZ_MAIN_MENU_POSITION
            );
            add_submenu_page(
                'complianz',
                __('Dashboard', 'complianz'),
                __('Dashboard', 'complianz'),
                'manage_options',
                'complianz',
                array($this, 'main_page')
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

            add_submenu_page(
                'complianz',
                __('Script center', 'complianz'),
                __('Script center', 'complianz'),
                'manage_options',
                "cmplz-script-center",
                array($this, 'script_center')
            );

            add_submenu_page(
                'complianz',
                __('Settings'),
                __('Settings'),
                'manage_options',
                "cmplz-settings",
                array($this, 'settings')
            );

            do_action('cmplz_admin_menu');

        }


        public function wizard_page()
        {

            ?>
            <div class="wrap">
                <div class="cmplz-wizard-title"><h1><?php _e("Wizard", 'complianz') ?></h1></div>

                <?php if (apply_filters('cmplz_show_wizard_page', true)) { ?>
                    <?php COMPLIANZ()->wizard->wizard('wizard'); ?>
                <?php } else {
                    cmplz_notice(__('Your license needs to be activated to unlock the wizard', 'complianz'), 'warning');
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

            <div class="cmplz-header-top cmplz-dashboard-text">
                <div class="cmplz-dashboard-title"> <?php echo __('Tools', 'complianz'); ?> </div>
            </div>
            <?php
            ?>
            <div class="cmplz-dashboard-support-content cmplz-dashboard-text">
                <ul>
                    <?php do_action('cmplz_tools') ?>
                    <li>
                        <i class="fas fa-plus"></i><?php echo sprintf(__("For the most common issues see the Complianz %sknowledge base%s", "complianz"), '<a target="_blank" href="https://complianz.io/support">', '</a>'); ?>
                    </li>
                    <li>
                        <i class="fas fa-plus"></i><?php echo sprintf(__("Ask your questions on the %sWordPress forum%s", "complianz"), '<a target="_blank" href="https://wordpress.org/support/plugin/complianz-gdpr">', '</a>'); ?>
                    </li>
                    <li>
                        <i class="fas fa-plus"></i><?php echo __("Create dataleak report", "complianz") . " " . sprintf(__('(%spremium%s)', "complianz"), '<a target="_blank" href="https://complianz.io">', "</a>"); ?>
                    </li>
                    <li>
                        <i class="fas fa-plus"></i><?php echo __("Create processing agreement", "complianz") . " " . sprintf(__('(%spremium%s)', "complianz"), '<a target="_blank" href="https://complianz.io">', "</a>"); ?>
                    </li>
                    <li>
                        <i class="fas fa-plus"></i><?php echo sprintf(__("Upgrade to Complianz premium for %spremium support%s", "complianz"), '<a target="_blank" href="https://complianz.io/pricing">', '</a>'); ?>
                    </li>
                </ul>
            </div>

            <?php
        }

        public function dashboard_tools()
        {
            if (cmplz_wp_privacy_version()) {
                ?>
                <li><i class="fas fa-plus"></i><a
                            href="<?php echo admin_url('tools.php?page=export_personal_data') ?>"><?php _e("Export personal data", "complianz"); ?></a>
                </li>
                <li><i class="fas fa-plus"></i><a
                            href="<?php echo admin_url('tools.php?page=remove_personal_data') ?>"><?php _e("Erase personal data", "complianz"); ?></a>
                </li>

                <?php
            }
        }



        function dashboard_third_block()
        {
            ?>
            <div class="cmplz-header-top cmplz-dashboard-text pro">
                <div class="cmplz-dashboard-title"> <?php echo __('Documents', 'complianz'); ?> </div>
            </div>
            <table class="cmplz-dashboard-documents-table cmplz-dashboard-text">
                <?php
                foreach (COMPLIANZ()->config->pages as $type => $page) {

                    if (COMPLIANZ()->document->page_exists($type)) {
                        $link = '<a href="' . get_permalink(COMPLIANZ()->document->get_shortcode_page_id($type)) . '">' . $page['title'] . '</a>';
                        COMPLIANZ()->admin->get_dashboard_element($link, 'success');
                    } elseif (COMPLIANZ()->document->page_required($page)){
                        COMPLIANZ()->admin->get_dashboard_element(sprintf(__("You should create a %s"),$page['title']), 'error');
                    }
                }

                $warnings = COMPLIANZ()->admin->get_warnings(false);
                $warning_types = apply_filters('cmplz_warnings_types', COMPLIANZ()->config->warning_types);

                foreach ($warning_types as $key => $type) {
                    if ($type['type'] === 'general') continue;
                    if (isset($type['region']) && !cmplz_has_region($type['region'])) continue;
                    if (in_array($key, $warnings)) {
                        if (isset($type['label_error'])) COMPLIANZ()->admin->get_dashboard_element($type['label_error'], 'error');
                    } else {
                        if (isset($type['label_ok'])) COMPLIANZ()->admin->get_dashboard_element($type['label_ok'], 'success');
                    }
                }
                do_action('cmplz_documents');
                ?>
            </table>
            <?php do_action('cmplz_documents_footer');

        }


        public function documents(){
            $regions = cmplz_get_regions();
            foreach($regions as $region => $label) {
                $region = COMPLIANZ()->config->regions[$region]['law'];
                $this->get_dashboard_element(sprintf(__('Privacy Statement (%s) - (%spremium%s)', 'complianz'), $region, '<a href="https://complianz.io">', '</a>'), 'error');
            }
        }

        public function documents_footer(){
            ?>
            <div class="cmplz-documents-bottom cmplz-dashboard-text">
                <div class="cmplz-premium-cta"> <?php echo __('Discover all premium benefits', 'complianz'); ?>
                    <a class="button cmplz"
                       href="https://complianz.io" target="_blank"><?php echo __('Read more', 'complianz'); ?>
                        <i class="fa fa-angle-right"></i>
                    </a>
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
                    <div class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </div>
                </a>
            </div>

            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Feature requests', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Need new features or languages? Let us know!', 'complianz'); ?></div>
                <a href="https://complianz.io/contact" target="_blank">
                    <div class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </div>
                </a>
            </div>

            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Documentation', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Check out the docs on complianz.io!', 'complianz'); ?></div>
                <a href="https://complianz.io/documentation/" target="_blank">
                    <div class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </div>
                </a>
            </div>

            <div class="cmplz-footer-block">
                <div class="cmplz-footer-title"><?php echo __('Our blog', 'complianz'); ?></div>
                <div class="cmplz-footer-description"><?php echo __('Stay up to date with the latest news', 'complianz'); ?></div>
                <a href="https://complianz.io/blog" target="_blank">
                    <div class="cmplz-external-btn">
                        <i class="fa fa-angle-right"></i>
                    </div>
                </a>
            </div>

            <?php
        }


        public function get_status_overview()
        {


            ?>

            <div class="cmplz-dashboard-container">

                <?php
                //show an overlay when the wizard is not completed at least once yet

                if (!COMPLIANZ()->wizard->wizard_completed_once()) {
                    ?>
                    <div id="complete_wizard_first_notice">
                        <p>
                            <?php _e("You haven't completed the wizard yet. You should run the wizard at least once to get valid results in the dashboard.", 'complianz') ?>
                            <a class="button cmplz-continue-button"
                               href="<?php echo admin_url('admin.php?page=cmplz-wizard') ?>">
                                <?php _e('Start wizard', 'complianz') ?>
                                <i class="fa fa-angle-right"></i>
                            </a>
                        </p>
                    </div>
                <?php } ?>

                <div class="cmplz-dashboard-header">
                    <div class="cmplz-header-top">
                    </div>
                </div>
                <div class="cmplz-dashboard-content-container">
                    <div class="cmplz-logo">
                        <img src="<?php echo cmplz_url . 'core/assets/images/cmplz-logo.png' ?>"> <?php echo apply_filters('cmplz_logo_extension', __('Free', 'complianz')) ?>
                    </div>
                    <div class="cmplz-completed-text">
                        <div class="cmplz-header-text">


                        </div>
                    </div>
                    <div class="cmplz-dashboard-progress cmplz-dashboard-item">
                        <div class="cmplz-dashboard-progress-top cmplz-dashboard-text">
                            <div class="cmplz-dashboard-top-text">
                                <div class="cmplz-dashboard-title"><?php echo __('Your progress', 'complianz'); ?> </div>
                                <div class='cmplz-dashboard-top-text-subtitle'>
                                    <?php if (COMPLIANZ()->wizard->wizard_percentage_complete() < 100) {
                                        printf(__('Your website is not ready for the %s yet.', 'complianz'),cmplz_supported_laws());
                                    } else {
                                        printf(__('Well done! Your website is ready for the %s.', 'complianz'),cmplz_supported_laws());
                                    } ?>
                                </div>
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
                                    <div>
                                        <a href="<?php echo admin_url('admin.php?page=cmplz-wizard') ?>"
                                           class="button cmplz cmplz-continue-button">
                                            <?php echo __('Continue', 'complianz'); ?>
                                            <i class="fa fa-angle-right"></i></a>
                                    </div>
                                <?php } ?>
                            </div>


                        </div>
                        <table class="cmplz-steps-table cmplz-dashboard-text">
                            <tr><td></td><td><div class="cmplz-dashboard-info"><?php _e('Tasks','complianz')?></div></td></tr>
                            <?php

                            $last_cookie_scan = COMPLIANZ()->cookie->get_last_cookie_scan_date();
                            if (!$last_cookie_scan) {
                                $this->task_count++;
                                $this->get_dashboard_element(sprintf(__('No cookies detected yet', 'complianz'), $last_cookie_scan), 'error');
                            }

                            do_action('cmplz_dashboard_elements_error');

                            $warnings = $this->get_warnings(false);
                            $warning_types = apply_filters('cmplz_warnings_types', COMPLIANZ()->config->warning_types);

                            foreach ($warning_types as $key => $type) {
                                if ($type['type'] === 'document') continue;
                                if (isset($type['region']) && !cmplz_has_region($type['region'])) continue;
                                if (in_array($key, $warnings)) {
                                    if (isset($type['label_error'])) $this->get_dashboard_element($type['label_error'], 'error');
                                }
                            }
                            $warning_count = $this->task_count + count($warnings);
                            if ($warning_count==0){
                                $this->get_dashboard_element(__("Nothing on your to do list", 'complianz'), 'success');
                            }
                            ?>
                            <tr><td></td><td><div class="cmplz-dashboard-info"><?php _e('System status','complianz')?></div></td></tr>

                            <?php

                            $regions = cmplz_get_regions();
                            foreach($regions as $region => $label){
                                $labels[] = COMPLIANZ()->config->regions[$region]['label'];
                                $selected_region = $region;
                            }
                            $labels = implode('/',$labels);

                            $add = '';
//                            if (defined('cmplz_free')) {
//                                if ($selected_region==='us') {
//                                    $add = sprintf(__("To enable EU, %sgo premium%s", 'complianz'), '<a href="https://complianz.io">', '</a>');
//                                } else {
//                                    $add = sprintf(__("To enable US, %sgo premium%s", 'complianz'), '<a href="https://complianz.io">', '</a>');
//
//                                }
//                            }
                            $this->get_dashboard_element(sprintf(__('Your site is configured for the %s.', 'complianz')." ".$add, $labels), 'success');




                            do_action('cmplz_dashboard_elements_success');

                            if (COMPLIANZ()->cookie->site_needs_cookie_warning() && COMPLIANZ()->wizard->wizard_completed_once()) {
                                $this->get_dashboard_element(__('Your site requires a cookie warning, which has been enabled', 'complianz'), 'success');
                            }
                            if (!COMPLIANZ()->cookie->site_needs_cookie_warning()) {
                                $this->get_dashboard_element(__('Your site does not require a cookie warning. No cookie warning has been enabled.', 'complianz'), 'success');
                            }
                            if ($last_cookie_scan) {
                                $this->get_dashboard_element(sprintf(__('Last cookie scan on %s', 'complianz'), $last_cookie_scan), 'success');
                            }

                            foreach ($warning_types as $key => $type) {
                                if ($type['type'] === 'document') continue;
                                if (isset($type['region']) && !cmplz_has_region($type['region'])) continue;
                                if (!in_array($key, $warnings)) {
                                    if (isset($type['label_ok'])) $this->get_dashboard_element($type['label_ok'], 'success');
                                }
                            }

                            ?>

                        </table>
                    </div>

                    <div class="cmplz-dashboard-support cmplz-dashboard-item">
                        <?php do_action("cmplz_dashboard_second_block") ?>
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

        public function dashboard_elements(){

            $this->get_dashboard_element(sprintf(__('The browser setting Do No Track is not respected yet. Upgrade to %spremium%s to make your site DNT compliant', 'complianz'), '<a  target="_blank" href="https://complianz.io">', '</a>'), 'warning');
            $this->task_count++;

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
                    $this->success_message = sprintf(__("Your support request has been received. We will reply shortly. You can track the status of your request at %scomplianz.io%s", "complianz"), '<a target="_blank" href="https://complianz.io/support">', '</a>');

                } else {
                    $this->error_message = __("Something went wrong while submitting the support request", "complianz");

                }
            }
        }

        public function get_dashboard_element($content, $type = 'error')
        {
            $icon = "";
            switch ($type) {
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

            $type = ($type == 'success') ? 'success' : 'error';

            ?>
            <tr class="<?php echo "cmplz-" . $type ?>">
                <td><i class="fa <?php echo $icon ?>"></i></td>
                <td><?php echo $content ?></td>
            </tr>
            <?php
        }

        public function ctb_section_text()
        {

        }


        public function settings()
        {
            ?>
            <div class="wrap cmplz-settings">
                <h1><?php _e("Settings") ?></h1>
                <?php do_action('cmplz_show_message')?>
                <form action="" method="post" enctype="multipart/form-data">


                    <table class="form-table">
                        <?php

                        COMPLIANZ()->field->get_fields('settings');

                        COMPLIANZ()->field->save_button();

                        ?>

                    </table>
                </form>
            </div>
            <?php
        }

        public function script_center()
        {
            ?>
            <div class="wrap cmplz-settings" id="cmplz-wizard">
                <h1><?php _e("Script center") ?></h1>

                <form action="" method="post" class="cmplz-body">

                    <table class="form-table">
                        <tr>
                            <th></th>
                            <td><?php

                                COMPLIANZ()->field->get_fields('wizard', STEP_COOKIES, 4);

                                ?>
                            </td>
                        </tr>
                        <tr><th></th><td><?php COMPLIANZ()->field->save_button();?></td></tr>
                    </table>
                </form>
            </div>
            <?php
        }


        public function cookie_page()
        {
            ?>
            <div class="wrap cookie-warning">
                <h1><?php _e("Cookie warning settings", 'complianz') ?></h1>

                <?php
                if (!COMPLIANZ()->wizard->wizard_completed_once()) {
                    cmplz_notice(__('Please complete the wizard to check if you need a cookie warning.', 'complianz'), 'warning');
                } else {
                    if (!COMPLIANZ()->cookie->site_needs_cookie_warning()) {
                        cmplz_notice(__('Your website does not require a cookie warning, so these settings do not apply.', 'complianz'));
                    } else {
                        cmplz_notice(__('Your website requires a cookie warning, these settings will determine how the popup will look.', 'complianz'));
                    }
                }
                ?>
                <?php //some fields for the cookies categories ?>
                <input type="hidden" name="cmplz_cookie_warning_required_stats" value="<?php echo (COMPLIANZ()->cookie->cookie_warning_required_stats())?>">

                <form id='cookie-settings' action="" method="post">
                    <?php if (cmplz_multiple_regions()){?>
                    <div id="cmplz-region-slider">
                        <label class="switch">
                            <input type="checkbox" <?php echo (cmplz_company_in_eu()) ? "checked": ""?> id="cmplz-region-mode">
                            <div class="slider round">
                                <span class="eu"><?php _e("EU", "complianz")?></span>
                                <span class="us"><?php _e("US", "complianz")?></span>
                            </div>
                        </label>
                    </div>
                    <?php } else { ?>
                        <input type="checkbox" style="display:none" <?php echo (cmplz_has_region('eu')) ? "checked": ""?> id="cmplz-region-mode">
                   <?php }?>
                    <table class="form-table">

                        <?php
                        do_action('cmplz_a_b_testing_section');

                        COMPLIANZ()->field->get_fields('cookie_settings');

                        //now clear the variation again.
                        COMPLIANZ()->field->set_variation_id('');

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
                        <?php _e("Complianz Privacy Suite requires at least PHP version 5.6. Please upgrade your PHP version before continuing.", "complianz"); ?>
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
