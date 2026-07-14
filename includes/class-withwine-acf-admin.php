<?php

defined( 'ABSPATH' ) || exit;

class WithWine_ACF_Admin {

	private const PAGE_SLUG = 'withwine-acf-integration';

	private const SUPPORTED_FIELD_TYPES = array(
		'select',
		'radio',
		'checkbox',
	);


	public static function init(): void {

		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_withwine_acf_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_withwine_acf_clear_cache', array( __CLASS__, 'clear_cache' ) );
	}


	/**
	 * Register the settings page.
	 */
	public static function register_page(): void {

		add_options_page(
			__( 'WithWine ACF Integration', 'withwine-acf-integration' ),
			__( 'WithWine ACF', 'withwine-acf-integration' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}


	/**
	 * Enqueue assets on this plugin's admin page only.
	 */
	public static function enqueue_assets(): void {

		$page = isset( $_GET['page'] )
			? sanitize_key( wp_unslash( $_GET['page'] ) )
			: '';

		if ( self::PAGE_SLUG !== $page ) {
			return;
		}

		wp_enqueue_style(
			'withwine-acf-integration-admin',
			WITHWINE_ACF_INTEGRATION_URL . 'assets/css/admin.css',
			array(),
			WITHWINE_ACF_INTEGRATION_VERSION
		);
	}


	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WithWine_ACF_Data::get_settings();
		?>
		<div class="wrap">

			<h1 class="withwine-acf-h1">
				<?php esc_html_e( 'WithWine ACF Integration', 'withwine-acf-integration' ); ?>
			</h1>

			<?php self::render_notice(); ?>

			<p class="withwine-acf-description">
				<?php
				esc_html_e(
					'Assign existing ACF Select, Radio Button or Checkbox fields to WithWine products or product lists. Enter each ACF field key beginning with “field_”.',
					'withwine-acf-integration'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="withwine_acf_save_settings">

				<?php wp_nonce_field( 'withwine_acf_save_settings', 'withwine_acf_settings_nonce' ); ?>

				<?php
				self::render_fields_table(
					'product_fields',
					__( 'Product Fields', 'withwine-acf-integration' ),
					__( 'These ACF choice fields will contain individual WithWine products.', 'withwine-acf-integration' ),
					$settings['product_fields']
				);

				self::render_fields_table(
					'product_list_fields',
					__( 'Product List Fields', 'withwine-acf-integration' ),
					__( 'These ACF choice fields will contain WithWine product lists.', 'withwine-acf-integration' ),
					$settings['product_list_fields']
				);
				?>

				<?php submit_button(); ?>
			</form>

			<hr class="withwine-acf-hr">

			<h2 class="withwine-acf-h2">
				<?php esc_html_e( 'Choice Cache', 'withwine-acf-integration' ); ?>
			</h2>

			<table class="widefat striped withwine-acf-cache-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'withwine-acf-integration' ); ?></th>
						<th><?php esc_html_e( 'Status', 'withwine-acf-integration' ); ?></th>
						<th><?php esc_html_e( 'Items', 'withwine-acf-integration' ); ?></th>
						<th><?php esc_html_e( 'Last Updated', 'withwine-acf-integration' ); ?></th>
					</tr>
				</thead>

				<tbody>
					<?php
					self::render_cache_row( __( 'Products', 'withwine-acf-integration' ), 'products' );
					self::render_cache_row( __( 'Product Lists', 'withwine-acf-integration' ), 'product_lists' );
					?>
				</tbody>
			</table>

			<p class="withwine-acf-cache-description">
				<?php
				esc_html_e(
					'After clearing the cache, choices are rebuilt on the next uncached frontend request. WithWine only makes its API shortcodes available on frontend requests.',
					'withwine-acf-integration'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="withwine_acf_clear_cache">
				<?php wp_nonce_field( 'withwine_acf_clear_cache', 'withwine_acf_cache_nonce' ); ?>
				<?php submit_button( __( 'Clear Choice Cache', 'withwine-acf-integration' ), 'secondary', 'submit', false ); ?>
			</form>

		</div>

		<?php self::render_page_script(); ?>
		<?php
	}


	/**
	 * Render one repeatable field-key table.
	 */
	private static function render_fields_table(
		string $name,
		string $title,
		string $description,
		array $rows
	): void {

		if ( empty( $rows ) ) {
			$rows = array(
				array(
					'field_key' => '',
					'label'     => '',
				),
			);
		}
		?>
		<div class="withwine-acf-field-group" data-field-name="<?php echo esc_attr( $name ); ?>">
			<h2 class="withwine-acf-field-group-title"><?php echo esc_html( $title ); ?></h2>
			<p class="withwine-acf-field-group-description"><?php echo esc_html( $description ); ?></p>

			<table class="widefat striped withwine-acf-field-group-table">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'ACF Field Key', 'withwine-acf-integration' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'Detected ACF Field', 'withwine-acf-integration' ); ?></th>
						<th><?php esc_html_e( 'Label / Note', 'withwine-acf-integration' ); ?></th>
						<th style="width: 90px;"></th>
					</tr>
				</thead>

				<tbody class="withwine-acf-field-rows">
					<?php foreach ( $rows as $index => $row ) : ?>
						<?php self::render_field_row( $name, (int) $index, $row ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button withwine-acf-add-row">
					<?php esc_html_e( 'Add Field', 'withwine-acf-integration' ); ?>
				</button>
			</p>
		</div>
		<?php
	}


	/**
	 * Render one field-key row.
	 */
	private static function render_field_row(
		string $name,
		int $index,
		array $row
	): void {

		$field_key  = $row['field_key'] ?? '';
		$field_info = self::get_field_info( $field_key );
		?>
		<tr class="withwine-acf-field-row">
			<td>
				<input
					type="text"
					class="regular-text"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $index ); ?>][field_key]"
					value="<?php echo esc_attr( $field_key ); ?>"
					placeholder="field_xxxxxxxxxxxxx"
				>
			</td>

			<td>
				<div class="withwine-acf-field-meta">
					<?php if ( $field_info['exists'] ) : ?>
						<span class="withwine-acf-field-label"><?php echo esc_html( $field_info['label'] ); ?></span>
						<code class="withwine-acf-field-type"><?php echo esc_html( $field_info['type_label'] ); ?></code>

						<?php if ( $field_info['supported'] ) : ?>
							<span class="withwine-acf-field-status withwine-acf-field-status--valid">
								<?php esc_html_e( 'Supported', 'withwine-acf-integration' ); ?>
							</span>
						<?php else : ?>
							<span class="withwine-acf-field-status withwine-acf-field-status--invalid">
								<?php esc_html_e( 'Unsupported field type', 'withwine-acf-integration' ); ?>
							</span>
						<?php endif; ?>
					<?php elseif ( '' !== $field_key ) : ?>
						<span class="withwine-acf-field-status withwine-acf-field-status--missing">
							<?php esc_html_e( 'Field not found', 'withwine-acf-integration' ); ?>
						</span>
					<?php else : ?>
						<span aria-hidden="true">—</span>
					<?php endif; ?>
				</div>
			</td>

			<td>
				<input
					type="text"
					class="regular-text"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $index ); ?>][label]"
					value="<?php echo esc_attr( $row['label'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr__( 'Optional note', 'withwine-acf-integration' ); ?>"
				>
			</td>

