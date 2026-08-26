<?php
/**
 * Self-hosted update checker: gives this plugin a native WP admin
 * "update available" notice + one-click update, sourced from GitHub
 * Releases instead of WordPress.org (this plugin is not published there).
 *
 * Powered by the vendored Plugin Update Checker library
 * (https://github.com/YahnisElsts/plugin-update-checker), MIT licensed,
 * bundled at includes/libs/plugin-update-checker/.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Updater {

	/**
	 * Set up the update checker against the plugin's GitHub repository.
	 * Reads each release's attached ZIP asset (built via bin/build-zip.ps1)
	 * rather than GitHub's auto-generated source archive, so dev-only files
	 * (tests/, docs/, node_modules/) are never shipped to production sites.
	 */
	public static function init() {
		if ( ! defined( 'RLR_GITHUB_REPO_URL' ) || ! RLR_GITHUB_REPO_URL ) {
			return;
		}

		require_once RLR_PLUGIN_DIR . 'includes/libs/plugin-update-checker/plugin-update-checker.php';

		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			RLR_GITHUB_REPO_URL,
			RLR_PLUGIN_FILE,
			'restaurant-location-redirect'
		);

		$update_checker->getVcsApi()->enableReleaseAssets( '/\.zip$/i' );

		// Only set if a token was configured (private repos only — this
		// plugin's default repo is public, so this is normally unused).
		if ( defined( 'RLR_GITHUB_ACCESS_TOKEN' ) && RLR_GITHUB_ACCESS_TOKEN ) {
			$update_checker->setAuthentication( RLR_GITHUB_ACCESS_TOKEN );
		}
	}
}
