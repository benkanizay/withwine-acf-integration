<?php

defined( 'ABSPATH' ) || exit;

class WithWine_ACF_Cache {

	private const CACHE_NAMES = array(
		'products',
		'product_lists',
	);


	/**
	 * Build a versioned transient key.
	 */
	public static function get_key( string $name ): string {

		return sprintf(
			'withwine_acf_%s_v%d',
			sanitize_key( $name ),
			WITHWINE_ACF_CHOICES_VERSION
		);
	}


	/**
	 * Build the option key used to track when a cache was last populated.
	 */
	private static function get_updated_key( string $name ): string {

		return self::get_key( $name ) . '_updated';
	}


	/**
	 * Get cached choices.
	 *
	 * @return array|false
	 */
	public static function get( string $name ) {

		return get_transient( self::get_key( $name ) );
	}


	/**
	 * Cache choices and record the update time.
	 */
	public static function set( string $name, array $choices ): bool {

		$stored = set_transient(
			self::get_key( $name ),
			$choices,
			WITHWINE_ACF_CHOICES_CACHE_LIFETIME
		);

		if ( $stored ) {
			update_option(
				self::get_updated_key( $name ),
				time(),
				false
			);
		}

		return $stored;
	}


	/**
	 * Determine whether a cache currently exists.
	 */
	public static function exists( string $name ): bool {

		return false !== self::get( $name );
	}


	/**
	 * Count cached choices.
	 */
	public static function count( string $name ): int {

		$choices = self::get( $name );

		return is_array( $choices ) ? count( $choices ) : 0;
	}


	/**
	 * Return the Unix timestamp when the cache was last populated.
	 */
	public static function get_updated( string $name ): int {

		return (int) get_option( self::get_updated_key( $name ), 0 );
	}


	/**
	 * Clear one cache.
	 */
	public static function clear( string $name ): void {

		delete_transient( self::get_key( $name ) );
		delete_option( self::get_updated_key( $name ) );
	}


	/**
	 * Clear all WithWine ACF caches.
	 *
	 */
	public static function clear_all(): void {

		foreach ( self::CACHE_NAMES as $name ) {
			self::clear( $name );
		}
	}
}
