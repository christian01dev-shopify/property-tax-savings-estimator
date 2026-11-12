<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRC_Shortcode {
	public const SHORTCODE = 'southland_residential_calculator';

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'src-calculator', SRC_PLUGIN_URL . 'assets/css/calculator.css', array(), SRC_VERSION );
		wp_register_script( 'src-calculator', SRC_PLUGIN_URL . 'assets/js/calculator.js', array(), SRC_VERSION, true );
	}

	public function render(): string {
		wp_enqueue_style( 'src-calculator' );
		wp_enqueue_script( 'src-calculator' );
		wp_localize_script(
			'src-calculator',
			'SRC_CALCULATOR',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => SRC_Calculator::AJAX_ACTION,
				'nonce'    => wp_create_nonce( SRC_Calculator::NONCE_ACTION ),
				'maxValue' => SRC_Calculator::MAX_VALUE,
			)
		);

		$instance_id = wp_unique_id( 'src-calculator-' );
		$settings    = SRC_Settings::get_settings();
		$counties    = SRC_Settings::get_active_counties();

		ob_start();
		?>
		<div class="src-calculator" id="<?php echo esc_attr( $instance_id ); ?>" data-src-calculator>
			<div class="src-calculator__progress" aria-hidden="true"><span class="src-calculator__progress-bar" data-src-progress></span></div>
			<form class="src-form" novalidate>
				<section class="src-step" data-src-step="1" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-1-title">
					<p class="src-eyebrow"><?php esc_html_e( 'Residential Savings Estimator', 'southland-residential-calculator' ); ?></p>
					<h2 class="src-title" id="<?php echo esc_attr( $instance_id ); ?>-step-1-title"><?php esc_html_e( 'See How Much You Could Potentially Save', 'southland-residential-calculator' ); ?></h2>
					<p class="src-intro"><?php esc_html_e( 'Enter your property address and select its county to get started.', 'southland-residential-calculator' ); ?></p>
					<div class="src-field">
						<label for="<?php echo esc_attr( $instance_id ); ?>-address"><?php esc_html_e( 'Property Address', 'southland-residential-calculator' ); ?> <span aria-hidden="true">*</span></label>
						<input id="<?php echo esc_attr( $instance_id ); ?>-address" name="address" type="text" autocomplete="street-address" maxlength="300" required aria-describedby="<?php echo esc_attr( $instance_id ); ?>-address-help <?php echo esc_attr( $instance_id ); ?>-address-error">
						<p class="src-help" id="<?php echo esc_attr( $instance_id ); ?>-address-help"><?php esc_html_e( 'Your address identifies the property for your inquiry. This estimate does not analyze the exact address.', 'southland-residential-calculator' ); ?></p>
						<p class="src-error" id="<?php echo esc_attr( $instance_id ); ?>-address-error" data-src-error="address"></p>
					</div>
					<div class="src-field">
						<label for="<?php echo esc_attr( $instance_id ); ?>-county"><?php esc_html_e( 'County', 'southland-residential-calculator' ); ?> <span aria-hidden="true">*</span></label>
						<select id="<?php echo esc_attr( $instance_id ); ?>-county" name="county" required aria-describedby="<?php echo esc_attr( $instance_id ); ?>-county-help <?php echo esc_attr( $instance_id ); ?>-county-error">
							<option value=""><?php esc_html_e( 'Select a county', 'southland-residential-calculator' ); ?></option>
							<?php foreach ( $counties as $county ) : ?>
								<option value="<?php echo esc_attr( $county['slug'] ); ?>"><?php echo esc_html( $county['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="src-help" id="<?php echo esc_attr( $instance_id ); ?>-county-help"><?php esc_html_e( 'The estimate is based on the selected county and your current appraised value.', 'southland-residential-calculator' ); ?></p>
						<?php if ( ! $counties ) : ?><p class="src-notice"><?php esc_html_e( 'No county estimates are currently available. Please contact Southland Tax for a custom review.', 'southland-residential-calculator' ); ?></p><?php endif; ?>
						<p class="src-error" id="<?php echo esc_attr( $instance_id ); ?>-county-error" data-src-error="county"></p>
					</div>
					<button class="src-button" type="button" data-src-next <?php disabled( ! $counties ); ?>><?php esc_html_e( 'Continue', 'southland-residential-calculator' ); ?></button>
				</section>

				<section class="src-step" data-src-step="2" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-2-title" hidden>
					<p class="src-eyebrow"><?php esc_html_e( 'Property Value', 'southland-residential-calculator' ); ?></p>
					<h2 class="src-title" id="<?php echo esc_attr( $instance_id ); ?>-step-2-title"><?php esc_html_e( "What is your property's current appraised value?", 'southland-residential-calculator' ); ?></h2>
					<div class="src-field">
						<label for="<?php echo esc_attr( $instance_id ); ?>-value"><?php esc_html_e( 'Current Appraised Value', 'southland-residential-calculator' ); ?> <span aria-hidden="true">*</span></label>
						<div class="src-currency"><span aria-hidden="true">$</span><input id="<?php echo esc_attr( $instance_id ); ?>-value" name="current_value_display" type="text" inputmode="decimal" autocomplete="off" required aria-describedby="<?php echo esc_attr( $instance_id ); ?>-value-help <?php echo esc_attr( $instance_id ); ?>-value-error"></div>
						<p class="src-help" id="<?php echo esc_attr( $instance_id ); ?>-value-help"><?php esc_html_e( 'You can find this amount on your latest property tax appraisal or assessment notice.', 'southland-residential-calculator' ); ?></p>
						<p class="src-error" id="<?php echo esc_attr( $instance_id ); ?>-value-error" data-src-error="current_value"></p>
					</div>
					<div class="src-actions">
						<button class="src-button src-button--secondary" type="button" data-src-back><?php esc_html_e( 'Back', 'southland-residential-calculator' ); ?></button>
						<button class="src-button" type="submit" data-src-submit><?php esc_html_e( 'Calculate My Savings', 'southland-residential-calculator' ); ?></button>
					</div>
				</section>

				<section class="src-step src-results" data-src-step="3" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-3-title" hidden>
					<p class="src-eyebrow"><?php esc_html_e( 'Your Estimate', 'southland-residential-calculator' ); ?></p>
					<h2 class="src-title" id="<?php echo esc_attr( $instance_id ); ?>-step-3-title"><?php esc_html_e( 'Your Estimated Potential Savings', 'southland-residential-calculator' ); ?></h2>
					<dl class="src-results__grid">
						<div><dt><?php esc_html_e( 'Current Appraised Value', 'southland-residential-calculator' ); ?></dt><dd data-src-result="current_value">—</dd></div>
						<div><dt><?php esc_html_e( 'Estimated Value Reduction', 'southland-residential-calculator' ); ?></dt><dd data-src-result="value_reduction">—</dd></div>
						<div><dt><?php esc_html_e( 'Estimated New Appraised Value', 'southland-residential-calculator' ); ?></dt><dd data-src-result="new_value">—</dd></div>
						<div class="src-results__highlight"><dt><?php esc_html_e( 'Estimated Annual Tax Savings', 'southland-residential-calculator' ); ?></dt><dd data-src-result="annual_savings">—</dd></div>
					</dl>
					<p class="src-results__basis" data-src-basis></p>
					<p class="src-disclaimer"><?php echo esc_html( $settings['disclaimer'] ); ?></p>
					<a class="src-button src-button--link" href="<?php echo esc_url( SRC_Settings::get_cta_url() ); ?>"><?php esc_html_e( 'Get Started With Southland Tax', 'southland-residential-calculator' ); ?></a>
				</section>
			</form>
			<div class="src-status" data-src-status role="status" aria-live="polite" aria-atomic="true"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
