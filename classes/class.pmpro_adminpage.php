<?php

/**
 * Created by PhpStorm.
 * User: sjolshag
 * Date: 5/19/16
 * Time: 12:09 PM
 */
class pmpro_adminpage {

	/** @var    string  $admin_header       The path/file containing the header for the admin page */
	private $admin_header;
	private $admin_footer;

	public function __construct() {

		$this->load_actions();
		$this->load_filters();
	}

	private function load_actions() {

		add_action('pmpro_adminpage_show', array( $this, 'showAdminPage'));

	}

	private function load_filters() {

	}

	public function showAdminPage() {

		// Load header for admin page
		if (file_exists( $this->admin_header ) ) {
			include $this->admin_header;
		}

		do_action('pmpro_adminpage_top_content');
		do_action('pmpro_adminpage_top_settings');

		do_action('pmpro_adminpage_middle_content');
		do_action('pmpro_adminpage_middle_settings');

		
		// Load footer for admin page
		if (file_exists( $this->admin_footer )) {
			include $this->admin_footer;
		}
	}
}