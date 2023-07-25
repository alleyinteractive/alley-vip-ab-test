<?php
/**
 * Alley VIP A/B Test: Test Abstract Class.
 *
 * @package Alley_VIP_AB_Test
 */

namespace Alley_VIP_AB_Test;

use Automattic\VIP\Cache\Vary_Cache;

/**
 * An abstract class that establishes a starting point for an A/B test.
 *
 * @package Alley_VIP_AB_Test
 */
abstract class Test {
	/**
	 * Instances of child classes.
	 *
	 * @var array
	 */
	protected static $instances = [];

	/**
	 * The name of the cache group for a test. Override this in your tests.
	 *
	 * @var string
	 */
	protected $cache_group = '';

	/**
	 * The name of group segment A for a test. Override this in your tests.
	 *
	 * @var string
	 */
	protected $a_group_key = '';

	/**
	 * The name of group segment B for a test. Override this in your tests.
	 *
	 * @var string
	 */
	protected $b_group_key = '';

	/**
	 * Test group segment selected for the current user.
	 *
	 * @var string
	 */
	protected $user_group = '';

	/**
	 * Construct the object.
	 */
	private function __construct() {
		if ( empty( $this->cache_group ) || empty( $this->a_group_key ) || empty( $this->b_group_key ) ) {
			$message = 'You must set a cache group as well as group keys for class ' . get_called_class();
			wp_die( esc_html( $message ) );
		}

		// Register the cache group.
		Vary_Cache::register_group( $this->cache_group );

		// Only hook if this is a front-end request.
		if ( ! is_admin() && ! wp_doing_cron() && ! wp_doing_ajax() && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) ) {
			add_action( 'init', [ $this, 'vary_cache' ], 99 );
		}
	}

	/**
	 * Return an instance of a child class.
	 *
	 * @return Test
	 */
	public static function get_instance() {
		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
			self::$instances[ $class ]->setup();
		}
		return self::$instances[ $class ];
	}

	/**
	 * Set up the class. The method is optional in extending classes.
	 * It runs when you create a test instance.
	 */
	protected function setup() {}

	/**
	 * Override with functionality to set the group for the current user.
	 * Its ultimate job is to set $this->user_group. This parent class does the rest.
	 *
	 * @param array $data Optional array of data that you may want to use when setting a group.
	 */
	abstract protected function set_user_group( $data = [] );

	/**
	 * Gets the cache group for the current user.
	 *
	 * @return string Name of the group the user is currently in for this test.
	 */
	public function get_user_group() {
		return $this->user_group;
	}

	/**
	 * Place the user in a cache group if they aren't in one already.
	 */
	public function vary_cache() {
		// Maybe override the group based on the querystring or the presence of an option.
		$this->override_cache_group();

		// User already in a group, but the class doesn't know.
		if ( Vary_Cache::is_user_in_group( $this->cache_group ) && empty( $this->user_group ) ) {
			if ( Vary_Cache::is_user_in_group_segment( $this->cache_group, $this->a_group_key ) ) {
				$this->user_group = $this->a_group_key;
			} elseif ( Vary_Cache::is_user_in_group_segment( $this->cache_group, $this->b_group_key ) ) {
				$this->user_group = $this->b_group_key;
			}
		}

		// Still not in a group, we should set one.
		if ( empty( $this->user_group ) ) {
			$this->set_user_group( [] );
			Vary_Cache::set_group_for_user( $this->cache_group, $this->user_group );
		}
	}

	/**
	 * Possibly override a user's group in response to querystring parameters.
	 */
	private function override_cache_group() {
		$key = 'group-' . $this->cache_group;

		// Check if we've set a group by query string.
		$override_value = ! empty( $_GET[ $key ] ) ? $override_value = sanitize_text_field( $_GET[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// If we haven't set a group via query string, or we set an invalid one, maybe set one via option.
		if (
			empty( $override_value )
			|| ! in_array( $override_value, [ $this->a_group_key, $this->b_group_key ], true )
		) {
			$override_value = get_option( 'ab-select-group-' . $this->cache_group );
		}

		if ( ! empty( $override_value ) ) {
			// Place the user into the requested group if it is a valid group.
			if ( in_array( $override_value, [ $this->a_group_key, $this->b_group_key ], true ) ) {
				$this->user_group = $override_value;
				Vary_Cache::set_group_for_user( $this->cache_group, $this->user_group );
			}
		}
	}
}
