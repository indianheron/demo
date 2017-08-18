<?php
/**
 * Defines base methods and properties for programmatically interfacing with LifterLMS Custom Post Types
 * @since  3.0.0
 * @since  3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class LLMS_Post_Model implements JsonSerializable {

	/**
	 * Name of the post type as stored in the database
	 * This will be prefixed (where applicable)
	 * ie: "llms_order" for the "llms_order" post type
	 * @var string
	 * @since  3.0.0
	 */
	protected $db_post_type;

	/**
	 * WP Post ID
	 * @var int
	 * @since 3.0.0
	 */
	protected $id;

	/**
	 * Define this in extending classes
	 * Allows models to use unprefixed post type names for filters and more
	 * ie: "order" for the "llms_order" post type
	 * @var string
	 * @since 3.0.0
	 */
	protected $model_post_type;

	/**
	 * A prefix to add to all meta properties
	 * Child classes can redefine this
	 * @var string
	 * @since 3.0.0
	 */
	protected $meta_prefix = '_llms_';

	/**
	 * Instance of WP_Post
	 * @var obj
	 * @since 3.0.0
	 */
	protected $post;

	/**
	 * Array of meta properties and their property type
	 * @var     array
	 * @since   3.3.0
	 * @version 3.3.0
	 */
	protected $properties = array();

	/**
	 * Constructor
	 * Setup ID and related post property
	 *
	 * @param int|obj    $model   WP post id, instance of an extending class, instance of WP_Post
	 * @param  array     $args    args to create the post, only applies when $model is 'new'
	 * @return  void
	 * @since  3.0.0
	 */
	public function __construct( $model, $args = array() ) {

		if ( 'new' === $model ) {
			$model = $this->create( $args );
			$created = true;
		} else {
			$created = false;
		}

		if ( empty( $model ) ) {
			return;
		}

		if ( is_numeric( $model ) ) {

			$this->id   = absint( $model );
			$this->post = get_post( $this->id );

		} elseif ( is_subclass_of( $model, 'LLMS_Post_Model' ) ) {

			$this->id   = absint( $model->id );
			$this->post = $model->post;

		} elseif ( $model instanceof WP_Post && isset( $model->ID ) ) {

			$this->id   = absint( $model->ID );
			$this->post = $model;

		}

		if ( $created ) {
			$this->after_create();
		}

	}


	/**
	 * Magic Getter
	 * @param  string $key   key to retrieve
	 * @return mixed
	 * @since  3.0.0
	 */
	public function __get( $key ) {

		// force numeric id and prevent filtering on the id
		if ( 'id' === $key ) {

			return absint( $this->$key );

		} // if it's a WP Post Property, grab it from the object we already have and apply appropriate filters
		elseif ( in_array( $key, $this->get_post_properties() ) ) {

			$post_key = 'post_' . $key;

			// ensure post is set globally for filters below
			global $post;
			$temp = $post;
			$post = $this->post;

			switch ( $key ) {

				case 'content':
					$val = wptexturize( $this->post->$post_key );
					$val = convert_chars( $val );
					$val = wpautop( $val );
					$val = shortcode_unautop( $val );
					$val = do_shortcode( $val );
				break;

				case 'excerpt':
					$val = apply_filters( 'get_the_excerpt', $this->post->$post_key );
				break;

				case 'menu_order':
					$val = $this->post->menu_order;
				break;

				case 'title':
					$val = apply_filters( 'the_title', $this->post->$post_key );
				break;

				default:
					$val = $this->post->$post_key;

			}

			// return the original global
			$post = $temp;

		} // regular meta data
		elseif ( ! in_array( $key, $this->get_unsettable_properties() ) ) {

			$val = get_post_meta( $this->id, $this->meta_prefix . $key, true );

		} // invalid or unsettable, just return whatever we have (which might be null)
		else {

			return $this->$key;

		}

		// if we found a valid, apply default llms get get filter and return the value
		if ( isset( $val ) ) {

			$val = $this->scrub( $key, $val );
			return apply_filters( 'llms_get_' . $this->model_post_type . '_' . $key, $val, $this );

		}

		// shouldn't ever get here
		return false;

	}

	/**
	 * Magic Isset
	 * @param  string  $key  check if a key exists in the database
	 * @return boolean
	 * @since  3.0.0
	 */
	public function __isset( $key ) {
		return metadata_exists( 'post', $this->id, $this->meta_prefix . $key );
	}

	/**
	 * Magic Setter
	 * @param string $key  key of the property
	 * @param mixed  $val  value to set the property with
	 * @return  void
	 * @since  3.0.0
	 */
	public function __set( $key, $val ) {
		$this->$key = $val;
	}

	/**
	 * Wrapper for $this-get() which allows translation of the database value before outputting on screen
	 *
	 * Extending classes should define this and translate any possible strings
	 * with a switch statement or something
	 * this will return the untranslated string if a translation isn't defined
	 *
	 * @param    string     $key  key to retrieve
	 * @return   string
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function translate( $key ) {
		$val = $this->get( $key );
		// ******* example *******
		// switch( $key ) {
		// 	case 'example_key':
		// 		if ( 'example-val' === $val ) {
		// 			return translate( 'Example Key', 'lifterlms' );
		// 		}
		// 	break;
		// 	default:
		// 		return $val;
		// }
		// ******* example *******
		return $val;
	}

	/**
	 * Wrapper for the $this->translate() that echos the result rather than returning it
	 * @param    string     $key  key to retrieve
	 * @return   string
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function _e( $key ) {
		echo $this->translate( $key );
	}

	/**
	 * Called immediately after creating / inserting a new post into the database
	 * This stub can be overwritten by child classes
	 * @since    3.0.0
	 * @version  3.0.0
	 * @return  void
	 */
	protected function after_create() {}

	/**
	 * Create a new post of the Instantiated Model
	 * This can be called by instantiating an instance with "new"
	 * as the value passed to the constructor
	 * @param  string  $title   Title to create the post with
	 * @return int    WP Post ID of the new Post on success or 0 on error
	 * @since  3.0.0
	 */
	private function create( $title = '' ) {
		return wp_insert_post( apply_filters( 'llms_new_' . $this->model_post_type, $this->get_creation_args( $title ) ) );
	}

	/**
	 * Clones the Post if the post is cloneable
	 * @return   mixed         WP_Error or array of generator results
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function clone_post() {

		// if post type doesnt support cloning don't proceed
		if ( ! $this->is_exportable() ) {
			return;
		}

		$generator = new LLMS_Generator( $this->toArray() );
		$generator->set_generator( 'LifterLMS/Single' . ucwords( $this->model_post_type ) . 'Cloner' );
		if ( ! $generator->is_error() ) {
			$generator->generate();
		}
		return $generator->get_results();

	}

	/**
	 * Trigger an export download of the given post type
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function export() {

		// if post type doesnt support exporting don't proceed
		if ( ! $this->is_exportable() ) {
			return;
		}

		$title = str_replace( ' ', '-', $this->get( 'title' ) );
		$title = preg_replace( '/[^a-zA-Z0-9-]/', '', $title );

		$filename = apply_filters( 'llms_post_model_export_filename', $title . '_' . current_time( 'Ymd' ), $this );

		header( 'Content-type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$arr = $this->toArray();

		$arr['_generator'] = 'LifterLMS/Single' . ucwords( $this->model_post_type ) . 'Exporter';
		$arr['_source'] = get_site_url();
		$arr['_version'] = LLMS()->version;

		ksort( $arr );

		echo json_encode( $arr );

		die();

	}

	/**
	 * Getter
	 * @param  string $key  property key
	 * @return mixed
	 * @since  3.0.0
	 */
	public function get( $key ) {
		return $this->$key;
	}

	/**
	 * Getter for array values
	 * Ensures that even empty values return an array
	 * @param  string $key  property key
	 * @return array
	 * @since  3.0.0 [<description>]
	 */
	public function get_array( $key ) {
		$val = $this->get( $key );
		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}
		return $val;
	}

	/**
	 * Getter for date strings with optional date format conversion
	 * If no format is supplied, the default format available via $this->get_date_format() will be used
	 * @param  string $key     property key
	 * @param  string $format  any valid date format that can be passed to date()
	 * @return string
	 * @since  3.0.0
	 */
	public function get_date( $key, $format = null ) {
		$format = ( ! $format ) ? $this->get_date_format() : $format;
		$raw = $this->get( $key );
		// only conver the date if we actually have something stored, otherwise we'll return the current date, which we probably aren't expecting
		$date = $raw ? date_i18n( $format, strtotime( $raw ) ) : '';
		return apply_filters( 'llms_get_' . $this->model_post_type . '_' . $key . '_date', $date, $this );
	}

	/**
	 * Retrieve the default date format for the post model
	 * This *can* be overriden by child classes if the post type requires a different default date format
	 * If no format is supplied by the child class, the default WP date & time formats available
	 * via General Settings will be combined and used
	 * @return string
	 * @since  3.0.0
	 */
	protected function get_date_format() {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return apply_filters( 'llms_get_' . $this->model_post_type . '_date_format', $format );
	}

	/**
	 * Retrieve URL for an image associated with the post
	 * Currently only retrieves the featured image if the post type supports it
	 * in the future this will allow retrieval of custom post images as well
	 * @param    string|array   $size  registered image size or a numeric array with width/height
	 * @param    string         $key   currently unused but here for forward compatibility if
	 *                                 additional custom images are added
	 * @return   string                empty string if no image or not supported
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function get_image( $size = 'full', $key = '' ) {
		if ( post_type_supports( $this->db_post_type, 'thumbnail' ) ) {
			$url = get_the_post_thumbnail_url( $this->get( 'id' ), $size );
		}
		return ! empty( $url ) ? $url : '';
	}

	/**
	 * Retrieve the registered Label of the posts current status
	 * @return   string
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function get_status_name() {
		$obj = get_post_status_object( $this->get( 'status' ) );
		return apply_filters( 'llms_get_' . $this->model_post_type . '_status_name', $obj->label );
	}

	/**
	 * Retrieve the Post's post type data object
	 * @return obj
	 * @since  3.0.0
	 */
	public function get_post_type_data() {
		return get_post_type_object( $this->get( 'type' ) );
	}

	/**
	 * Retrieve a label from the post type data object's labels object
	 * @param  string $label key for the label
	 * @return string
	 * @since  3.0.0
	 */
	public function get_post_type_label( $label = 'singular' ) {
		$obj = $this->get_post_type_data();
		if ( property_exists( $obj, 'labels' ) && property_exists( $obj->labels, $label ) ) {
			return $obj->labels->$label;
		}
		return '';
	}

	/**
	 * Getter for price strings with optional formatting options
	 * @param    string $key         property key
	 * @param    array  $price_args  optional array of arguments that can be passed to llms_price()
	 * @param    string $format      optional format conversion method [html|raw|float]
	 * @return   mixed
	 * @since    3.0.0
	 * @version  3.2.7
	 */
	public function get_price( $key, $price_args = array(), $format = 'html' ) {

		$price = $this->get( $key );

		// handle empty or unset values gracefully
		if ( '' === $price ) {
			$price = 0;
		}

		if ( 'html' == $format || 'raw' === $format ) {
			$price = llms_price( $price, $price_args );
			if ( 'raw' === $format ) {
				$price = strip_tags( $price );
			}
		} elseif ( 'float' === $format ) {
			$price = floatval( number_format( $price, get_lifterlms_decimals(), get_lifterlms_decimal_separator(), '' ) );
		} else {
			$price = apply_filters( 'llms_get_' . $this->model_post_type . '_' . $key . '_' . $format, $price, $key, $price_args, $format, $this );
		}

		return apply_filters( 'llms_get_' . $this->model_post_type . '_' . $key . '_price', $price, $key, $price_args, $format, $this );

	}

	/**
	 * An array of default arguments to pass to $this->create()
	 * when creating a new post
	 * This *should* be overridden by child classes
	 * @param  array  $args   args of data to be passed to wp_insert_post
	 * @return array
	 * @since  3.0.0
	 */
	protected function get_creation_args( $args = null ) {

		// allow nothing to be passed in
		if ( empty( $args ) ) {
			$args = array();
		}

		// backwards compat to original 3.0.0 format when just a title was passed in
		if ( is_string( $args ) ) {
			$args = array(
				'post_title' => $args,
			);
		}

		$args = wp_parse_args( $args, array(
			'comment_status' => 'closed',
			'ping_status'	 => 'closed',
			'post_author' 	 => 1,
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_status' 	 => 'draft',
			'post_title'     => '',
			'post_type' 	 => $this->get( 'db_post_type' ),
		) );

		return apply_filters( 'llms_' . $this->model_post_type . '_get_creation_args', $args, $this );
	}

	/**
	 * Get a property's data type for scrubbing
	 * used by $this->scrub() to determine how to scrub the property
	 * @param   string $key  property key
	 * @return  string
	 * @since   3.3.0
	 * @version 3.3.0
	 */
	protected function get_property_type( $key ) {

		$props = $this->get_properties();

		// check against the properties array
		if ( in_array( $key, array_keys( $props ) ) ) {
			$type = $props[ $key ];
		} // default to text
		else {
			$type = 'text';
		}

		return $type;

	}

	/**
	 * Retrieve an array of post properties
	 * These properties need to be get/set with alternate methods
	 * @return array
	 * @since 3.0.0
	 */
	protected function get_post_properties() {
		return apply_filters( 'llms_post_model_get_post_properties', array(
			'author',
			'content',
			'date',
			'excerpt',
			'menu_order',
			'modified',
			'name',
			'status',
			'title',
			'type',
		), $this );
	}

	/**
	 * Retrieve an array of properties defined by the model
	 * @return   array
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function get_properties() {
		return apply_filters( 'llms_post_model_get_post_properties', $this->properties, $this );
	}

	/**
	 * Array of properties which *cannot* be set
	 * If a child class adds any properties which should not be settable
	 * the class should override this property and add their custom
	 * properties to the array
	 * @var array
	 * @since 3.0.0
	 */
	protected function get_unsettable_properties() {
		return apply_filters( 'llms_post_model_get_unsettable_properties', array(
			'db_post_type',
			'id',
			'meta_prefix',
			'model_post_type',
			'post',
		), $this );
	}

	/**
	 * Determine if the associated post is exportable
	 * @return   boolean
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function is_cloneable() {
		return post_type_supports( $this->db_post_type, 'llms-clone-post' );
	}

	/**
	 * Determine if the associated post is exportable
	 * @return   boolean
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function is_exportable() {
		return post_type_supports( $this->db_post_type, 'llms-export-post' );
	}

	/**
	 * Format the object for json serialization
	 * encodes the results of $this->toArray()
	 * @return   array
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function jsonSerialize() {
		return apply_filters( 'llms_post_model_json_serialize', $this->toArray(), $this );
	}

	/**
	 * Scrub field according to it's type
	 * This is automatically called by set() method before anything is actually set
	 *
	 * @param  string $key  property key
	 * @param  mixed  $val  property value
	 * @return mixed
	 * @since  3.0.0
	 */
	protected function scrub( $key, $val ) {

		switch ( $key ) {

			case 'author':
			case 'menu_order':
				$type = 'absint';
			break;

			case 'content':
				$type = 'html';
			break;

			case 'excerpt':
			case 'title':
				$type = 'text';
			break;

			default:
				$type = apply_filters( 'llms_get_' . $this->model_post_type . '_property_type', $this->get_property_type( $key ), $this );

		}

		return apply_filters( 'llms_scrub_' . $this->model_post_type . '_field_' . $key, $this->scrub_field( $val, $type ), $this, $key, $val );

	}

	/**
	 * Scrub fields according to datatype
	 * @param  mixed  $val   property value to scrub
	 * @param  string $type  data type
	 * @return mixed
	 * @since  3.0.0
	 */
	protected function scrub_field( $val, $type ) {

		if ( 'html' !== $type && 'array' !== $type ) {
			$val = strip_tags( $val );
		}

		switch ( $type ) {

			case 'absint':
				$val = absint( $val );
			break;

			case 'array':
				$val = ( array ) $val;
			break;

			case 'bool':
			case 'boolean':
				$val = boolval( $val );
			break;

			case 'float':
				$val = floatval( $val );
			break;

			case 'html':
				$val = wp_kses_post( $val );
			break;

			case 'int':
				$val = intval( $val );
			break;

			case 'yesno':
				$val = 'yes' === $val ? 'yes' : 'no';
			break;

			case 'text':
			case 'string':
			default:
				$val = sanitize_text_field( $val );

		}

		return $val;

	}

	/**
	 * Setter
	 * @param  string $key  key of the property
	 * @param  mixed  $val  value to set the property with
	 * @return boolean      true on success, false on error or if the submitted value is the same as what's in the database
	 */
	public function set( $key, $val ) {

		$val = $this->scrub( $key, $val );

		// update WordPress Post Properties using the wp_insert_post() function
		if ( in_array( $key, $this->get_post_properties() ) ) {

			$post_key = 'post_' . $key;

			switch ( $key ) {

				case 'content':
					$val = apply_filters( 'content_save_pre', $val );
				break;

				case 'excerpt':
					$val = apply_filters( 'excerpt_save_pre', $val );
				break;

				case 'menu_order':
					$post_key = 'menu_order';
				break;

				case 'title':
					$val = apply_filters( 'title_save_pre', $val );
				break;

			}

			$args = array(
				'ID' => $this->get( 'id' ),
			);

			$args[ $post_key ] = apply_filters( 'llms_set_' . $this->model_post_type . '_' . $key, $val, $this );

			if ( wp_update_post( $args ) ) {
				return true;
			} else {
				return false;
			}

		} // if the property is not unsettable, update the meta value
		elseif ( ! in_array( $key, $this->get_unsettable_properties() ) ) {

			$u = update_post_meta( $this->id, $this->meta_prefix . $key, apply_filters( 'llms_set_' . $this->model_post_type . '_' . $key, $val, $this ) );
			if ( is_numeric( $u ) || true === $u ) {
				return true;
			} else {
				return false;
			}

		} // we have a problem...
		else {

			return false;

		}

	}

	/**
	 * Coverts the object to an associative array
	 * Any property returned by $this->get_properties() will be retrieved
	 * via $this->get() and added to the array
	 *
	 * Extending classes can add additonal properties to the array
	 * by overriding $this->toArrayAfter()
	 *
	 * This function is also utilzied to serialize the object to json
	 *
	 * @return   array
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function toArray() {

		$arr = array(
			'id' => $this->get( 'id' ),
		);

		$props = array_merge( array_keys( $this->get_properties() ), $this->get_post_properties() );

		foreach ( $props as $prop ) {
			$arr[ $prop ] = $this->get( $prop );
		}

		// add the featured image if the post type supports it
		if ( post_type_supports( $this->db_post_type, 'thumbnail' ) ) {
			$arr['featured_image'] = $this->get_image( 'full' );
		}

		// expand author
		if ( ! empty( $arr['author'] ) ) {
			$u = new WP_User( $arr['author'] );
			$arr['author'] = array(
				'descrpition' => $u->description,
				'email' => $u->user_email,
				'first_name' => $u->first_name,
				'id' => $u->ID,
				'last_name' => $u->last_name,
			);
		}

		// allow extending classes to add properties easily without overridding the class
		$arr = $this->toArrayAfter( $arr );

		ksort( $arr ); // because i'm anal...

		return apply_filters( 'llms_post_model_to_array', $arr, $this );

	}

	/**
	 * Called before data is sorted and returned by $this->toArray()
	 * Extending classes should override this data if custom data should
	 * be added when object is converted to an array or json
	 * @param    array     $arr   array of data to be serialized
	 * @return   array
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	protected function toArrayAfter( $arr ) {
		return $arr;
	}

}
