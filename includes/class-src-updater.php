<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Isolated GitHub Release updater integration.
 */
final class SRC_Updater {
	private const REPOSITORY_URL = 'https://github.com/christian01dev-shopify/property-tax-savings-estimator/';
	private const PLUGIN_SLUG = 'southland-residential-calculator';
	private const RELEASE_ASSET = 'southland-residential-calculator.zip';

	/**
	 * Initialize Plugin Update Checker when its vendored files are available.
	 *
	 * Update failures are intentionally non-fatal and do not affect the calculator.
	 */
	public static function initialize(): void {
		$library_file = SRC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $library_file ) ) {
			return;
		}

		try {
			require_once $library_file;

			$factory_class = '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
			if ( ! class_exists( $factory_class ) ) {
				return;
			}

			$update_checker = $factory_class::buildUpdateChecker(
				self::REPOSITORY_URL,
				SRC_PLUGIN_FILE,
				self::PLUGIN_SLUG
			);

			// Only published GitHub Releases are valid update sources. This removes
			// the library's normal tag and branch fallback strategies.
			add_filter(
				$update_checker->getUniqueName( 'vcs_update_detection_strategies' ),
				static function ( array $strategies ): array {
					if ( isset( $strategies['latest_release'] ) ) {
						return array( 'latest_release' => $strategies['latest_release'] );
					}
					return array();
				}
			);

			// Require the clean, workflow-built ZIP. Never fall back to GitHub's
			// version-prefixed source archive, which can change the installed folder.
			$api = $update_checker->getVcsApi();
			if ( method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets(
					'/^' . preg_quote( self::RELEASE_ASSET, '/' ) . '$/i',
					\YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS
				);
			}

			if ( defined( 'PROPERTY_TAX_ESTIMATOR_GITHUB_TOKEN' ) ) {
				$token = PROPERTY_TAX_ESTIMATOR_GITHUB_TOKEN;
				if ( is_string( $token ) && '' !== trim( $token ) ) {
					$update_checker->setAuthentication( trim( $token ) );
				}
			}
		} catch ( \Throwable $exception ) {
			// Update availability is optional. Never let it interrupt site requests.
			return;
		}
	}
}
