<?php
/*100% match*/
defined('ABSPATH') or die("you do not have access to this page!");

if ( ! class_exists( 'cmplz_cookie_blocker' ) ) {
    class cmplz_cookie_blocker
    {
        private static $_this;
        public $script_tags = array();
        public $iframe_tags = array();

        function __construct()
        {

            if (isset(self::$_this))
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl'), get_class($this)));

            self::$_this = $this;

        }

        static function this()
        {
            return self::$_this;

        }


        /**
         * Apply the mixed content fixer.
         *
         * @since  1.0
         *
         * @access public
         *
         */

        public function filter_buffer($buffer)
        {
            $buffer = $this->replace_tags($buffer);
            return $buffer;
        }

        /**
         * Start buffering the output
         *
         * @since  1.0
         *
         * @access public
         *
         */

        public function start_buffer()
        {
            ob_start(array($this, "filter_buffer"));
        }

        /**
         * Flush the output buffer
         *
         * @since  1.0
         *
         * @access public
         *
         */

        public function end_buffer()
        {
            if (ob_get_length()) ob_end_flush();
        }

        /**
         * Just before the page is sent to the visitor's browser, remove all tracked third party scripts
         *
         * @since  1.0
         *
         * @access public
         *
         */

        public function replace_tags($output)
        {
            if (strpos($output, '<html') === false):
                return $output;
            elseif (strpos($output, '<html') > 200):
                return $output;
            endif;

            /*
             * Get script tags, and add custom user scrripts
             *
             * */

            $known_script_tags = COMPLIANZ()->config->script_tags;
            $custom_scripts = cmplz_strip_spaces(cmplz_get_value('thirdparty_scripts'));
            if (!empty($custom_scripts) && strlen($custom_scripts)>0){
                $custom_scripts = explode(',', $custom_scripts);
                $known_script_tags = array_merge($known_script_tags ,  $custom_scripts);
            }

            /*
             * Get iframe tags, and add custom user iframes
             *
             * */

            $known_iframe_tags = COMPLIANZ()->config->iframe_tags;
            $custom_iframes = cmplz_strip_spaces(cmplz_get_value('thirdparty_iframes'));
            if (!empty($custom_iframes) && strlen($custom_iframes)>0){
                $custom_iframes = explode(',', $custom_iframes);
                $known_iframe_tags = array_merge($known_iframe_tags ,  $custom_iframes);
            }

            //not meant as a "real" URL pattern, just a loose match for URL type strings.
            $url_pattern = '([\w.,@?^=%&:\/~+#-]*[\w@?^=%&\/~+#-]?)';

            /*
             * Handle iframes from third parties
             *
             *
             * */

            $iframe_pattern = '/<(iframe)[^>].*?src=[\'"](http:\/\/|https:\/\/|\/\/)'.$url_pattern.'[\'"].*?>/i';
            if (preg_match_all($iframe_pattern, $output, $matches, PREG_PATTERN_ORDER)) {
                foreach($matches[2] as $key => $match){
                    $total_match = $matches[0][$key];
                    $iframe_src = $matches[2][$key].$matches[3][$key];
                    if (strpos($iframe_src, 'youtube.com/embed/')!==false){
                        $output = str_replace('youtube.com/embed/', 'youtube-nocookie.com/embed/', $output);
                    } elseif ($this->strpos_arr($iframe_src, $known_iframe_tags) !== false) {
                        $new = $total_match;
                        $new = str_replace('<iframe', '<iframe data-src-cmplz="'.$iframe_src.'"', $new);
                        $new = $this->remove_src($new);
                        $new = $this->add_class($new, 'iframe', 'cmplz-iframe');
                        $output = str_replace($total_match, $new, $output);
                    }
                }
            }

            /*
             * Handle scripts from third parties
             *
             *
             * */

            $script_pattern = '/(<script.*?>)(\X*?)<\/script>/i';
            if (preg_match_all($script_pattern, $output, $matches, PREG_PATTERN_ORDER)) {
                foreach($matches[1] as $key => $script_open){
                    if (strpos($script_open,'cmplz-native')!==FALSE) continue;
                    $total_match = $matches[0][$key];
                    $content = $matches[2][$key];
                    if (!empty($content)) {
                        $key = $this->strpos_arr($content, $known_script_tags);
                        //if it's google analytics, and it's not anonymous or from complianz, remove it.
                        if ($known_script_tags[$key] == 'www.google-analytics.com/analytics.js' || $known_script_tags[$key] == 'google-analytics.com/ga.js') {
                            if (strpos($content, 'anonymizeIp') !== FALSE) continue;
                        }
                        if ($key !== false) {
                            $new = $total_match;
                            $new = $this->add_class($new, 'script', 'cmplz-script');

                            $new = $this->set_javascript_to_plain($new);
                            $output = str_replace($total_match, $new, $output);
                        }
                    }

                    //when script contains src
                    $script_src_pattern = '/<script [^>]*?src=[\'"](http:\/\/|https:\/\/|\/\/)'.$url_pattern.'[\'"].*?>/i';
                    if (preg_match_all($script_src_pattern, $total_match, $src_matches, PREG_PATTERN_ORDER)) {

                        foreach ($src_matches[2] as $src_key => $script_src) {

                            $script_src = $src_matches[1][$src_key].$src_matches[2][$src_key];
                            if ($this->strpos_arr($script_src, $known_script_tags) !== false){
                                $new = $total_match;
                                $new = $this->add_class($new, 'script', 'cmplz-script');
                                $new = $this->set_javascript_to_plain($new);

                                $output = str_replace($total_match, $new, $output);
                            }
                        }
                    }
                }
            }

            //add a marker so we can recognize if this function is active on the front-end
            $output = str_replace("<body ", '<body data-cmplz=1 ', $output);
            return $output;
        }

        private function strpos_arr($haystack, $needle)
        {
            if (!is_array($needle)) $needle = array($needle);
            foreach ($needle as $key => $what) {
                if (($pos = strpos($haystack, $what)) !== false) return $key;
            }
            return false;
        }

        private function set_javascript_to_plain($script){
            if (strpos($script, 'type')===false) {
                $script = str_replace("<script", '<script type="text/plain"', $script);
            } else {
                $pattern = '/type=[\'|\"]text\/javascript[\'|\"]/i';
                $script = preg_replace($pattern, 'type="text/plain"', $script);
            }
            return $script;
        }

        private function remove_src($script){
            $pattern = '/src=[\'"](http:\/\/|https:\/\/)([\w.,@?^=%&:\/~+#-]*[\w@?^=%&\/~+#-]?)[\'"]/i';
            $script = preg_replace($pattern, '', $script);
            return $script;
        }

        private function add_class($html, $el, $class){
            if (strpos($html,'class' )===false){
                $html = str_replace("<$el", '<'.$el.' class="'.$class.'"', $html);
            }
            return $html;
        }
    }
}



