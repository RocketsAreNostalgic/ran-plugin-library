<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Psr\Log\LoggerInterface;

class AdminFormsErrorHandler implements FormsErrorHandlerInterface {
	public function handle_builder_error(
		\Throwable $e,
		string $hook,
		LoggerInterface $logger,
		string $class_name,
		callable $is_dev_environment,
		callable $is_admin,
		callable $current_user_can,
		callable $add_action,
		callable $register_fallback_pages
	): void {
		$context = array(
			'hook'  => $hook,
			'class' => $class_name,
			'file'  => $e->getFile(),
			'line'  => $e->getLine(),
			'trace' => $e->getTraceAsString(),
		);

		$logger->error(
			sprintf('Settings builder error on %s hook: %s', $hook, $e->getMessage()),
			$context
		);

		if (!$is_admin() || !$current_user_can('manage_options')) {
			return;
		}

		$is_dev = (bool) $is_dev_environment();

		if ($is_dev) {
			$add_action('admin_notices', function () use ($e, $hook): void {
				ErrorNoticeRenderer::renderWithContext($e, 'Settings Builder Error', 'hook', $hook);
			});
		}

		$register_fallback_pages($e, $hook, $is_dev);
	}

	public function register_admin_menu_fallback_pages(
		array $page_slugs,
		bool $is_dev,
		callable $add_menu_page,
		callable $add_submenu_page,
		callable $did_action,
		callable $add_action
	): void {
		if (empty($page_slugs)) {
			return;
		}

		$main_slug = array_shift($page_slugs);

		$register_pages = function () use ($main_slug, $page_slugs, $is_dev, $add_menu_page, $add_submenu_page): void {
			$render_error = function () use ($is_dev): void {
				ErrorNoticeRenderer::renderFallbackPage('Settings Builder Errors', 'Settings Unavailable', $is_dev);
			};

			$add_menu_page(
				$is_dev ? 'Settings Error' : 'Settings',
				$is_dev ? 'Settings Error' : 'Settings',
				'manage_options',
				$main_slug,
				$render_error,
				$is_dev ? 'dashicons-warning' : 'dashicons-admin-generic',
				999
			);

			foreach ($page_slugs as $slug) {
				$add_submenu_page(
					$main_slug,
					$is_dev ? 'Settings Error' : 'Settings',
					$is_dev ? 'Settings Error' : 'Settings',
					'manage_options',
					$slug,
					$render_error
				);
			}
		};

		if ($did_action('admin_menu')) {
			$register_pages();
			return;
		}

		$add_action('admin_menu', $register_pages, 999);
	}
}
