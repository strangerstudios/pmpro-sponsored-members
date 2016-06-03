<?php
/**
 * Copyright (c) 2016 - Stranger Studios (Jason Coleman). ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 **/

if (WP_DEBUG) {
    error_log("Attempting to load admin page for Sponsored Members");
}

add_action('init', array(new pmpro_SponsoredAdminPage(), 'menu_init'));

class pmpro_SponsoredAdminPage
{

    /** @var        string $page_content The content (HTML) for the page */
    private $page_content;

    /** @var    string $admin_header The path/file containing the header for the admin page */
    protected $admin_header;

    /** @var    string $admin_footer The path/file containing the footer for the admin page */
    protected $admin_footer;

    /** @var    object $menu_handle The handle to access the submenu for the Sponsored Members admin page */
    protected $menu_handle;

    /** @var    pmpro_SponsoredAdminPage $instance The instance of this class */
    static $instance = null;

    protected $page_name;

    protected $ext;

    protected $location;

    public function __construct()
    {

        if (null === self::$instance) {
            self::$instance = $this;
        }

    }

    public function menu_init()
    {

        if (WP_DEBUG) {
            error_log("Loading filters & action hooks for sponsored admin page");
        }

        $this->admin_header = apply_filters('pmpro_admin_path_to_header', PMPRO_DIR . "/adminpages/admin_header.php");
        $this->admin_footer = apply_filters('pmpro_admin_path_to_footer', PMPRO_DIR . "/adminpages/admin_footer.php");

        // set the page name & handle cases
        $this->page_name = apply_filters('pmpro_admin_page_name', 'sponsoredmembers');
        $this->page_name = apply_filters("pmpro_admin_page_name_{$this->page_name}", $this->page_name);

        $this->location = apply_filters("pmpro_admin_{$this->page_name}_page_location", 'local');
        $this->ext = apply_filters("pmpro_admin_{$this->page_name}_page_ext", 'php');

        add_filter('pmpro_adminpages_custom_template_path', array($this, 'set_template_path'), 10, 5);

        // add_action("load-{$this->menu_handle}", array( $this, 'showSettings'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_menu', array($this, 'load_admin_menu'));

        add_action('wp_ajax_pmprosm_save_map', array($this, 'save_settings'));
        add_action('wp_ajax_nopriv_pmprosm_save_map', array($this, 'unprivileged'));
        // add_action( 'admin_init', array( $this, 'create_settings_page' ) );

        // add_action('admin_init', array( $this, 'register_settings') );
    }

    public function unprivileged()
    {
        wp_send_json_error('Error: Access denied');
    }

    public function save_settings()
    {
        if (WP_DEBUG) {
            error_log("Settings AJAX call for Save operation");
        }

        check_ajax_referer('pmprosm', 'pmprosm_nonce');

        if (WP_DEBUG) {
            error_log("We're permitted to save the settings");
        }

        $main_levels = isset($_REQUEST['main_levels']) ? $this->sanitize($_REQUEST['main_levels']) : array();
        $sponsored_levels = isset($_REQUEST['sponsored_levels']) ? $this->sanitize($_REQUEST['sponsored_levels']) : array();
        $seats = isset($_REQUEST['seats']) ? $this->sanitize($_REQUEST['seats']) : array();
        $max_seats = isset($_REQUEST['max_seats']) ? $this->sanitize($_REQUEST['max_seats']) : array();
        $seat_cost = isset($_REQUEST['seat_cost']) ? $this->sanitize($_REQUEST['seat_cost']) : array();

        $can_delete = isset($_REQUEST['sponsor_delete']) && $_REQUEST['sponsor_delete'] == 1 ? true : false;

        $level_map = array();

        // build the main level to sponsored level configuration array
        foreach ($main_levels as $key => $level_id) {

            // skip if not configured (-1 value)
            if (-1 == $level_id) {
                continue;
            }

            // clean up any use of the 'empty' sponsored level entry
            foreach( $sponsored_levels[$key] as $k => $lid ) {
                if (-1 == $lid) {
                    unset($sponsored_levels[$key][$k]);
                }
            }

            // skip if no sponsored levels are defined for this entry
            if (empty( $sponsored_levels[$key] ) ) {
                continue;
            }

            // save value(s), unless they're empty or -1
            $level_map[$level_id] = array();
            $level_map[$level_id]['main_level_id'] = $level_id;

            $level_map[$level_id]['sponsored_level_id'] = ( !empty($sponsored_levels[$key]) ? $sponsored_levels[$key] : 0 );
            $level_map[$level_id]['seats'] = !empty($seats[$key]) ? $seats[$key] : 0;
            $level_map[$level_id]['max_seats'] = !empty($max_seats[$key]) ? $max_seats[$key] : 0;
            $level_map[$level_id]['seat_cost'] = !empty($seat_cost[$key]) ? $seat_cost[$key] : 0;
        }

        if (WP_DEBUG) {
            error_log("Settings array: " . print_r($level_map, true));
        }

        // set sponsor options (privileges)
        $options = get_option('pmprosm_settings', array());
        $options['sponsor_can_delete'] = $can_delete;

        // attempt to save the level map
        update_option('pmprosm_level_map', $level_map, 'no' );
        update_option('pmprosm_settings', $options, 'no');
        wp_send_json_success();
    }

