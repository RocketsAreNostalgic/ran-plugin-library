<?php
/**
 * ValidatorPipelineService
 *
 * Provides reusable sanitizer/validator bucket management for form pipelines.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Validation;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;

final class ValidatorPipelineService {
	public const BUCKET_COMPONENT           = 'component';
	public const BUCKET_SCHEMA              = 'schema';
	public const CLOSURE_PLACEHOLDER_PREFIX = '@closure ';
	/**
	 * @var string[]
	 */
	public const BUCKET_ORDER = array(self::BUCKET_COMPONENT, self::BUCKET_SCHEMA);

	/**
	 * @return array{component:array<int,callable>,schema:array<int,callable>}
	 */
	public function create_bucket_map(): array {
		return array(
			self::BUCKET_COMPONENT => array(),
			self::BUCKET_SCHEMA    => array(),
		);
	}

	/**
	 * Summarize callable buckets without retaining raw closures in logs.
	 *
	 * @param array{component:array<int,callable>,schema:array<int,callable>} $bucketMap
	 * @return array{component:array{count:int,descriptors:array<int,string>},schema:array{count:int,descriptors:array<int,string>}}
	 */
	private function summarize_bucket_map(array $bucketMap): array {
		return array(
			self::BUCKET_COMPONENT => $this->summarize_callable_list($bucketMap[self::BUCKET_COMPONENT] ?? array()),
			self::BUCKET_SCHEMA    => $this->summarize_callable_list($bucketMap[self::BUCKET_SCHEMA] ?? array()),
		);
	}

	/**
	 * Summarize default entry for safe logging.
	 *
	 * @param array{sanitize:array{component:array, schema:array}, validate:array{component:array, schema:array}, default?:mixed} $normalized
	 * @return array{has_default:bool,type?:string,value?:string,callable?:string}
	 */
	private function summarize_default(array $normalized): array {
		if (!array_key_exists('default', $normalized)) {
			return array('has_default' => false);
		}

		$default = $normalized['default'];
		$summary = array('has_default' => true, 'type' => gettype($default));

		if (
			is_string($default) || is_int($default) || is_float($default) || is_bool($default)
		) {
			$summary['value'] = is_bool($default) ? ($default ? 'true' : 'false') : (string) $default;
			return $summary;
		}

		if (is_callable($default)) {
			$summary['callable'] = $this->describe_callable($default);
			return $summary;
		}

		if (is_array($default)) {
			$summary['count'] = count($default);
			return $summary;
		}

		if (is_object($default)) {
			$summary['class'] = get_class($default);
		}

		return $summary;
	}

	/**
	 * @param array<int, callable> $callables
	 * @return array{count:int,descriptors:array<int,string>}
	 */
	private function summarize_callable_list(array $callables): array {
		$count       = count($callables);
		$descriptors = array();
		$limit       = 5;
		foreach ($callables as $idx => $callable) {
			if ($idx >= $limit) {
				break;
			}
			$descriptors[] = $this->describe_callable($callable);
		}

		return array(
			'count'       => $count,
			'descriptors' => $descriptors,
		);
	}

	private function describe_callable(callable $callable): string {
		if (is_string($callable)) {
			return $callable;
		}

		if (is_array($callable)) {
			$target = $callable[0] ?? null;
			$method = $callable[1] ?? 'unknown';
			$target = is_object($target) ? get_class($target) : (string) $target;
			return $target . '::' . (string) $method;
		}

		if ($callable instanceof \Closure) {
			if (function_exists('spl_object_id')) {
				return 'Closure#' . spl_object_id($callable);
			}

			if (function_exists('spl_object_hash')) {
				return 'Closure#' . spl_object_hash($callable);
			}

			return 'Closure';
		}

		if (is_object($callable) && method_exists($callable, '__invoke')) {
			return get_class($callable) . '::__invoke';
		}

		return 'callable';
	}

	public function is_bucket_map(array $candidate): bool {
		return array_key_exists(self::BUCKET_COMPONENT, $candidate) || array_key_exists(self::BUCKET_SCHEMA, $candidate);
	}

	/**
	 * @param callable|array<callable>|null $callables
	 * @return array<int,callable>
	 */
	public function normalize_callable_field($callables, string $field, string $optionKey, string $hostLabel): array {
		if ($callables === null) {
			return array();
		}

		if (\is_string($callables) && str_starts_with($callables, self::CLOSURE_PLACEHOLDER_PREFIX)) {
			return array();
		}

		if (\is_callable($callables)) {
			return array($callables);
		}

		if (!\is_array($callables)) {
			throw new \InvalidArgumentException("{$hostLabel}: Schema for key '{$optionKey}' has non-array '{$field}'.");
		}

		foreach ($callables as $index => $callable) {
			if (\is_string($callable) && str_starts_with($callable, self::CLOSURE_PLACEHOLDER_PREFIX)) {
				continue;
			}
			if (!\is_callable($callable)) {
				throw new \InvalidArgumentException("{$hostLabel}: Schema for key '{$optionKey}' has non-callable {$field} at index {$index}.");
			}
		}

		return array_values(array_filter($callables, static function ($callable): bool {
			return !(\is_string($callable) && str_starts_with($callable, self::CLOSURE_PLACEHOLDER_PREFIX));
		}));
	}

	/**
	 * @return array{sanitize:array{component:array<int,callable>,schema:array<int,callable>}, validate:array{component:array<int,callable>,schema:array<int,callable>}, default?:mixed}
	 */
	public function normalize_schema_entry(array $entry, string $optionKey, string $hostLabel, Logger $logger): array {
		$normalized = array(
			'sanitize' => $this->create_bucket_map(),
			'validate' => $this->create_bucket_map(),
		);

		if (array_key_exists('sanitize', $entry)) {
			if (\is_array($entry['sanitize']) && $this->is_bucket_map($entry['sanitize'])) {
				foreach (self::BUCKET_ORDER as $bucket) {
					if (array_key_exists($bucket, $entry['sanitize'])) {
						$normalized['sanitize'][$bucket] = $this->normalize_callable_field($entry['sanitize'][$bucket], 'sanitize', $optionKey, $hostLabel);
					}
				}
			} else {
				$normalized['sanitize'][self::BUCKET_SCHEMA] = $this->normalize_callable_field($entry['sanitize'], 'sanitize', $optionKey, $hostLabel);
			}
		}

		if (array_key_exists('validate', $entry)) {
			if (\is_array($entry['validate']) && $this->is_bucket_map($entry['validate'])) {
				foreach (self::BUCKET_ORDER as $bucket) {
					if (array_key_exists($bucket, $entry['validate'])) {
						$normalized['validate'][$bucket] = $this->normalize_callable_field($entry['validate'][$bucket], 'validate', $optionKey, $hostLabel);
					}
				}
			} else {
				$normalized['validate'][self::BUCKET_SCHEMA] = $this->normalize_callable_field($entry['validate'], 'validate', $optionKey, $hostLabel);
			}
		}

		if (array_key_exists('default', $entry)) {
			$normalized['default'] = $entry['default'];
		}

		// Only log per-entry coercion in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$logger->debug("{$hostLabel}: _coerce_schema_entry completed", array(
				'key'              => $optionKey,
				'sanitize_summary' => $this->summarize_bucket_map($normalized['sanitize']),
				'validate_summary' => $this->summarize_bucket_map($normalized['validate']),
				'default_summary'  => $this->summarize_default($normalized)
			));
		}
		return $normalized;
	}

	/**
	 * @param array{component:array<callable>,schema:array<callable>} $existing
	 * @param array{component:array<callable>,schema:array<callable>} $incoming
	 * @return array{component:array<callable>,schema:array<callable>}
	 */
	public function merge_bucketed_callables(array $existing, array $incoming): array {
		$merged = $this->create_bucket_map();

		// Component validators always merge (they come from different sources)
		$merged[self::BUCKET_COMPONENT] = array_merge($existing[self::BUCKET_COMPONENT], $incoming[self::BUCKET_COMPONENT]);

		// Schema validators: deduplicate using stable keys based on closure definition location
		// This prevents duplicate validators when the same schema is registered multiple times
		$schemaValidators = array();
		foreach ($existing[self::BUCKET_SCHEMA] as $validator) {
			$key                    = $this->_get_callable_identity_key($validator);
			$schemaValidators[$key] = $validator;
		}
		foreach ($incoming[self::BUCKET_SCHEMA] as $validator) {
			$key = $this->_get_callable_identity_key($validator);
			// Only add if not already present (deduplication)
			if (!isset($schemaValidators[$key])) {
				$schemaValidators[$key] = $validator;
			}
		}
		$merged[self::BUCKET_SCHEMA] = array_values($schemaValidators);

		return $merged;
	}

	/**
	 * Generate a stable identity key for a callable.
	 *
	 * For closures, uses ReflectionFunction to get file+line which is stable
	 * across multiple instantiations of the same closure definition.
	 * This allows deduplication without requiring developers to cache their schema.
	 *
	 * @param callable $callable The callable to generate a key for.
	 * @return string A stable identity key.
	 */
	private function _get_callable_identity_key(callable $callable): string {
		if ($callable instanceof \Closure) {
			try {
				$ref = new \ReflectionFunction($callable);
				// Use file + start line + end line as stable identity
				return $ref->getFileName() . ':' . $ref->getStartLine() . '-' . $ref->getEndLine();
			} catch (\ReflectionException $e) {
				// Fall back to object hash if reflection fails
				return spl_object_hash($callable);
			}
		}

		// For other callables (strings, arrays), serialize for comparison
		if (is_string($callable)) {
			return 'fn:' . $callable;
		}

		if (is_array($callable)) {
			// [class, method] or [object, method]
			$class = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];
			return 'method:' . $class . '::' . $callable[1];
		}

		// Fallback for invokable objects
		if (is_object($callable)) {
			return 'invokable:' . get_class($callable);
		}

		return md5(serialize($callable));
	}

	/**
	 * Coerce a schema entry to canonical bucket structure.
	 *
	 * Transforms flat callable arrays into the canonical bucket structure with
	 * 'component' and 'schema' sub-arrays. When $flatAsComponent is true, flat
	 * callables are placed in the 'component' bucket (for manifest defaults);
	 * when false, they go to the 'schema' bucket (for developer schema).
	 *
	 * @param array  $source          Source schema fragment.
	 * @param bool   $flatAsComponent If true, flat arrays go to 'component' bucket; else 'schema'.
	 * @param Logger $logger          Logger instance for debug output.
	 * @return array{
	 *     sanitize: array{component: array<int, callable>, schema: array<int, callable>},
	 *     validate: array{component: array<int, callable>, schema: array<int, callable>},
	 *     context?: array,
	 *     default?: mixed
	 * }
	 */
	public function coerce_to_bucket_structure(array $source, bool $flatAsComponent, Logger $logger): array {
		// Use existing normalize_schema_entry for the heavy lifting
		$normalized = $this->normalize_schema_entry($source, 'coerce-bucket', 'ValidatorPipelineService', $logger);

		// When flatAsComponent is true, move schema bucket contents to component bucket
		// This handles the case where manifest defaults have flat callables that should
		// be treated as component-level validators
		if ($flatAsComponent) {
			foreach (array('sanitize', 'validate') as $bucket) {
				if (
					isset($normalized[$bucket][self::BUCKET_COMPONENT], $normalized[$bucket][self::BUCKET_SCHEMA])
					&& $normalized[$bucket][self::BUCKET_COMPONENT] === array()
					&& $normalized[$bucket][self::BUCKET_SCHEMA] !== array()
				) {
					$normalized[$bucket][self::BUCKET_COMPONENT] = $normalized[$bucket][self::BUCKET_SCHEMA];
					$normalized[$bucket][self::BUCKET_SCHEMA]    = array();
				}
			}
		}

		// Preserve context if present in source
		if (isset($source['context']) && is_array($source['context'])) {
			$normalized['context'] = $source['context'];
		}

		return $normalized;
	}

	/**
	 * Merge component defaults with developer schema.
	 *
	 * Coerces both inputs to bucket structure, then merges with proper precedence:
	 * - Defaults go to 'component' bucket (flatAsComponent = true)
	 * - Schema goes to 'schema' bucket (flatAsComponent = false)
	 * - Component buckets are merged (defaults first, then schema additions)
	 * - Schema bucket: schema overrides defaults if non-empty
	 * - Context arrays are merged (schema overrides defaults)
	 * - Default value from schema takes precedence if both present
	 *
	 * @param array  $defaults Component defaults (flat callables → component bucket).
	 * @param array  $schema   Developer schema (flat callables → schema bucket).
	 * @param Logger $logger   Logger instance for debug output.
	 * @return array{
	 *     sanitize: array{component: array<int, callable>, schema: array<int, callable>},
	 *     validate: array{component: array<int, callable>, schema: array<int, callable>},
	 *     context?: array,
	 *     default?: mixed
	 * }
	 */
	public function merge_schema_with_defaults(array $defaults, array $schema, Logger $logger): array {
		$defaultBuckets = $this->coerce_to_bucket_structure($defaults, true, $logger);
		$schemaBuckets  = $this->coerce_to_bucket_structure($schema, false, $logger);

		$merged = array(
			'sanitize' => array(
				self::BUCKET_COMPONENT => array_merge(
					$defaultBuckets['sanitize'][self::BUCKET_COMPONENT],
					$schemaBuckets['sanitize'][self::BUCKET_COMPONENT]
				),
				self::BUCKET_SCHEMA => $schemaBuckets['sanitize'][self::BUCKET_SCHEMA] !== array()
					? $schemaBuckets['sanitize'][self::BUCKET_SCHEMA]
					: $defaultBuckets['sanitize'][self::BUCKET_SCHEMA],
			),
			'validate' => array(
				self::BUCKET_COMPONENT => array_merge(
					$defaultBuckets['validate'][self::BUCKET_COMPONENT],
					$schemaBuckets['validate'][self::BUCKET_COMPONENT]
				),
				self::BUCKET_SCHEMA => $schemaBuckets['validate'][self::BUCKET_SCHEMA] !== array()
					? $schemaBuckets['validate'][self::BUCKET_SCHEMA]
					: $defaultBuckets['validate'][self::BUCKET_SCHEMA],
			),
		);

		// Merge context (schema overrides defaults)
		$defaultContext = $defaultBuckets['context'] ?? array();
		$schemaContext  = $schemaBuckets['context']  ?? array();
		if ($defaultContext !== array() || $schemaContext !== array()) {
			$merged['context'] = $defaultContext === array()
				? $schemaContext
				: array_merge($defaultContext, $schemaContext);
		}

		// Default value: schema takes precedence
		if (array_key_exists('default', $schemaBuckets)) {
			$merged['default'] = $schemaBuckets['default'];
		} elseif (array_key_exists('default', $defaultBuckets)) {
			$merged['default'] = $defaultBuckets['default'];
		}

		// Only log per-merge completion in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$logger->debug('ValidatorPipelineService: merge_schema_with_defaults completed', array(
				'default_sanitize_component' => count($defaultBuckets['sanitize'][self::BUCKET_COMPONENT]),
				'default_validate_component' => count($defaultBuckets['validate'][self::BUCKET_COMPONENT]),
				'schema_sanitize_schema'     => count($schemaBuckets['sanitize'][self::BUCKET_SCHEMA]),
				'schema_validate_schema'     => count($schemaBuckets['validate'][self::BUCKET_SCHEMA]),
				'merged_sanitize_component'  => count($merged['sanitize'][self::BUCKET_COMPONENT]),
				'merged_validate_component'  => count($merged['validate'][self::BUCKET_COMPONENT]),
			));
		}

		return $merged;
	}

	/**
	 * @param array{sanitize:array{component:array<callable>,schema:array<callable>}, validate:array{component:array<callable>,schema:array<callable>}} $rules
	 * @param callable(callable):string $describeCallable
	 * @param callable(mixed):string $stringifyValueForError
	 * @param callable(string,string):void $recordNotice
	 * @param callable(string,string):void $recordWarning
	 */
	public function sanitize_and_validate(
		string $normalizedKey,
		mixed $value,
		array $rules,
		string $hostLabel,
		Logger $logger,
		callable $describeCallable,
		callable $stringifyValueForError,
		callable $recordNotice,
		callable $recordWarning
	): mixed {
		foreach (self::BUCKET_ORDER as $bucket) {
			foreach ($rules['sanitize'][$bucket] as $index => $sanitizer) {
				if (!\is_callable($sanitizer)) {
					$logger->warning("{$hostLabel}: _sanitize_and_validate_option runtime non-callable sanitizer", array('key' => $normalizedKey, 'index' => $index));
					throw new \InvalidArgumentException("{$hostLabel}: Schema for key '{$normalizedKey}' has non-callable sanitizer at index {$index} at runtime.");
				}

				$sanitizerDesc = $describeCallable($sanitizer);
				// Only log per-sanitizer execution in verbose mode to avoid log flooding
				if (ErrorNoticeRenderer::isVerboseDebug()) {
					$logger->debug("{$hostLabel}: running sanitizer", array(
						'key'       => $normalizedKey,
						'bucket'    => $bucket,
						'index'     => $index,
						'sanitizer' => $sanitizerDesc,
					));
				}

				try {
					$firstPass = $sanitizer($value, function (string $message) use ($recordNotice, $normalizedKey): void {
						$recordNotice($normalizedKey, $message);
					});
					$secondPass = $sanitizer($firstPass, static function (): void {
						// No-op emitter to avoid duplicating notices during idempotence check.
					});
				} catch (\ArgumentCountError $e) {
					$firstPass  = $sanitizer($value);
					$secondPass = $sanitizer($firstPass);
				}

				if ($secondPass !== $firstPass) {
					$valStr1 = $stringifyValueForError($firstPass);
					$valStr2 = $stringifyValueForError($secondPass);
					$logger->warning("{$hostLabel}: _sanitize_and_validate_option sanitizer not idempotent", array('key' => $normalizedKey, 'sanitizer' => $sanitizerDesc, 'valStr1' => $valStr1, 'valStr2' => $valStr2));
					throw new \InvalidArgumentException(
						"{$hostLabel}: Sanitizer for option '{$normalizedKey}' at index {$index} must be idempotent. First result {$valStr1} differs from second {$valStr2}. Sanitizer {$sanitizerDesc}."
					);
				}

				$value = $firstPass;
			}
		}

		// Run all validators and accumulate messages (no fail-fast)
		$anyValidationFailed = false;

		foreach (self::BUCKET_ORDER as $bucket) {
			foreach ($rules['validate'][$bucket] as $index => $validator) {
				if (!\is_callable($validator)) {
					$logger->warning("{$hostLabel}: _sanitize_and_validate_option runtime non-callable validator", array('key' => $normalizedKey, 'index' => $index));
					throw new \InvalidArgumentException("{$hostLabel}: Schema for key '{$normalizedKey}' has non-callable validator at index {$index} at runtime.");
				}

				$validatorDesc = $describeCallable($validator);
				// Only log per-validator execution in verbose mode to avoid log flooding
				if (ErrorNoticeRenderer::isVerboseDebug()) {
					$logger->debug("{$hostLabel}: running validator", array(
						'key'       => $normalizedKey,
						'bucket'    => $bucket,
						'index'     => $index,
						'validator' => $validatorDesc,
					));
				}

				$messageRecorded = false;
				try {
					$valid = $validator($value, function (string $message) use ($recordWarning, $normalizedKey, &$messageRecorded): void {
						$recordWarning($normalizedKey, $message);
						$messageRecorded = true;
					});
				} catch (\ArgumentCountError $e) {
					$valid = $validator($value);
				}

				if ($valid !== true && $valid !== false) {
					$valStr  = $stringifyValueForError($value);
					$gotType = gettype($valid);
					$logger->warning("{$hostLabel}: _sanitize_and_validate_option validator returned non-bool", array('key' => $normalizedKey, 'value' => $value, 'validator' => $validatorDesc, 'gotType' => $gotType));
					throw new \InvalidArgumentException(
						"{$hostLabel}: Validator for option '{$normalizedKey}' at index {$index} must return strict bool; got {$gotType}. Value {$valStr}; validator {$validatorDesc}."
					);
				}

				if ($valid !== true) {
					$valStr = $stringifyValueForError($value);
					if (!$messageRecorded) {
						$recordWarning($normalizedKey, "Validation failed for value {$valStr}");
					}

					$logger->debug("{$hostLabel}: validation failed", array('key' => $normalizedKey, 'validator' => $validatorDesc, 'bucket' => $bucket));
					$anyValidationFailed = true;
					// Continue to run remaining validators to accumulate all messages
				}
			}
		}

		// Only log per-option completion in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$logger->debug("{$hostLabel}: _sanitize_and_validate_option completed", array('key' => $normalizedKey, 'validation_failed' => $anyValidationFailed));
		}
		return $value;
	}
}
