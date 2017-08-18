<?php
/**
* Frontend scripts class
* @since    1.0.0
* @version  3.4.1
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Frontend_Assets {

	public static $min = '.min';

	/**
	 * Inline script ids that have been enqueued
	 * @var  array
	 */
	private static $enqueued_inline_scripts = array();

	/**
	 * Array of inline scripts to be output in the header / footer respectively
	 * @var  array
	 */
	private static $inline_scripts = array(
		'header' => array(),
		'footer' => array(),
	);

	/**
	 * Initializer
	 * Replaces non-static __construct() from 3.4.0 & lower
	 * @return   void
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_header_scripts' ) );
		add_action( 'wp_footer', array( __CLASS__, 'output_footer_scripts' ) );
	}

	/**
	 * Enqueue an inline script
	 * @param    string     $id        unique id for the script, used to prevent duplicates
	 * @param    string     $script    JS to enqueue, do not add <script> tags!
	 * @param    string     $where     where to enqueue, in the header or footer
	 * @param    float      $priority  enqueue priority
	 * @return   boolean
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function enqueue_inline_script( $id, $script, $where = 'footer', $priority = 10 ) {

		// dupcheck
		if ( self::is_inline_script_enqueued( $id ) ) {
			return false;
		}

		// retrieve the current array of scripts
		$scripts = self::get_inline_scripts( $where );

		$priority = ( string ) $priority;

		// if something already exist at the priority, increment until we can save it
		while ( isset( $scripts[ $priority ] ) ) {

			$priority = ( float ) $priority;
			$priority = $priority + 0.01;
			$priority = ( string ) $priority;

		}

		// add the script to the array
		$scripts[ $priority ] = $script;

		// add it to the array of enqueued scripts
		self::$enqueued_inline_scripts[] = $id;

		ksort( $scripts );

		// save updated array
		self::$inline_scripts[ $where ] = $scripts;

		return true;

	}

	/**
	 * Output the inline PW Strength meter script
	 * @return   void
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function enqueue_inline_pw_script() {
		self::enqueue_inline_script(
			'llms-pw-strength',
			'window.LLMS.PasswordStrength = window.LLMS.PasswordStrength || {}; window.LLMS.PasswordStrength.get_minimum_strength = function() { return "' . llms_get_minimum_password_strength() . '"; }',
			'footer',
			15
		);
	}

	/**
	 * Enqueue Styles
	 * @since   1.0.0
	 * @version 3.4.1
	 */
	public static function enqueue_styles() {

		global $post_type;

		wp_enqueue_style( 'chosen-styles', plugins_url( '/assets/chosen/chosen' . LLMS_Frontend_Assets::$min . '.css', LLMS_PLUGIN_FILE ) );
		wp_enqueue_style( 'lifterlms-styles', plugins_url( '/assets/css/lifterlms' . LLMS_Frontend_Assets::$min . '.css', LLMS_PLUGIN_FILE ) );

		if ( 'llms_my_certificate' == $post_type || 'llms_certificate' == $post_type ) {
			wp_enqueue_style( 'certificates', plugins_url( '/assets/css/certificates' . LLMS_Frontend_Assets::$min . '.css', LLMS_PLUGIN_FILE ) );
		}

	}

	/**
	 * Enqueue Scripts
	 * @since   1.0.0
	 * @version 3.4.1
	 */
	public static function enqueue_scripts() {

		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-slider' );
		wp_enqueue_script( 'chosen-jquery', plugins_url( 'assets/chosen/chosen.jquery' . LLMS_Frontend_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array( 'jquery' ), '', true );
		wp_enqueue_script( 'collapse', plugins_url( 'assets/js/vendor/collapse.js', LLMS_PLUGIN_FILE ) );
		wp_enqueue_script( 'transition', plugins_url( 'assets/js/vendor/transition.js', LLMS_PLUGIN_FILE ) );

		wp_register_script( 'llms-jquery-matchheight', plugins_url( 'assets/js/vendor/jquery.matchHeight.js', LLMS_PLUGIN_FILE ), array( 'jquery' ), '', true );
		if ( is_course() || is_membership() || is_lesson() || is_memberships() || is_courses() ) {
			wp_enqueue_script( 'llms-jquery-matchheight' );
		}

		/**
		 * @todo  this is currently being double registered and enqueued by LLMS_Ajax
		 *        fix it ffs...
		 */
		wp_register_script( 'llms', plugins_url( '/assets/js/llms' . LLMS_Frontend_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array( 'jquery' ), '', true );
		wp_enqueue_script( 'llms' );

		wp_enqueue_script( 'llms-ajax', plugins_url( '/assets/js/llms-ajax' . LLMS_Frontend_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array( 'jquery' ), '', true );
		//wp_enqueue_script( 'llms-quiz', plugins_url(  '/assets/js/llms-quiz' . LLMS_Frontend_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array('jquery'), '', TRUE);
		wp_enqueue_script( 'llms-form-checkout', plugins_url( '/assets/js/llms-form-checkout' . LLMS_Frontend_Assets::$min . '.js', LLMS_PLUGIN_FILE ), array( 'jquery' ), '', true );

		if ( ( is_llms_account_page() || is_llms_checkout() ) && 'yes' === get_option( 'lifterlms_registration_password_strength' ) ) {
			wp_enqueue_script( 'password-strength-meter' );
			self::enqueue_inline_pw_script();
		}

		self::enqueue_inline_script(
			'llms-ajaxurl',
			'window.llms = window.llms || {};window.llms.ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";'
		);
		self::enqueue_inline_script(
			'llms-LLMS-obj',
			'window.LLMS = window.LLMS || {};'
		);
		self::enqueue_inline_script(
			'llms-l10n',
			'window.LLMS.l10n = window.LLMS.l10n || {}; window.LLMS.l10n.strings = ' . LLMS_l10n::get_js_strings( true ) . ';'
		);

	}

	/**
	 * Retrieve inline scripts
	 * @param    string     $where  header or footer, if none provided both will be returned
	 * @return   array
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	private static function get_inline_scripts( $where = null ) {

		$scripts = self::$inline_scripts;

		if ( isset( $scripts[ $where ] ) ) {
			$scripts = $scripts[ $where ];
		}

		return apply_filters( 'llms_frontend_assets_inline_scripts', $scripts );

	}

	/**
	 * Determine if an inline script has already been enqueued
	 * @param    string     $id  id of the inline script
	 * @return   boolean
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function is_inline_script_enqueued( $id ) {
		return in_array( $id, self::$enqueued_inline_scripts );
	}

	/**
	 * Output inline scripts
	 * @param    string     $where  which set of scripts to output [header|footer]
	 * @return   void
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	private static function output_inline_scripts( $where ) {

		$scripts = self::get_inline_scripts( $where );

		// bail if no scripts
		if ( ! $scripts ) {
			return;
		}

		echo '<script id="llms-inline-' . $where . 'scripts" type="text/javascript">';
		foreach ( $scripts as $script ) {
			echo $script;
		}
		echo '</script>';

	}

	/**
	 * Output inline scripts to the footer
	 * @return   void
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function output_footer_scripts() {
		self::output_inline_scripts( 'footer' );
	}

	/**
	 * Output inline scripts to the header
	 * @return   void
	 * @since    3.4.1
	 * @version  3.4.1
	 */
	public static function output_header_scripts() {
		self::output_inline_scripts( 'header' );
	}

}

return LLMS_Frontend_Assets::init();