			<td>
				<button type="button" class="button-link-delete withwine-acf-remove-row">
					<?php esc_html_e( 'Remove', 'withwine-acf-integration' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}


	/**
	 * Resolve an ACF field key to a label and supported field type.
	 */
	private static function get_field_info( string $field_key ): array {

		$info = array(
			'exists'     => false,
			'supported'  => false,
			'label'      => '',
			'type'       => '',
			'type_label' => '',
		);

		if ( '' === $field_key || ! function_exists( 'acf_get_field' ) ) {
			return $info;
		}

		$field = acf_get_field( $field_key );

		if ( ! is_array( $field ) ) {
			return $info;
		}

		$type = sanitize_key( $field['type'] ?? '' );

		$info['exists']     = true;
		$info['supported']  = in_array( $type, self::SUPPORTED_FIELD_TYPES, true );
		$info['label']      = (string) ( $field['label'] ?? $field_key );
		$info['type']       = $type;
		$info['type_label'] = self::get_field_type_label( $type );

		return $info;
	}


	/**
	 * Return a friendly ACF field type label.
	 */
	private static function get_field_type_label( string $type ): string {

		$labels = array(
			'select'   => __( 'Select', 'withwine-acf-integration' ),
			'radio'    => __( 'Radio Button', 'withwine-acf-integration' ),
			'checkbox' => __( 'Checkbox', 'withwine-acf-integration' ),
		);

		return $labels[ $type ] ?? $type;
	}


	/**
	 * Render cache status row.
	 */
	private static function render_cache_row( string $label, string $cache_name ): void {

		$exists  = WithWine_ACF_Cache::exists( $cache_name );
		$count   = WithWine_ACF_Cache::count( $cache_name );
		$updated = WithWine_ACF_Cache::get_updated( $cache_name );
		?>
		<tr>
			<td><strong><?php echo esc_html( $label ); ?></strong></td>
			<td>
				<?php echo $exists ? esc_html__( 'Cached', 'withwine-acf-integration' ) : esc_html__( 'Not cached', 'withwine-acf-integration' ); ?>
			</td>
			<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
			<td>
				<?php
				if ( $exists && $updated ) {
					echo esc_html(
						sprintf(
							/* translators: %s: human-readable time difference. */
							__( '%s ago', 'withwine-acf-integration' ),
							human_time_diff( $updated, time() )
						)
					);
				} else {
					echo '&mdash;';
				}
				?>
			</td>
		</tr>
		<?php
	}


