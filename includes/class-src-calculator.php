<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRC_Calculator {
	public const AJAX_ACTION = 'southland_calculate_savings';
	public const NONCE_ACTION = 'src_calculate_savings';
	public const MAX_VALUE = 1000000000;

	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_request' ) );
	}

	public function handle_request(): void {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			wp_send_json_error( array( 'message' => __( 'This calculator request method is not allowed.', 'southland-residential-calculator' ) ), 405 );
		}

		$raw_nonce = $_POST['nonce'] ?? '';
		if ( ! is_scalar( $raw_nonce ) ) {
			wp_send_json_error( array( 'message' => __( 'Your calculator session expired. Please refresh the page and try again.', 'southland-residential-calculator' ) ), 403 );
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $raw_nonce ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Your calculator session expired. Please refresh the page and try again.', 'southland-residential-calculator' ) ), 403 );
		}

		$raw_address = $_POST['address'] ?? '';
		if ( ! is_scalar( $raw_address ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid property address.', 'southland-residential-calculator' ) ), 400 );
		}
		$address = sanitize_text_field( wp_unslash( (string) $raw_address ) );
		if ( '' === $address || mb_strlen( $address ) > 300 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid property address.', 'southland-residential-calculator' ) ), 400 );
		}

		$raw_county = $_POST['county'] ?? '';
		if ( ! is_scalar( $raw_county ) ) {
			wp_send_json_error( array( 'message' => __( "We don't currently have an estimate configured for this county. Please contact Southland Tax for a custom review.", 'southland-residential-calculator' ) ), 404 );
		}
		$county_slug = sanitize_key( wp_unslash( (string) $raw_county ) );
		$county      = $this->find_active_county( $county_slug );
		if ( null === $county ) {
			wp_send_json_error( array( 'message' => __( "We don't currently have an estimate configured for this county. Please contact Southland Tax for a custom review.", 'southland-residential-calculator' ) ), 404 );
		}

		$raw_value = $_POST['current_value'] ?? '';
		if ( ! is_scalar( $raw_value ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid current appraised value.', 'southland-residential-calculator' ) ), 400 );
		}
		$raw_value = wp_unslash( (string) $raw_value );
		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $raw_value ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid current appraised value.', 'southland-residential-calculator' ) ), 400 );
		}

		$current_value = (float) $raw_value;
		if ( ! is_finite( $current_value ) || $current_value <= 0 || $current_value > self::MAX_VALUE ) {
			wp_send_json_error( array( 'message' => __( 'Current appraised value must be greater than zero and no more than $1,000,000,000.', 'southland-residential-calculator' ) ), 400 );
		}

		$value_reduction = $current_value * ( (float) $county['reduction_percentage'] / 100 );
		$new_value       = $current_value - $value_reduction;
		$annual_savings  = $value_reduction * ( (float) $county['savings_rate'] / 100 );

		wp_send_json_success(
			array(
				'county_name'     => (string) $county['name'],
				'current_value'   => $this->format_currency( $current_value ),
				'value_reduction' => $this->format_currency( $value_reduction ),
				'new_value'       => $this->format_currency( $new_value ),
				'annual_savings'  => $this->format_currency( $annual_savings ),
			)
		);
	}

	private function find_active_county( string $slug ): ?array {
		if ( '' === $slug ) {
			return null;
		}
		foreach ( SRC_Settings::get_active_counties() as $county ) {
			if ( isset( $county['slug'] ) && hash_equals( (string) $county['slug'], $slug ) ) {
				return $county;
			}
		}
		return null;
	}

	private function format_currency( float $value ): string {
		return '$' . number_format_i18n( round( $value ), 0 );
	}
}
