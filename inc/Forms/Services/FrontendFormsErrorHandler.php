<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Psr\Log\LoggerInterface;

class FrontendFormsErrorHandler implements FormsErrorHandlerInterface {
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

		if ($is_admin()) {
			return;
		}

		$is_dev = (bool) $is_dev_environment();
		if (!$is_dev) {
			return;
		}

		$add_action('wp_footer', function () use ($e, $hook, $class_name): void {
			$title          = htmlspecialchars('Frontend Forms Builder Error', ENT_QUOTES, 'UTF-8');
			$hookLabel      = htmlspecialchars($hook, ENT_QUOTES, 'UTF-8');
			$classLabel     = htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8');
			$exceptionClass = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
			$message        = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
			$file           = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
			$line           = (int) $e->getLine();
			$trace          = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

			echo '<div style="border:1px solid #cc1818;background:#fff5f5;color:#111;padding:12px;margin:12px 0;font-family:monospace;">';
			echo '<div style="font-weight:bold;margin-bottom:6px;">' . $title . '</div>';
			echo '<div><strong>Class:</strong> ' . $classLabel . '</div>';
			echo '<div><strong>Hook:</strong> ' . $hookLabel . '</div>';
			echo '<div><strong>Exception:</strong> ' . $exceptionClass . '</div>';
			echo '<div><strong>Message:</strong> ' . $message . '</div>';
			echo '<div><strong>Location:</strong> ' . $file . ':' . $line . '</div>';
			echo '<pre style="white-space:pre-wrap;max-height:260px;overflow:auto;margin:8px 0 0;">' . $trace . '</pre>';
			echo '</div>';
		});
	}

	public function register_admin_menu_fallback_pages(
		array $page_slugs,
		bool $is_dev,
		callable $add_menu_page,
		callable $add_submenu_page,
		callable $did_action,
		callable $add_action
	): void {
		// no-op for frontend
	}
}
