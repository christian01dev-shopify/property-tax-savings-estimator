<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRC_Settings {
	public const OPTION_NAME = 'src_calculator_settings';
	public const SETTINGS_GROUP = 'src_calculator_settings_group';

	public static function get_defaults(): array {
		return array(
			'disclaimer' => __( 'This estimate is based on historical and configured assumptions and is not a guarantee of actual tax savings or appraisal reduction.', 'southland-residential-calculator' ),
			'cta_url'    => '/contact-us/',
			'counties'   => array(),
		);
	}

	public static function get_settings(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
		$settings['disclaimer'] = is_scalar( $settings['disclaimer'] ) ? (string) $settings['disclaimer'] : self::get_defaults()['disclaimer'];
		$settings['cta_url']    = is_scalar( $settings['cta_url'] ) ? (string) $settings['cta_url'] : self::get_defaults()['cta_url'];
		$settings['counties']   = is_array( $settings['counties'] ) ? $settings['counties'] : array();
		return $settings;
	}

	public static function get_active_counties(): array {
		$settings = self::get_settings();
		return array_values(
			array_filter(
				$settings['counties'],
				static function ( $county ) {
					return is_array( $county )
						&& ! empty( $county['active'] )
						&& isset( $county['slug'], $county['name'], $county['reduction_percentage'], $county['savings_rate'] )
						&& is_scalar( $county['slug'] )
						&& is_scalar( $county['name'] )
						&& is_numeric( $county['reduction_percentage'] )
						&& is_numeric( $county['savings_rate'] );
				}
			)
		);
	}

	public static function get_cta_url(): string {
		$url = (string) self::get_settings()['cta_url'];
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return home_url( $url );
		}
		return $url;
	}

	public function register_hooks(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	public function add_settings_page(): void {
		add_options_page(
			__( 'Residential Calculator Settings', 'southland-residential-calculator' ),
			__( 'Residential Calculator', 'southland-residential-calculator' ),
			'manage_options',
			'southland-residential-calculator',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_southland-residential-calculator' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'src-admin', SRC_PLUGIN_URL . 'assets/css/admin.css', array(), SRC_VERSION );
		wp_enqueue_script( 'src-admin', SRC_PLUGIN_URL . 'assets/js/admin-settings.js', array(), SRC_VERSION, true );
	}

	public function sanitize_settings( $input ): array {
		$old      = self::get_settings();
		$input    = is_array( $input ) ? $input : array();
		$errors   = array();
		$output   = self::get_defaults();
		$raw_disclaimer       = $input['disclaimer'] ?? '';
		$output['disclaimer'] = is_scalar( $raw_disclaimer ) ? sanitize_textarea_field( wp_unslash( (string) $raw_disclaimer ) ) : '';
		if ( '' === $output['disclaimer'] ) {
			$errors[] = __( 'The calculator disclaimer cannot be empty.', 'southland-residential-calculator' );
		}

		$raw_cta_url = $input['cta_url'] ?? '';
		$cta_url     = is_scalar( $raw_cta_url ) ? trim( sanitize_text_field( wp_unslash( (string) $raw_cta_url ) ) ) : '';
		if ( $this->is_valid_cta_url( $cta_url ) ) {
			$output['cta_url'] = str_starts_with( $cta_url, '/' ) ? $cta_url : esc_url_raw( $cta_url, array( 'http', 'https' ) );
		} else {
			$errors[] = __( 'CTA URL must be a site-relative path beginning with one slash or a valid HTTP/HTTPS URL.', 'southland-residential-calculator' );
		}

		$counties = isset( $input['counties'] ) && is_array( $input['counties'] ) ? $input['counties'] : array();
		$slugs    = array();
		foreach ( $counties as $index => $county ) {
			if ( ! is_array( $county ) ) {
				continue;
			}

			$raw_name = $county['name'] ?? '';
			$raw_slug = $county['slug'] ?? '';
			$name     = is_scalar( $raw_name ) ? sanitize_text_field( wp_unslash( (string) $raw_name ) ) : '';
			$slug     = is_scalar( $raw_slug ) ? sanitize_title( wp_unslash( (string) $raw_slug ) ) : '';
			if ( '' === $slug ) {
				$slug = sanitize_title( $name );
			}

			$row_number = (int) $index + 1;
			if ( '' === $name || '' === $slug ) {
				$errors[] = sprintf( __( 'County row %d requires a name and a valid identifier.', 'southland-residential-calculator' ), $row_number );
				continue;
			}
			if ( isset( $slugs[ $slug ] ) ) {
				$errors[] = sprintf( __( 'County identifier “%s” must be unique.', 'southland-residential-calculator' ), $slug );
				continue;
			}

			$reduction = $this->sanitize_percentage( $county['reduction_percentage'] ?? null );
			$savings   = $this->sanitize_percentage( $county['savings_rate'] ?? null );
			if ( null === $reduction || null === $savings ) {
				$errors[] = sprintf( __( 'County row %d percentages must be numbers from 0 through 100.', 'southland-residential-calculator' ), $row_number );
				continue;
			}

			$slugs[ $slug ]       = true;
			$output['counties'][] = array(
				'slug'                 => $slug,
				'name'                 => $name,
				'reduction_percentage' => $reduction,
				'savings_rate'         => $savings,
				'active'               => ! empty( $county['active'] ),
			);
		}

		if ( $errors ) {
			foreach ( $errors as $error ) {
				add_settings_error( self::OPTION_NAME, 'src_invalid_settings', $error, 'error' );
			}
			return $old;
		}

		add_settings_error( self::OPTION_NAME, 'src_settings_saved', __( 'Calculator settings saved.', 'southland-residential-calculator' ), 'success' );
		return $output;
	}

	private function sanitize_percentage( $value ): ?float {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;
		return is_finite( $value ) && $value >= 0 && $value <= 100 ? $value : null;
	}

	private function is_valid_cta_url( string $url ): bool {
		if ( preg_match( '#^/(?!/)#', $url ) ) {
			return true;
		}
		return (bool) wp_http_validate_url( $url );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'southland-residential-calculator' ) );
		}
		$settings = self::get_settings();
		$active_count = count( array_filter( $settings['counties'], static function ( $county ) { return ! empty( $county['active'] ); } ) );
		?>
		<div class="wrap src-admin">
			<header class="src-admin__hero">
				<div><p class="src-admin__eyebrow"><?php esc_html_e( 'Southland Tax', 'southland-residential-calculator' ); ?></p><h1><?php esc_html_e( 'Residential Calculator Settings', 'southland-residential-calculator' ); ?></h1><p><?php esc_html_e( 'Control the calculator message, destination, and county-level estimate assumptions.', 'southland-residential-calculator' ); ?></p></div>
				<div class="src-admin__summary" aria-label="<?php esc_attr_e( 'County configuration summary', 'southland-residential-calculator' ); ?>"><div><strong><?php echo esc_html( count( $settings['counties'] ) ); ?></strong><span><?php esc_html_e( 'Counties', 'southland-residential-calculator' ); ?></span></div><div><strong><?php echo esc_html( $active_count ); ?></strong><span><?php esc_html_e( 'Active', 'southland-residential-calculator' ); ?></span></div></div>
			</header>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<form action="options.php" method="post">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<section class="src-admin__card" aria-labelledby="src-global-heading">
					<div class="src-admin__section-heading"><div class="src-admin__section-icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></div><div><h2 id="src-global-heading"><?php esc_html_e( 'Global Settings', 'southland-residential-calculator' ); ?></h2><p><?php esc_html_e( 'These values appear on every calculator result.', 'southland-residential-calculator' ); ?></p></div></div>
					<div class="src-admin__fields">
						<div class="src-admin__field src-admin__field--wide"><label for="src-disclaimer"><?php esc_html_e( 'Calculator Disclaimer', 'southland-residential-calculator' ); ?></label><textarea id="src-disclaimer" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[disclaimer]" rows="4" required><?php echo esc_textarea( $settings['disclaimer'] ); ?></textarea><p><?php esc_html_e( 'Shown beneath each estimate to set clear expectations.', 'southland-residential-calculator' ); ?></p></div>
						<div class="src-admin__field"><label for="src-cta-url"><?php esc_html_e( 'CTA destination', 'southland-residential-calculator' ); ?></label><div class="src-admin__input-with-icon"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><input id="src-cta-url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cta_url]" type="text" value="<?php echo esc_attr( $settings['cta_url'] ); ?>" required></div><p><?php esc_html_e( 'Use a site path such as /contact-us/ or a full HTTP/HTTPS URL.', 'southland-residential-calculator' ); ?></p></div>
					</div>
				</section>

				<section class="src-admin__card" aria-labelledby="src-counties-heading">
					<div class="src-admin__county-heading"><div class="src-admin__section-heading"><div class="src-admin__section-icon" aria-hidden="true"><span class="dashicons dashicons-location-alt"></span></div><div><h2 id="src-counties-heading"><?php esc_html_e( 'County Estimates', 'southland-residential-calculator' ); ?></h2><p><?php esc_html_e( 'Set the assumptions used for each available county.', 'southland-residential-calculator' ); ?></p></div></div><button class="button src-admin__add-button" id="src-add-county" type="button"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add County', 'southland-residential-calculator' ); ?></button></div>
					<div class="src-admin__table-wrap">
					<table class="widefat striped" id="src-county-table">
						<thead><tr>
							<th><?php esc_html_e( 'Identifier / Slug', 'southland-residential-calculator' ); ?></th>
							<th><?php esc_html_e( 'County Name', 'southland-residential-calculator' ); ?></th>
							<th><?php esc_html_e( 'Reduction %', 'southland-residential-calculator' ); ?></th>
							<th><?php esc_html_e( 'Savings Rate %', 'southland-residential-calculator' ); ?></th>
							<th><?php esc_html_e( 'Active', 'southland-residential-calculator' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'southland-residential-calculator' ); ?></span></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $settings['counties'] as $index => $county ) : ?>
							<?php $this->render_county_row( (string) $index, $county ); ?>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="src-admin__empty" data-src-empty <?php echo $settings['counties'] ? 'hidden' : ''; ?>><span class="dashicons dashicons-location" aria-hidden="true"></span><strong><?php esc_html_e( 'No counties configured yet', 'southland-residential-calculator' ); ?></strong><p><?php esc_html_e( 'Add your first county to make an estimate available in the calculator.', 'southland-residential-calculator' ); ?></p></div>
					<p class="src-admin__tip"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Identifiers are generated from the county name when blank and remain stable unless you edit them. Identifiers must be unique.', 'southland-residential-calculator' ); ?></p>
					</div>
				</section>
				<div class="src-admin__save-bar"><p><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Changes affect new calculator estimates after saving.', 'southland-residential-calculator' ); ?></p><?php submit_button( __( 'Save Changes', 'southland-residential-calculator' ), 'primary', 'submit', false ); ?></div>
			</form>
			<template id="src-county-row-template"><?php $this->render_county_row( '__INDEX__', array() ); ?></template>
		</div>
		<?php
	}

	private function render_county_row( string $index, array $county ): void {
		$name = self::OPTION_NAME . '[counties][' . $index . ']';
		?>
		<tr class="src-county-row">
			<td data-label="<?php esc_attr_e( 'Identifier / Slug', 'southland-residential-calculator' ); ?>"><input class="regular-text src-county-slug" name="<?php echo esc_attr( $name ); ?>[slug]" type="text" value="<?php echo esc_attr( $county['slug'] ?? '' ); ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="dallas-county" aria-label="<?php esc_attr_e( 'County identifier', 'southland-residential-calculator' ); ?>"></td>
			<td data-label="<?php esc_attr_e( 'County Name', 'southland-residential-calculator' ); ?>"><input class="regular-text src-county-name" name="<?php echo esc_attr( $name ); ?>[name]" type="text" value="<?php echo esc_attr( $county['name'] ?? '' ); ?>" placeholder="Dallas County" required aria-label="<?php esc_attr_e( 'County name', 'southland-residential-calculator' ); ?>"></td>
			<td data-label="<?php esc_attr_e( 'Reduction %', 'southland-residential-calculator' ); ?>"><div class="src-admin__number"><input name="<?php echo esc_attr( $name ); ?>[reduction_percentage]" type="number" value="<?php echo esc_attr( $county['reduction_percentage'] ?? '' ); ?>" min="0" max="100" step="0.01" required aria-label="<?php esc_attr_e( 'Estimated reduction percentage', 'southland-residential-calculator' ); ?>"><span>%</span></div></td>
			<td data-label="<?php esc_attr_e( 'Savings Rate %', 'southland-residential-calculator' ); ?>"><div class="src-admin__number"><input name="<?php echo esc_attr( $name ); ?>[savings_rate]" type="number" value="<?php echo esc_attr( $county['savings_rate'] ?? '' ); ?>" min="0" max="100" step="0.01" required aria-label="<?php esc_attr_e( 'Estimated savings rate percentage', 'southland-residential-calculator' ); ?>"><span>%</span></div></td>
			<td data-label="<?php esc_attr_e( 'Active', 'southland-residential-calculator' ); ?>"><label class="src-admin__switch"><input name="<?php echo esc_attr( $name ); ?>[active]" type="checkbox" value="1" <?php checked( ! empty( $county['active'] ) ); ?>><span aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Active', 'southland-residential-calculator' ); ?></span></label></td>
			<td data-label="<?php esc_attr_e( 'Actions', 'southland-residential-calculator' ); ?>"><button class="button-link-delete src-remove-county" type="button"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span><?php esc_html_e( 'Remove', 'southland-residential-calculator' ); ?></span></button></td>
		</tr>
		<?php
	}
}
