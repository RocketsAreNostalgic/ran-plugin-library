<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

interface FormsValidatorServiceInterface {
	/**
	 * @param array<string,mixed> $field_context
	 */
	public function inject_component_validators(string $field_id, string $component, array $field_context = array()): void;

	/**
	 * @return array<string, array<int, callable>>
	 */
	public function drain_queued_component_validators(): array;

	/**
	 * @param array<string,mixed> $field_context
	 */
	public function inject_component_sanitizers(string $field_id, string $component, array $field_context = array()): void;

	/**
	 * @return array<string, array<int, callable>>
	 */
	public function drain_queued_component_sanitizers(): array;

	/**
	 * @param array<string, array<string, mixed>> $bucketedSchema
	 * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<int, callable>>}
	 */
	public function consume_component_validator_queue(array $bucketedSchema): array;

	/**
	 * @param array<string, array<string, mixed>> $bucketedSchema
	 * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<int, callable>>}
	 */
	public function consume_component_sanitizer_queue(array $bucketedSchema): array;
}