    /**
     * Sanitize supplied field value(s) depending on data type
     *
     * @param $field - The data to sanitize
     * @return array|int|string
     */
    public function sanitize($field)
    {

        if (!is_numeric($field)) {

            if (is_array($field)) {

                foreach ($field as $key => $val) {
                    $field[$key] = $this->sanitize($val);
                }
            }

            if (is_object($field)) {

                foreach ($field as $key => $val) {
                    $field->{$key} = $this->sanitize($val);
                }
            }

            if ((!is_array($field)) && ctype_alpha($field) ||
                ((!is_array($field)) && strtotime($field)) ||
                ((!is_array($field)) && is_string($field))
            ) {

                $field = sanitize_text_field($field);
            }

        } else {

            if (is_float($field + 1)) {

                $field = sanitize_text_field($field);
            }

            if (is_int($field + 1)) {

                $field = intval($field);
            }
        }

        return $field;
    }

    /**
     * Add the adminpage for the plugin to the list of paths for the PMPro Templates
     *
     * @param array $defaults Existing paths to template files
     * @param string $page_name Name of the template file/page to load
     * @param string $type Type of file ('pages', 'adminpages', 'email', etc).
     * @param string $where 'local' or 'url'
     * @param string $ext File extension for the template page/file to load
     *
     * @return array
     */
    public function set_template_path($defaults, $page_name, $type, $where, $ext)
    {

        $defaults[] = PMPROSM_DIR . "{$type}/{$page_name}.{$ext}";
        return $defaults;
    }

    public function enqueue()
    {

        wp_register_script('pmprosm-admin', PMPROSM_URL . "js/pmpro-sponsored-members-admin.js", array('jquery', 'select2'), PMPROSM_VER);

        wp_localize_script('pmprosm-admin', 'pmprosm',
            array(
                'variables' => array(
                    'timeout' => apply_filters('pmprosm_ajax_timeout', 5000)
                )
            )
        );

        wp_enqueue_script('pmprosm-admin');
        wp_enqueue_script('select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', array('jquery'), '4.0.3');

        wp_enqueue_style('pmprosm-admin', PMPROSM_URL . 'css/pmprosm-admin.css', null, PMPROSM_VER);
        wp_enqueue_style('select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css', null, '4.0.3');
    }

    public function create_settings_page()
    {

        // load the page header
        require_once $this->admin_header;

        echo pmpro_loadTemplate('sponsoredmembers', 'local', 'adminpages', 'php');

        // load the page footer
        require_once $this->admin_footer;

    }

