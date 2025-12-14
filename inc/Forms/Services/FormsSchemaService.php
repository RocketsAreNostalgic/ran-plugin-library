<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;

class FormsSchemaService implements FormsSchemaServiceInterface {
	/** @var array<string, array<string,mixed>> */
	private array $schema_bundle_cache;

	/** @var array<string, array<string,mixed>>|null */
	private ?array $catalogue_cache;

	/** @var callable(): (FormsServiceSession|null) */
	private $get_form_session;

	/** @var callable(): void */
	private $start_form_session;

	/** @var callable(): array<int, array<string, mixed>> */
	private $get_registered_field_metadata;

	/**
	 * @param array<string, array<string,mixed>> $schema_bundle_cache
	 * @param array<string, array<string,mixed>>|null $catalogue_cache
	 * @param callable(): (FormsServiceSession|null) $get_form_session
	 * @param callable(): void $start_form_session
	 * @param callable(): array<int, array<string, mixed>> $get_registered_field_metadata
	 */
	public function __construct(
		private RegisterOptions $base_options,
		private ComponentManifest $components,
		private Logger $logger,
		private FormsValidatorServiceInterface $validator_service,
		private string $host_label,
		array &$schema_bundle_cache,
		?array &$catalogue_cache,
		callable $get_form_session,
		callable $start_form_session,
		callable $get_registered_field_metadata
	) {
		$this->schema_bundle_cache           = & $schema_bundle_cache;
		$this->catalogue_cache               = & $catalogue_cache;
		$this->get_form_session              = $get_form_session;
		$this->start_form_session            = $start_form_session;
		$this->get_registered_field_metadata = $get_registered_field_metadata;
	}

	/**
	 * Factory that allocates internal cache state.
	 *
	 * This allows consumers (like FormsCore) to avoid owning cache arrays.
	 */
	public static function create_with_internal_state(
		RegisterOptions $base_options,
		ComponentManifest $components,
		Logger $logger,
		FormsValidatorServiceInterface $validator_service,
		string $host_label,
		callable $get_form_session,
		callable $start_form_session,
		callable $get_registered_field_metadata
	): self {
		$schema_bundle_cache = array();
		$catalogue_cache     = null;
		return new self(
			$base_options,
			$components,
			$logger,
			$validator_service,
			$host_label,
			$schema_bundle_cache,
			$catalogue_cache,
			$get_form_session,
			$start_form_session,
			$get_registered_field_metadata
		);
	}

	public function resolve_schema_bundle(RegisterOptions $options, array $context = array()): array {
		$storage       = $options->get_storage_context();
		$cacheKeyParts = array(
			$options->get_main_option_name(),
			$storage->scope?->value ?? 'site',
		);

		if ($storage->scope === OptionScope::Blog && $storage->blog_id !== null) {
			$cacheKeyParts[] = (string) $storage->blog_id;
		} elseif ($storage->scope === OptionScope::User && $storage->user_id !== null) {
			$cacheKeyParts[] = (string) $storage->user_id;
		}

		$cacheKey = implode('|', $cacheKeyParts);

		if (isset($this->schema_bundle_cache[$cacheKey])) {
			$this->logger->debug('forms.schema_bundle.cache_hit', array(
				'key'    => $cacheKey,
				'intent' => $context['intent'] ?? 'none',
			));
			return $this->schema_bundle_cache[$cacheKey];
		}

		$schemaInternal = $options->__get_schema_internal();
		$defaults       = array();
		foreach ($schemaInternal as $normalizedKey => $entry) {
			if (is_array($entry) && array_key_exists('default', $entry)) {
				$defaults[$normalizedKey] = array('default' => $entry['default']);
			}
		}

		$session = ($this->get_form_session)();
		if ($session === null) {
			($this->start_form_session)();
			$session = ($this->get_form_session)();
		}

		$bucketedSchema   = array();
		$metadata         = array();
		$queuedValidators = array();
		$queuedSanitizers = array();

		if ($session !== null) {
			$assembled        = $this->assemble_initial_bucketed_schema($session);
			$bucketedSchema   = $assembled['schema'];
			$metadata         = $assembled['metadata'];
			$queuedValidators = $assembled['queued_validators'];
			$queuedSanitizers = $assembled['queued_sanitizers'];
		}

		$bundle = array(
			'schema'            => $schemaInternal,
			'defaults'          => $defaults,
			'bucketed_schema'   => $bucketedSchema,
			'metadata'          => $metadata,
			'queued_validators' => $queuedValidators,
			'queued_sanitizers' => $queuedSanitizers,
		);

		$this->schema_bundle_cache[$cacheKey] = $bundle;
		$this->logger->debug('forms.schema_bundle.cached', array(
			'key'                   => $cacheKey,
			'schema_keys'           => array_keys($schemaInternal),
			'default_count'         => count($defaults),
			'bucketed_count'        => count($bucketedSchema),
			'queued_validator_keys' => array_keys($queuedValidators),
			'queued_sanitizer_keys' => array_keys($queuedSanitizers),
		));

		return $bundle;
	}

