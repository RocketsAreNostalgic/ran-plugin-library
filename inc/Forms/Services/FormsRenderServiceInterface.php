<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

interface FormsRenderServiceInterface {
	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $element_context
	 */
	public function finalize_render(string $container_id, array $payload, array $element_context = array()): void;

	/**
	 * @param array<int|string, mixed> $sections
	 * @param array<string,mixed> $values
	 */
	public function render_default_sections_wrapper(string $id_slug, array $sections, array $values): string;

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $values
	 */
	public function render_group_wrapper(array $group, string $fields_content, string $before_content, string $after_content, array $values): string;

	/**
	 * @param array<string,mixed> $field_item
	 * @param array<string,mixed> $values
	 */
	public function render_default_field_wrapper(array $field_item, array $values): string;

	public function render_default_field_wrapper_warning(string $message): string;

	/**
	 * @param array<string,mixed> $context
	 */
	public function render_callback_output(?callable $callback, array $context): ?string;

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $context
	 */
	public function render_raw_html_content(array $field, array $context): string;

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $context
	 */
	public function render_hr_content(array $field, array $context): string;

	public function container_has_file_uploads(string $container_id): bool;
}