    /*
        public function register_settings() {

            register_setting(' pmpro_sponsoredmembers', 'pmpro_sponsoredsettings' );

            add_settings_section(
                'pmpro_sponsoredmembers_section',
                __("Main level (for sponsor) to sponsored level", "pmpro_sponsored_members"),
                array( $this, 'section_callback' ),
                'pmpro_sponsoredmembers'
            );

            add_settings_field(
                'pmpro_mainlevel_select',
                __("Main level", "pmpro_sponsored_members"),
                array( $this, 'mainlevel_select' ),
                'pmpro_sponsoredmembers',
                'pmpro_sponsoredmembers_section'
            );

            add_settings_field(
                'pmpro_sponsoredlevel_select',
                __("Sponsored level", "pmpro_sponsored_members"),
                array( $this, 'sponsoredlevel_select' ),
                'pmpro_sponsoredmembers',
                'pmpro_sponsoredmembers_section'
            );

            add_settings_field(
                'pmpro_sponsored_seats_input',
                __("Seats", "pmpro_sponsored_members"),
                array( $this, 'sponsored_seats_input' ),
                'pmpro_sponsoredmembers',
                'pmpro_sponsoredmembers_section'
            );

            add_settings_section(
                'pmpro_sponsoreduser_section',
                __("Sponsor settings", "pmpro_sponsored_members"),
                array( $this, 'sponsor_settings_callback' ),
                'pmpro_sponsoredmembers'
            );

            add_settings_field(
                'pmpro_sponsoreduser_checkbox',
                __("Can delete user accounts", "pmpro_sponsored_members"),
                array( $this, 'sponsoreduser_checkbox' ),
                'pmpro_sponsoredmembers',
                'pmpro_sponsoreduser_section'
            );

        }

        public function sponsoreduser_checkbox() {

            $options = get_option( 'pmpro_sponsoredsettings' );
            ?>
            <div class="pmprosm-ckbox">
                <input type="checkbox" class="pmprosm-checkbox" name="pmpro_sponsoredsettings[pmpro_sponsoreduser_checkbox]" <?php checked( $options['pmpro_sponsoreduser_checkbox'], 1); ?>>
                <label><i></i></label>
            </div>
            <?php
        }

        public function sponsor_settings_callback() {

        }

        public function sponsored_seats_input() {

            $options = get_option( 'pmpro_sponsoredsettings' );
            ?>
            <input type="number" name="pmpro_sponsoredsettings[pmpro_sponsored_seats_input][]" value="<?php echo $options['pmpro_sponsored_seats_input_0']; ?>" class="pmprsm-admin-input" /><?php
        }

        public function sponsoredlevel_select() {

            $options = get_option( 'pmpro_sponsoredsettings' );
            $levels = pmpro_getAllLevels();

            ?>
            <select name="pmpro_sponsoredsettings[pmpro_mainlevel_select][]" class="pmprosm-admin-select">
                <option value="-1" <?php selected( $options['pmpro_mainlevel_select_0'], -1); ?>> --- </option><?php
                foreach( $levels as $level ) { ?>
                    <option value="<?php echo $level->id; ?>" <?php selected( $options['pmpro_mainlevel_select_0'], $level->id ); ?>><?php echo $level->name; ?></option><?php
                } ?>
            </select>
            <?php
        }

        public function mainlevel_select() {

            $options = get_option( 'pmpro_sponsoredsettings' );
            $levels = pmpro_getAllLevels(); ?>
            <select name="pmpro_sponsoredsettings[pmpro_sponsoredlevel_select][]" class="pmprosm-admin-select">
                <option value="-1" <?php in_array(-1, $options['pmpro_sponsoredlevel_select']) ? 'selected="selected"' : null; ?>> --- </option><?php
                foreach( $levels as $level ) { ?>
                    <option value="<?php echo $level->id; ?>" <?php selected( $options['pmpro_sponsoredlevel_select_0'], $level->id ); ?>><?php echo $level->name; ?></option><?php
                } ?>
            </select>
            <?php
        }

        public function showSettings() {

            if (WP_DEBUG) {
                error_log("Loading showSettings for Sponsored Members settings page");
            }

            require $this->admin_header; ?>

            <form action="options-general.php" method="post">
            <h2><?php _e("Settings for Sponsored Memberships Add-on", "pmpro_sponsored_members"); ?></h2><?php
            settings_fields('pmpro_sponsoredmembers');
            do_settings_sections('pmpro_sponsoredmembers');
            submit_button();
            ?>
            </form><?php

            require $this->admin_footer;
        }
    */
    public function load_admin_menu()
    {

        /**
         * add_options_page(
         * __( "Sponsored Members", "pmpro_sponsored_members" ),
         * __( "Sponsored Members", "pmpro_sponsored_members" ),
         * 'manage_options',
         * 'pmpro-sponsorsettings',
         * array( $this, 'showSettings' )
         * );
         */

        $this->menu_handle = add_submenu_page(
            'pmpro-membershiplevels',
            __("Sponsored Members", "pmpro_sponsored_members"),
            __("Sponsored Members", "pmpro_sponsored_members"),
            'pmpro_discountcodes', 'pmpro-sponsorsettings',
            array($this, 'create_settings_page')
        );

    }
}