	public function merge_schema_bundle_sources(array $bundle): array {
		$merged           = array();
		$metadata         = $bundle['metadata']          ?? array();
		$queuedValidators = $bundle['queued_validators'] ?? array();
		$queuedSanitizers = $bundle['queued_sanitizers'] ?? array();

		if (!empty($bundle['bucketed_schema'])) {
			$merged = $bundle['bucketed_schema'];
		}

		if (!empty($bundle['schema'])) {
			foreach ($bundle['schema'] as $key => $entry) {
				if (!isset($merged[$key])) {
					if (array_key_exists('default', $entry)) {
						$merged[$key] = array('default' => $entry['default']);
					}
				} elseif (!array_key_exists('default', $merged[$key]) && array_key_exists('default', $entry)) {
					$merged[$key]['default'] = $entry['default'];
				}
			}
		}

		$defaultsForSeeding = $bundle['defaults'] ?? array();
		if (!empty($defaultsForSeeding)) {
			foreach ($defaultsForSeeding as $key => $entry) {
				if (!isset($merged[$key])) {
					$merged[$key] = $entry;
				} elseif (!array_key_exists('default', $merged[$key]) && array_key_exists('default', $entry)) {
					$merged[$key]['default'] = $entry['default'];
				}
			}
		}

		$this->logger->debug('forms.schema_bundle.merged', array(
			'bucketed_count' => count($bundle['bucketed_schema'] ?? array()),
			'schema_count'   => count($bundle['schema'] ?? array()),
			'defaults_count' => count($defaultsForSeeding),
			'merged_count'   => count($merged),
		));

		return array(
			'merged_schema'        => $merged,
			'metadata'             => $metadata,
			'queued_validators'    => $queuedValidators,
			'queued_sanitizers'    => $queuedSanitizers,
			'defaults_for_seeding' => $defaultsForSeeding,
		);
	}

	public function merge_schema_entry_buckets(array $existing, array $incoming): array {
		$merged = $existing;

		if (array_key_exists('default', $incoming)) {
			$merged['default'] = $incoming['default'];
		}

		if (isset($incoming['sanitize']) && is_array($incoming['sanitize'])) {
			if (!isset($merged['sanitize']) || !is_array($merged['sanitize'])) {
				$merged['sanitize'] = array('component' => array(), 'schema' => array());
			}
			foreach (array('component', 'schema') as $bucket) {
				if (isset($incoming['sanitize'][$bucket]) && is_array($incoming['sanitize'][$bucket])) {
					$merged['sanitize'][$bucket] = array_merge(
						$merged['sanitize'][$bucket] ?? array(),
						$incoming['sanitize'][$bucket]
					);
				}
			}
		}

		if (isset($incoming['validate']) && is_array($incoming['validate'])) {
			if (!isset($merged['validate']) || !is_array($merged['validate'])) {
				$merged['validate'] = array('component' => array(), 'schema' => array());
			}
			foreach (array('component', 'schema') as $bucket) {
				if (isset($incoming['validate'][$bucket]) && is_array($incoming['validate'][$bucket])) {
					$merged['validate'][$bucket] = array_merge(
						$merged['validate'][$bucket] ?? array(),
						$incoming['validate'][$bucket]
					);
				}
			}
		}

		if (isset($incoming['context']) && is_array($incoming['context'])) {
			$merged['context'] = array_merge($merged['context'] ?? array(), $incoming['context']);
		}

		return $merged;
	}

	public function assemble_initial_bucketed_schema(FormsServiceSession $session): array {
		$bucketedSchema = array();
		$metadata       = array();

		if ($this->catalogue_cache === null) {
			$this->catalogue_cache = $this->components->default_catalogue();
			$this->logger->debug($this->host_label . ': Catalogue fetched and cached', array(
				'component_count' => count($this->catalogue_cache),
			));
		}
		$manifestCatalogue = $this->catalogue_cache;
		$internalSchema    = $this->base_options->__get_schema_internal();

		$registeredFieldMetadata = ($this->get_registered_field_metadata)();
		foreach ($registeredFieldMetadata as $entry) {
			$field     = $entry['field'] ?? array();
			$fieldId   = isset($field['id']) ? (string) $field['id'] : '';
			$component = isset($field['component']) ? (string) $field['component'] : '';
			if ($fieldId === '' || $component === '') {
				continue;
			}

			$normalizedKey   = $this->base_options->normalize_schema_key($fieldId);
			$currentEntry    = $internalSchema[$normalizedKey] ?? null;
			$componentSchema = $field['schema']                ?? array();
			if (!is_array($componentSchema)) {
				$componentSchema = array();
			}

			if (is_array($currentEntry)) {
				$sanitizeComponents = (array) ($currentEntry['sanitize']['component'] ?? array());
				$validateComponents = (array) ($currentEntry['validate']['component'] ?? array());
				if ($sanitizeComponents === array() || $validateComponents === array()) {
					$entryForMerge = $currentEntry;
					if (isset($entryForMerge['sanitize']['schema'])) {
						$entryForMerge['sanitize']['schema'] = array();
					}
					if (isset($entryForMerge['validate']['schema'])) {
						$entryForMerge['validate']['schema'] = array();
					}
					$merged                         = $session->merge_schema_with_defaults($component, $entryForMerge, $manifestCatalogue);
					$bucketedSchema[$normalizedKey] = $merged;
					$validatorFactories             = $this->components->validator_factories();
					if (isset($validatorFactories[$component])) {
						$metadata[$normalizedKey]['requires_validator'] = true;
					}
				}
				continue;
			}

			$merged                         = $session->merge_schema_with_defaults($component, $componentSchema, $manifestCatalogue);
			$bucketedSchema[$normalizedKey] = $merged;

			$validatorFactories = $this->components->validator_factories();
			if (isset($validatorFactories[$component])) {
				$metadata[$normalizedKey]['requires_validator'] = true;
			}
		}

		$queuedValidators = array();
		$queuedSanitizers = array();

		if ($bucketedSchema !== array()) {
			list($bucketedSchema, $queuedValidators) = $this->validator_service->consume_component_validator_queue($bucketedSchema);
			list($bucketedSchema, $queuedSanitizers) = $this->validator_service->consume_component_sanitizer_queue($bucketedSchema);
		}

		return array(
			'schema'            => $bucketedSchema,
			'metadata'          => $metadata,
			'queued_validators' => $queuedValidators,
			'queued_sanitizers' => $queuedSanitizers,
		);
	}
}
