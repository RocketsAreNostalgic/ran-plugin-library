<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Psr\Log\LoggerInterface;

interface FormsErrorHandlerInterface {
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
	): void;

	/**
	 * @param array<string> $page_slugs
	 */
	public function register_admin_menu_fallback_pages(
		array $page_slugs,
		bool $is_dev,
		callable $add_menu_page,
		callable $add_submenu_page,
		callable $did_action,
		callable $add_action
	): void;
}
