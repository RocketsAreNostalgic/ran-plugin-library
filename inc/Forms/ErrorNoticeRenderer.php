<?php
/**
 * ErrorNoticeRenderer: Static utility for rendering admin error notices.
 *
 * Provides consistent error display across AdminSettings, UserSettings,
 * and their lightweight registry counterparts.
 *
 * @package Ran\PluginLib\Forms
 * @since   0.3.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

/**
 * Static utility class for rendering WordPress admin error notices.
 *
 * Used by FormsBaseTrait, AdminMenuRegistry, and UserSettingsRegistry
 * to provide consistent error display with optional stack traces in dev mode.
 */
class ErrorNoticeRenderer {
	/**
	 * Render an error notice div.
	 *
	 * @param \Throwable $e       The exception or error to display.
	 * @param string     $context Context label (e.g., 'AdminMenuRegistry Error').
	 * @param bool|null  $is_dev  Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function render(\Throwable $e, string $context = 'Error', ?bool $is_dev = null): void {
		if ($is_dev === null) {
			$is_dev = defined('WP_DEBUG') && WP_DEBUG;
		}

		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html($context) . '</strong></p>';
		echo '<p>' . esc_html($e->getMessage()) . '</p>';
		if ($is_dev) {
			echo '<p><small>' . esc_html($e->getFile()) . ':' . esc_html((string) $e->getLine()) . '</small></p>';
			echo '<details><summary>Stack Trace</summary><pre style="overflow:auto;max-height:300px;font-size:11px;">' . esc_html($e->getTraceAsString()) . '</pre></details>';
		}
		echo '</div>';
	}

	/**
	 * Render an error notice with additional context info (e.g., page slug, collection).
	 *
	 * @param \Throwable $e            The exception or error to display.
	 * @param string     $context      Context label (e.g., 'AdminSettings Error').
	 * @param string     $context_key  Key for the context value (e.g., 'page', 'collection').
	 * @param string     $context_value Value for the context (e.g., 'kplr-my-page').
	 * @param bool|null  $is_dev       Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function renderWithContext(
		\Throwable $e,
		string $context,
		string $context_key,
		string $context_value,
		?bool $is_dev = null
	): void {
		if ($is_dev === null) {
			$is_dev = defined('WP_DEBUG') && WP_DEBUG;
		}

		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html($context) . '</strong> (' . esc_html($context_key) . ': <code>' . esc_html($context_value) . '</code>)</p>';
		echo '<p>' . esc_html($e->getMessage()) . '</p>';
		if ($is_dev) {
			echo '<p><small>' . esc_html($e->getFile()) . ':' . esc_html((string) $e->getLine()) . '</small></p>';
			echo '<details><summary>Stack Trace</summary><pre style="overflow:auto;max-height:300px;font-size:11px;">' . esc_html($e->getTraceAsString()) . '</pre></details>';
		}
		echo '</div>';
	}

	/**
	 * Render a full error page (with wrap and h1).
	 *
	 * @param \Throwable $e       The exception or error to display.
	 * @param string     $title   Page title (h1).
	 * @param string     $context Context label for the notice.
	 * @param bool|null  $is_dev  Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function renderPage(\Throwable $e, string $title, string $context, ?bool $is_dev = null): void {
		if ($is_dev === null) {
			$is_dev = defined('WP_DEBUG') && WP_DEBUG;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html($title) . '</h1>';
		self::render($e, $context, $is_dev);
		if (!$is_dev) {
			echo '<p>This settings page is temporarily unavailable due to a configuration error.</p>';
			echo '<p>Please contact the site administrator if this problem persists.</p>';
		}
		echo '</div>';
	}
}
