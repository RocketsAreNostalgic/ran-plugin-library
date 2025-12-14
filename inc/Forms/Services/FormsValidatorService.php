<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;

class FormsValidatorService implements FormsValidatorServiceInterface {
	/** @var array<string, array<int, callable>> */
	private array $queued_component_validators;

	/** @var array<string, array<int, callable>> */
	private array $queued_component_sanitizers;

	/**
	 * @param array<string, array<int, callable>> $queued_component_validators
	 * @param array<string, array<int, callable>> $queued_component_sanitizers
	 */
	public function __construct(
		private RegisterOptions $base_options,
		private ComponentManifest $components,
		private Logger $logger,
		array &$queued_component_validators,
		array &$queued_component_sanitizers
	) {
		$this->queued_component_validators = & $queued_component_validators;
		$this->queued_component_sanitizers = & $queued_component_sanitizers;
	}

	public static function create_with_internal_state(
		RegisterOptions $base_options,
		ComponentManifest $components,
		Logger $logger
	): self {
		$queued_component_validators = array();
		$queued_component_sanitizers = array();
		return new self($base_options, $components, $logger, $queued_component_validators, $queued_component_sanitizers);
	}

	public function inject_component_validators(string $field_id, string $component, array $field_context = array()): void {
		$field_key = $this->base_options->normalize_schema_key($field_id);
		// Field context is passed directly - no manifest defaults needed
		$context = $field_context;

		$validator_factories = $this->components->validator_factories();
		$factory             = $validator_factories[$component] ?? null;
		if (!is_callable($factory)) {
			// No validator for this component - silently skip
			// Display-only components (buttons, raw HTML) won't have validators
			return;
		}

		$validator_instance = $factory();
		$validator_callable = function($value, callable $emitWarning) use ($validator_instance, $context): bool {
			return $validator_instance->validate($value, $context, $emitWarning);
		};

		$hadSchema                                       = $this->base_options->has_schema_key($field_key);
		$this->queued_component_validators[$field_key][] = $validator_callable;
		// Only log per-field queuing in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->logger->debug(static::class . ': Component validator queued pending schema', array(
				'field_id'          => $field_id,
				'component'         => $component,
				'schema_registered' => $hadSchema,
			));
		}
	}

	public function drain_queued_component_validators(): array {
		$buffer                            = $this->queued_component_validators;
		$this->queued_component_validators = array();
		return $buffer;
	}

	public function inject_component_sanitizers(string $field_id, string $component, array $field_context = array()): void {
		$field_key = $this->base_options->normalize_schema_key($field_id);
		// Field context is passed directly - no manifest defaults needed
		$context = $field_context;

		$sanitizer_factories = $this->components->sanitizer_factories();
		$factory             = $sanitizer_factories[$component] ?? null;
		if (!is_callable($factory)) {
			// Sanitizers are optional – silently skip components without one
			return;
		}

		$sanitizer_instance = $factory();
		$sanitizer_callable = function($value, callable $emitNotice) use ($sanitizer_instance, $context): mixed {
			return $sanitizer_instance->sanitize($value, $context, $emitNotice);
		};

		$hadSchema                                       = $this->base_options->has_schema_key($field_key);
		$this->queued_component_sanitizers[$field_key][] = $sanitizer_callable;
		// Only log per-field queuing in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->logger->debug(static::class . ': Component sanitizer queued pending schema', array(
				'field_id'          => $field_id,
				'component'         => $component,
				'schema_registered' => $hadSchema,
			));
		}
	}

	public function drain_queued_component_sanitizers(): array {
		$buffer                            = $this->queued_component_sanitizers;
		$this->queued_component_sanitizers = array();
		return $buffer;
	}

	public function consume_component_validator_queue(array $bucketedSchema): array {
		$drained = $this->drain_queued_component_validators();
		if ($drained === array()) {
			return array($bucketedSchema, array());
		}

		$queuedForSchema = array();
		$matchedCounts   = array();
		$unmatchedKeys   = array();
		$schemaKeyLookup = array_fill_keys(array_keys($bucketedSchema), true);

		foreach ($drained as $normalizedKey => $validators) {
			if (!is_array($validators) || $validators === array()) {
				continue;
			}

			$validators = array_values($validators);
			if (isset($schemaKeyLookup[$normalizedKey])) {
				$count                           = count($validators);
				$queuedForSchema[$normalizedKey] = $validators;
				$matchedCounts[$normalizedKey]   = $count;
				// Only log per-key matching in verbose mode to avoid log flooding
				if (ErrorNoticeRenderer::isVerboseDebug()) {
					$this->logger->debug(static::class . ': Component validator queue matched schema key', array(
						'normalized_key'  => $normalizedKey,
						'validator_count' => $count,
					));
				}
				continue;
			}

			if (!isset($this->queued_component_validators[$normalizedKey])) {
				$this->queued_component_validators[$normalizedKey] = $validators;
			} else {
				$this->queued_component_validators[$normalizedKey] = array_merge(
					(array) $this->queued_component_validators[$normalizedKey],
					$validators
				);
			}
			$unmatchedKeys[] = $normalizedKey;
			// Only log per-key re-queuing in verbose mode to avoid log flooding
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->logger->debug(static::class . ': Component validator queue re-queued unmatched key', array(
					'normalized_key'  => $normalizedKey,
					'validator_count' => count($validators),
				));
			}
		}

		// Only log queue consumption summary in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->logger->debug(static::class . ': Component validator queue consumed', array(
				'schema_keys'    => array_keys($bucketedSchema),
				'queued_counts'  => $matchedCounts,
				'unmatched_keys' => $unmatchedKeys,
			));
		}

		return array($bucketedSchema, $queuedForSchema);
	}

	public function consume_component_sanitizer_queue(array $bucketedSchema): array {
		$drained = $this->drain_queued_component_sanitizers();
		if ($drained === array()) {
			return array($bucketedSchema, array());
		}

		$queuedForSchema = array();
		$matchedCounts   = array();
		$unmatchedKeys   = array();
		$schemaKeyLookup = array_fill_keys(array_keys($bucketedSchema), true);

		foreach ($drained as $normalizedKey => $sanitizers) {
			if (!is_array($sanitizers) || $sanitizers === array()) {
				continue;
			}

			$sanitizers = array_values($sanitizers);
			if (isset($schemaKeyLookup[$normalizedKey])) {
				$count                           = count($sanitizers);
				$queuedForSchema[$normalizedKey] = $sanitizers;
				$matchedCounts[$normalizedKey]   = $count;
				// Only log per-key matching in verbose mode to avoid log flooding
				if (ErrorNoticeRenderer::isVerboseDebug()) {
					$this->logger->debug(static::class . ': Component sanitizer queue matched schema key', array(
						'normalized_key'  => $normalizedKey,
						'sanitizer_count' => $count,
					));
				}
				continue;
			}

			if (!isset($this->queued_component_sanitizers[$normalizedKey])) {
				$this->queued_component_sanitizers[$normalizedKey] = $sanitizers;
			} else {
				$this->queued_component_sanitizers[$normalizedKey] = array_merge(
					(array) $this->queued_component_sanitizers[$normalizedKey],
					$sanitizers
				);
			}
			$unmatchedKeys[] = $normalizedKey;
			// Only log per-key re-queuing in verbose mode to avoid log flooding
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->logger->debug(static::class . ': Component sanitizer queue re-queued unmatched key', array(
					'normalized_key'  => $normalizedKey,
					'sanitizer_count' => count($sanitizers),
				));
			}
		}

		// Only log queue consumption summary in verbose mode to avoid log flooding
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->logger->debug(static::class . ': Component sanitizer queue consumed', array(
				'schema_keys'    => array_keys($bucketedSchema),
				'queued_counts'  => $matchedCounts,
				'unmatched_keys' => $unmatchedKeys,
			));
		}

		return array($bucketedSchema, $queuedForSchema);
	}
}
