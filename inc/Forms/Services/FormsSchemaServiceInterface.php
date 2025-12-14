<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Options\RegisterOptions;

interface FormsSchemaServiceInterface {
	/**
	 * @param array<string,mixed> $context
	 * @return array{
	 *     schema: array<string,array>,
	 *     defaults: array<string,array{default:mixed}>,
	 *     bucketed_schema: array<string,array>,
	 *     metadata: array<string,array<string,mixed>>,
	 *     queued_validators: array<string,array<int,callable>>,
	 *     queued_sanitizers: array<string,array<int,callable>>
	 * }
	 */
	public function resolve_schema_bundle(RegisterOptions $options, array $context = array()): array;

	/**
	 * @param array $bundle Schema bundle from resolve_schema_bundle().
	 * @return array{
	 *     merged_schema: array<string, array>,
	 *     metadata: array<string, array<string, mixed>>,
	 *     queued_validators: array<string, array<int, callable>>,
	 *     queued_sanitizers: array<string, array<int, callable>>,
	 *     defaults_for_seeding: array<string, array>
	 * }
	 */
	public function merge_schema_bundle_sources(array $bundle): array;

	/**
	 * @param array $existing The existing schema entry.
	 * @param array $incoming The incoming schema entry to merge.
	 * @return array The merged schema entry.
	 */
	public function merge_schema_entry_buckets(array $existing, array $incoming): array;

	/**
	 * @param FormsServiceSession $session
	 * @return array{
	 *     schema: array<string, array{
	 *         sanitize: array{component: array<int, callable>, schema: array<int, callable>},
	 *         validate: array{component: array<int, callable>, schema: array<int, callable>},
	 *         default?: mixed
	 *     }>,
	 *     metadata: array<string, array<string, mixed>>,
	 *     queued_validators: array<string, array<int, callable>>,
	 *     queued_sanitizers: array<string, array<int, callable>>
	 * }
	 */
	public function assemble_initial_bucketed_schema(FormsServiceSession $session): array;
}
