<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Options\RegisterOptions;

interface FormsMessageServiceInterface {
	/**
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function take_messages(): array;

	/**
	 * @param array<string,mixed> $payload
	 */
	public function prepare_validation_messages(array $payload): void;

	/**
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function process_validation_messages(RegisterOptions $options): array;

	public function has_validation_failures(): bool;

	public function clear_pending_validation(): void;

	/**
	 * @param array<string,mixed> $context
	 */
	public function log_validation_failure(string $message, array $context = array(), string $level = 'info'): void;

	/**
	 * @param array<string,mixed> $context
	 */
	public function log_validation_success(string $message, array $context = array(), string $level = 'debug'): void;

	public function get_form_messages_transient_key(?int $user_id = null): string;

	/**
	 * @param array<string, array{warnings: array<int, string>, notices: array<int, string>}> $messages
	 */
	public function persist_form_messages(array $messages, ?int $user_id = null): void;

	public function restore_form_messages(?int $user_id = null): bool;

	/**
	 * @return array{warnings: array<int, string>, notices: array<int, string>}
	 */
	public function get_messages_for_field(string $field_id): array;
}