	/**
	 * Save configured ACF field keys.
	 */
	public static function save_settings(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not permitted to update these settings.', 'withwine-acf-integration' ) );
		}

		check_admin_referer( 'withwine_acf_save_settings', 'withwine_acf_settings_nonce' );

		$settings = array(
			'product_fields'      => self::sanitize_field_rows( $_POST['product_fields'] ?? array() ),
			'product_list_fields' => self::sanitize_field_rows( $_POST['product_list_fields'] ?? array() ),
		);

		update_option( WithWine_ACF_Data::get_settings_option_name(), $settings );

		self::redirect( 'saved' );
	}


	/**
	 * Sanitize repeatable field-key rows and remove duplicate keys.
	 */
	private static function sanitize_field_rows( $rows ): array {

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $rows as $row ) {
			$field_key = sanitize_key( $row['field_key'] ?? '' );

			if ( ! str_starts_with( $field_key, 'field_' ) ) {
				continue;
			}

			$sanitized[ $field_key ] = array(
				'field_key' => $field_key,
				'label'     => sanitize_text_field( $row['label'] ?? '' ),
			);
		}

		return array_values( $sanitized );
	}


	/**
	 * Clear both cached sources.
	 */
	public static function clear_cache(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not permitted to clear this cache.', 'withwine-acf-integration' ) );
		}

		check_admin_referer( 'withwine_acf_clear_cache', 'withwine_acf_cache_nonce' );

		WithWine_ACF_Cache::clear_all();

		self::redirect( 'cache-cleared' );
	}


	/**
	 * Redirect back to the settings page.
	 */
	private static function redirect( string $notice ): void {

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => self::PAGE_SLUG,
					'withwine_acf_notice' => $notice,
				),
				admin_url( 'options-general.php' )
			)
		);

		exit;
	}


	/**
	 * Render admin success notices.
	 */
	private static function render_notice(): void {

		$notice = isset( $_GET['withwine_acf_notice'] )
			? sanitize_key( wp_unslash( $_GET['withwine_acf_notice'] ) )
			: '';

		if ( '' === $notice ) {
			return;
		}

		switch ( $notice ) {
			case 'saved':
				$message = __( 'WithWine ACF field mappings were saved.', 'withwine-acf-integration' );
				break;

			case 'cache-cleared':
				$message = __( 'The WithWine choice cache was cleared.', 'withwine-acf-integration' );
				break;

			default:
				return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}


	/**
	 * Repeatable-row JavaScript.
	 */
	private static function render_page_script(): void {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', () => {
				document.querySelectorAll('.withwine-acf-field-group').forEach(group => {
					const rows = group.querySelector('.withwine-acf-field-rows');
					const addButton = group.querySelector('.withwine-acf-add-row');
					const fieldName = group.dataset.fieldName;

					const reindexRows = () => {
						rows.querySelectorAll('.withwine-acf-field-row').forEach((row, index) => {
							row.querySelectorAll('[name]').forEach(input => {
								const property = input.name.endsWith('[label]') ? 'label' : 'field_key';
								input.name = `${fieldName}[${index}][${property}]`;
							});
						});
					};

					addButton.addEventListener('click', () => {
						const sourceRow = rows.querySelector('.withwine-acf-field-row');
						const newRow = sourceRow.cloneNode(true);

						newRow.querySelectorAll('input').forEach(input => {
							input.value = '';
						});

						const meta = newRow.querySelector('.withwine-acf-field-meta');
						if (meta) {
							meta.innerHTML = '<span aria-hidden="true">—</span>';
						}

						rows.appendChild(newRow);
						reindexRows();
					});

					rows.addEventListener('click', event => {
						const removeButton = event.target.closest('.withwine-acf-remove-row');

						if (!removeButton) {
							return;
						}

						const row = removeButton.closest('.withwine-acf-field-row');
						const allRows = rows.querySelectorAll('.withwine-acf-field-row');

						if (allRows.length === 1) {
							row.querySelectorAll('input').forEach(input => {
								input.value = '';
							});

							const meta = row.querySelector('.withwine-acf-field-meta');
							if (meta) {
								meta.innerHTML = '<span aria-hidden="true">—</span>';
							}
							return;
						}

						row.remove();
						reindexRows();
					});
				});
			});
		</script>
		<?php
	}
}
