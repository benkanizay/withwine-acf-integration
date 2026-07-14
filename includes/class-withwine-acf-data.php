<?php

defined( 'ABSPATH' ) || exit;

class WithWine_ACF_Data {

	private const SUPPORTED_FIELD_TYPES = array(
		'select',
		'radio',
		'checkbox',
	);

	private const SETTINGS_OPTION = 'withwine_acf_integration_settings';

	private const SOURCES = array(
		'products' => array(
			'cache_name' => 'products',
			'type'       => 'products',
			'foreach'    => 'data',
			'item_name'  => 'product',
		),

		'product_lists' => array(
			'cache_name' => 'product_lists',
			'type'       => 'product-lists',
			'foreach'    => '',
			'item_name'  => 'productList',
		),
	);


	/**
	 * Register ACF filters and frontend cache warming.
	 */
	public static function init(): void {

		add_action(
			'acf/init',
			array( __CLASS__, 'register_field_filters' ),
			20
		);

		/*
		 * WithWine registers its API shortcodes on frontend requests.
		 * Warm missing caches once WordPress has reached the frontend.
		 */
		add_action(
			'wp',
			array( __CLASS__, 'warm_missing_caches' ),
			20
		);
	}


	/**
	 * Register a load_field filter for every configured ACF field key.
	 */
	public static function register_field_filters(): void {

		$settings = self::get_settings();

		foreach ( $settings['product_fields'] as $row ) {
			self::register_field_filter(
				$row['field_key'],
				'products'
			);
		}

		foreach ( $settings['product_list_fields'] as $row ) {
			self::register_field_filter(
				$row['field_key'],
				'product_lists'
			);
		}
	}


	/**
	 * Register one ACF field population filter.
	 */
	private static function register_field_filter(
		string $field_key,
		string $source
	): void {

		$field_key = sanitize_key( $field_key );

		if ( ! str_starts_with( $field_key, 'field_' ) ) {
			return;
		}

		add_filter(
			'acf/load_field/key=' . $field_key,
			static function( $field ) use ( $source ) {

				$field_type = sanitize_key( $field['type'] ?? '' );

				if ( ! in_array( $field_type, self::SUPPORTED_FIELD_TYPES, true ) ) {
					return $field;
				}

				$choices = self::get_choices( $source );

				/*
				 * Never replace the field's choices when WithWine data
				 * could not be obtained during the current request.
				 */
				if (
					false !== $choices &&
					! empty( $choices )
				) {
					$field['choices'] = $choices;
				}

				return $field;
			}
		);
	}


	/**
	 * Retrieve plugin settings with defaults.
	 */
	public static function get_settings(): array {

		$settings = get_option(
			self::SETTINGS_OPTION,
			array()
		);

		return wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'product_fields'      => array(),
				'product_list_fields' => array(),
			)
		);
	}


	/**
	 * Return the option name for the admin class.
	 */
	public static function get_settings_option_name(): string {

		return self::SETTINGS_OPTION;
	}


	/**
	 * Get choices for a configured source.
	 *
	 * @return array|false
	 */
	public static function get_choices( string $source ) {

		if ( ! isset( self::SOURCES[ $source ] ) ) {
			return false;
		}

		$config = self::SOURCES[ $source ];

		$cached = WithWine_ACF_Cache::get(
			$config['cache_name']
		);

		if ( false !== $cached ) {
			return $cached;
		}

		/*
		 * WithWine deliberately does not register these shortcodes in
		 * every admin request. Return false rather than wiping choices.
		 */
		if ( ! self::shortcodes_available() ) {
			return false;
		}

		$output = self::run_shortcode( $config );

		if ( false === $output ) {
			return false;
		}

		$choices = self::parse_choices( $output );

		if ( empty( $choices ) ) {
			return false;
		}

		WithWine_ACF_Cache::set(
			$config['cache_name'],
			$choices
		);

		return $choices;
	}


	/**
	 * Check whether the required WithWine shortcodes are registered.
	 */
	private static function shortcodes_available(): bool {

		return shortcode_exists( 'withwineapi' )
			&& shortcode_exists( 'withwineapi_1' );
	}


	/**
	 * Execute the working WithWine shortcode template.
	 *
	 * @return string|false
	 */
	private static function run_shortcode( array $config ) {

		$shortcode = sprintf(
			'[withwineapi type="%1$s" foreach="%2$s" itemname="%3$s"]'
			. '[withwineapi_1 type="item" sourceitem="%3$s" render="id" /]'
			. '|||'
			. '[withwineapi_1 type="item" sourceitem="%3$s" render="name" /]'
			. "\n"
			. '[/withwineapi]',
			$config['type'],
			$config['foreach'],
			$config['item_name']
		);

		$output = do_shortcode( $shortcode );

		if (
			! is_string( $output ) ||
			'' === trim( $output ) ||
			false === strpos( $output, '|||' ) ||
			false !== strpos( $output, '[withwineapi' )
		) {
			return false;
		}

		return $output;
	}


	/**
	 * Parse shortcode output into ACF choices.
	 */
	private static function parse_choices( string $data ): array {

		$choices = array();

		$data = preg_replace(
			'/\s*\|\|\|\s*/',
			'|||',
			trim( $data )
		);

		$lines = preg_split(
			'/\R+/',
			$data,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		foreach ( $lines as $line ) {
			$parts = array_map(
				'trim',
				explode( '|||', $line, 2 )
			);

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			list( $id, $name ) = $parts;

			if ( '' === $id || '' === $name ) {
				continue;
			}

			$choices[ (string) $id ] = $name;
		}

		return $choices;
	}


	/**
	 * Warm missing caches during a supported frontend request.
	 */
	public static function warm_missing_caches(): void {

		if (
			is_admin() ||
			wp_doing_ajax() ||
			wp_doing_cron()
		) {
			return;
		}

		if ( ! self::shortcodes_available() ) {
			return;
		}

		$settings = self::get_settings();

		if (
			! empty( $settings['product_fields'] ) &&
			! WithWine_ACF_Cache::exists( 'products' )
		) {
			self::get_choices( 'products' );
		}

		if (
			! empty( $settings['product_list_fields'] ) &&
			! WithWine_ACF_Cache::exists( 'product_lists' )
		) {
			self::get_choices( 'product_lists' );
		}
	}
}
