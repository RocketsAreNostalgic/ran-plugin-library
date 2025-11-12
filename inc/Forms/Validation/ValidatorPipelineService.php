<?php
/**
 * ValidatorPipelineService
 *
 * Provides reusable sanitizer/validator bucket management for form pipelines.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Validation;

use Ran\PluginLib\Util\Logger;

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

		$logger->debug("{$hostLabel}: _coerce_schema_entry completed", array('key' => $optionKey, 'normalized' => $normalized));
		return $normalized;
	}

	/**
	 * @param array{component:array<callable>,schema:array<callable>} $existing
	 * @param array{component:array<callable>,schema:array<callable>} $incoming
	 * @return array{component:array<callable>,schema:array<callable>}
	 */
	public function merge_bucketed_callables(array $existing, array $incoming): array {
		$merged                         = $this->create_bucket_map();
		$merged[self::BUCKET_COMPONENT] = array_merge($existing[self::BUCKET_COMPONENT], $incoming[self::BUCKET_COMPONENT]);
		$merged[self::BUCKET_SCHEMA]    = array_merge($existing[self::BUCKET_SCHEMA], $incoming[self::BUCKET_SCHEMA]);

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
				$logger->debug("{$hostLabel}: running sanitizer", array(
					'key'       => $normalizedKey,
					'bucket'    => $bucket,
					'index'     => $index,
					'sanitizer' => $sanitizerDesc,
				));

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

		foreach (self::BUCKET_ORDER as $bucket) {
			foreach ($rules['validate'][$bucket] as $index => $validator) {
				if (!\is_callable($validator)) {
					$logger->warning("{$hostLabel}: _sanitize_and_validate_option runtime non-callable validator", array('key' => $normalizedKey, 'index' => $index));
					throw new \InvalidArgumentException("{$hostLabel}: Schema for key '{$normalizedKey}' has non-callable validator at index {$index} at runtime.");
				}

				$validatorDesc = $describeCallable($validator);
				$logger->debug("{$hostLabel}: running validator", array(
					'key'       => $normalizedKey,
					'bucket'    => $bucket,
					'index'     => $index,
					'validator' => $validatorDesc,
				));

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

					$logger->debug("{$hostLabel}: validation failed, stopping validator chain", array('key' => $normalizedKey, 'validator' => $validatorDesc, 'bucket' => $bucket));

					return $value;
				}
			}
		}

		$logger->debug("{$hostLabel}: _sanitize_and_validate_option completed", array('key' => $normalizedKey));
		return $value;
	}
}
