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
	 * Generic production message for page-level errors.
	 */
	private const PAGE_ERROR_MESSAGE = 'This settings page is temporarily unavailable due to a configuration error. Please contact the site administrator if this problem persists.';

	/**
	 * Generic production message for field-level errors.
	 */
	private const FIELD_ERROR_MESSAGE = 'This field could not be rendered. Please contact the site administrator.';

	/**
	 * Short production message for inline errors.
	 */
	private const INLINE_ERROR_MESSAGE = 'Settings Unavailable — Please contact the site administrator.';

	/**
	 * Check if we're in development mode.
	 *
	 * Centralizes the WP_DEBUG check so it's consistent across all error handling.
	 *
	 * @return bool
	 */
	public static function isDevMode(): bool {
		return defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * Get the appropriate field-level error message based on dev mode.
	 *
	 * In dev mode, returns the detailed message. In production, returns generic message.
	 *
	 * @param string    $dev_message Detailed message to show in dev mode.
	 * @param bool|null $is_dev      Whether in dev mode. Defaults to WP_DEBUG.
	 * @return string
	 */
	public static function getFieldErrorMessage(string $dev_message = '', ?bool $is_dev = null): string {
		$is_dev = $is_dev ?? self::isDevMode();
		return $is_dev && $dev_message !== '' ? $dev_message : self::FIELD_ERROR_MESSAGE;
	}

	/**
	 * Get the appropriate page-level error message based on dev mode.
	 *
	 * In dev mode, returns the detailed message. In production, returns generic message.
	 *
	 * @param string    $dev_message Detailed message to show in dev mode.
	 * @param bool|null $is_dev      Whether in dev mode. Defaults to WP_DEBUG.
	 * @return string
	 */
	public static function getPageErrorMessage(string $dev_message = '', ?bool $is_dev = null): string {
		$is_dev = $is_dev ?? self::isDevMode();
		return $is_dev && $dev_message !== '' ? $dev_message : self::PAGE_ERROR_MESSAGE;
	}

	/**
	 * Render a simple error notice for missing/unknown resources.
	 *
	 * Used by AdminSettings and UserSettings when a page/collection doesn't exist.
	 *
	 * @param string    $dev_message Detailed message to show in dev mode.
	 * @param bool|null $is_dev      Whether in dev mode. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function renderSimpleNotice(string $dev_message, ?bool $is_dev = null): void {
		echo '<div class="notice notice-error"><p><strong>Settings Error</strong></p>';
		echo '<p>' . esc_html(self::getPageErrorMessage($dev_message, $is_dev)) . '</p>';
		echo '</div>';
	}

	/**
	 * Render an error notice div.
	 *
	 * @param \Throwable $e       The exception or error to display.
	 * @param string     $context Context label (e.g., 'AdminMenuRegistry Error').
	 * @param bool|null  $is_dev  Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function render(\Throwable $e, string $context = 'Error', ?bool $is_dev = null): void {
		$is_dev = $is_dev ?? self::isDevMode();

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
		$is_dev = $is_dev ?? self::isDevMode();

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
		$is_dev = $is_dev ?? self::isDevMode();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html($title) . '</h1>';
		self::render($e, $context, $is_dev);
		if (!$is_dev) {
			echo '<p>' . esc_html(self::PAGE_ERROR_MESSAGE) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render a fallback page wrapper (when error details are shown elsewhere via admin_notices).
	 *
	 * Use this when the full error is already displayed via admin_notices and you just
	 * need a simple page wrapper with a title and optional production message.
	 *
	 * @param string    $dev_title  Title to show in dev mode.
	 * @param string    $prod_title Title to show in production.
	 * @param bool|null $is_dev     Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function renderFallbackPage(string $dev_title, string $prod_title, ?bool $is_dev = null): void {
		$is_dev = $is_dev ?? self::isDevMode();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html($is_dev ? $dev_title : $prod_title) . '</h1>';
		if (!$is_dev) {
			echo '<p>' . esc_html(self::PAGE_ERROR_MESSAGE) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render an inline error placeholder (for user profile pages, etc.).
	 *
	 * @param string    $dev_message  Message to show in dev mode.
	 * @param bool|null $is_dev       Whether to show dev details. Defaults to WP_DEBUG.
	 * @return void
	 */
	public static function renderInlinePlaceholder(string $dev_message, ?bool $is_dev = null): void {
		$is_dev = $is_dev ?? self::isDevMode();

		echo '<div class="user-settings-error" style="margin: 20px 0; padding: 12px 15px; background: #fff; border-left: 4px solid #dc3232;">';
		if ($is_dev) {
			echo '<strong>' . esc_html($dev_message) . '</strong>';
		} else {
			echo '<strong>' . esc_html(self::INLINE_ERROR_MESSAGE) . '</strong>';
		}
		echo '</div>';
	}
}
