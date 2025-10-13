<?php
/**
 * ComponentManifest: unified registry and _discovery for form components.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\Normalize\NormalizeInterface;
use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;

class ComponentManifest {
	/** @var array<string, callable(array<string,mixed>):ComponentRenderResult|array{result:ComponentRenderResult,warnings?:array<int,string>}> */
	private array $components = array();
	/** @var array<int,string> */
	private array $warnings = array();
	/** @var array<string,array{normalizer:?string,builder:?string,validator:?string}> */
	private array $componentMetadata = array();
	private ComponentNormalizationContext $helpers;

	public function __construct(private ComponentLoader $views, private Logger $logger) {
		$this->logger  = $logger;
		$this->helpers = new ComponentNormalizationContext($this->logger);

		$this->_discover();
		$this->_register_defaults();
	}

	/**
	 * Registers a component factory.
	 *
	 * @param string $alias
	 * @param callable $factory
	 */
	public function register(string $alias, callable $factory): void {
		$this->components[$alias] = $factory;
	}

	/**
	 * Checks if a component is registered.
	 *
	 * @param string $alias
	 * @return bool
	 */
	public function has(string $alias): bool {
		return isset($this->components[$alias]);
	}

	/**
	 * Renders a component.
	 *
	 * @param string $alias
	 * @param array<string,mixed> $context
	 * @return ComponentRenderResult
	 */
	public function render(string $alias, array $context = array()): ComponentRenderResult {
		if (!isset($this->components[$alias])) {
			throw new \InvalidArgumentException(sprintf('Unknown form component "%s".', $alias));
		}

		$factory = $this->components[$alias];
		$outcome = $factory($context);

		$localWarnings = array();
		if ($outcome instanceof ComponentRenderResult) {
			$result = $outcome;
		} elseif (is_array($outcome) && isset($outcome['result'])) {
			$result        = $outcome['result'];
			$localWarnings = isset($outcome['warnings']) && is_array($outcome['warnings']) ? $outcome['warnings'] : array();
			if (!$result instanceof ComponentRenderResult) {
				throw new \UnexpectedValueException(sprintf('Component "%s" must return ComponentRenderResult.', $alias));
			}
		} else {
			throw new \UnexpectedValueException(sprintf('Component "%s" factory must return ComponentRenderResult.', $alias));
		}

		$helperWarnings = $this->helpers->take_warnings();
		if (!empty($helperWarnings)) {
			$localWarnings = array_merge($localWarnings, $helperWarnings);
		}
		if (!empty($localWarnings)) {
			$this->warnings = array_merge($this->warnings, $localWarnings);
		}

		return $result;
	}

	/**
	 * Returns any warnings generated during rendering.
	 *
	 * @return array<int,string>
	 */
	public function take_warnings(): array {
		$warnings       = $this->warnings;
		$this->warnings = array();
		return $warnings;
	}

	/**
	 * Creates normalizers for all registered components.
	 *
	 * @return array<string,NormalizeInterface>
	 */
	public function create_normalizers(): array {
		$instances = array();
		foreach ($this->componentMetadata as $alias => $meta) {
			$normalizer = $meta['normalizer'];
			if ($normalizer === null) {
				continue;
			}
			$instances[$alias] = new $normalizer($this->views);
		}

		return $instances;
	}

	/**
	 * Creates builder factories for all registered components.
	 *
	 * @return array<string,callable(string,string):BuilderDefinitionInterface>
	 */
	public function builder_factories(): array {
		$factories = array();
		foreach ($this->componentMetadata as $alias => $meta) {
			$builder = $meta['builder'];
			if ($builder === null) {
				continue;
			}
			$factories[$alias] = function (string $id, string $label, mixed ...$args) use ($builder): BuilderDefinitionInterface {
				return new $builder($id, $label, ...$args);
			};
		}

		return $factories;
	}

	/**
	 * Returns a map of validator factories for each component.
	 *
	 * @return array<string,callable():ValidatorInterface>
	 */
	public function validator_factories(): array {
		$factories = array();
		foreach ($this->componentMetadata as $alias => $meta) {
			$validator = $meta['validator'];
			if ($validator === null) {
				continue;
			}
			$factories[$alias] = function () use ($validator): ValidatorInterface {
				$instance = new $validator($this->logger);
				if (!$instance instanceof ValidatorInterface) {
					throw new \UnexpectedValueException(sprintf('Validator for "%s" must implement %s.', $validator, ValidatorInterface::class));
				}
				return $instance;
			};
		}

		return $factories;
	}

	/**
	 * Discovers all available components.
	 */
	private function _discover(): void {
		foreach ($this->views->aliases() as $alias => $_path) {
			$this->_register_alias($alias);
		}
	}

	/**
	 * Registers a component alias.
	 *
	 * @param string $alias
	 */
	private function _register_alias(string $alias): void {
		$meta = $this->componentMetadata[$alias] ?? array(
		    'normalizer' => null,
		    'builder'    => null,
		    'validator'  => null,
		);

		$class = $this->views->resolve_normalizer_class($alias);
		if ($class !== null && is_subclass_of($class, NormalizeInterface::class)) {
			$meta['normalizer'] = $class;
		}

		$builder = $this->views->resolve_builder_class($alias);
		if ($builder !== null && is_subclass_of($builder, BuilderDefinitionInterface::class)) {
			$meta['builder'] = $builder;
		}

		$validator = $this->views->resolve_validator_class($alias);
		if ($validator !== null && is_subclass_of($validator, ValidatorInterface::class)) {
			$meta['validator'] = $validator;
		}

		$this->componentMetadata[$alias] = $meta;
	}

	/**
	 * Registers default factories for all components.
	 */
	private function _register_defaults(): void {
		$normalizers = $this->create_normalizers();

		foreach ($this->views->aliases() as $alias => $_path) {
			if (isset($normalizers[$alias])) {
				$normalizer = $normalizers[$alias];
				$this->register($alias, function (array $context) use ($normalizer, $alias): array {
					$normalized = $normalizer->render($context, $this->helpers, $alias);
					$payload    = $normalized['payload'];
					$result     = $this->_create_result_from_payload($payload);
					return array(
					    'result'   => $result,
					    'warnings' => $normalized['warnings'] ?? array(),
					);
				});
				continue;
			}

			$this->register($alias, function (array $context) use ($alias): array {
				$result = $this->_render_raw_component($alias, $context);
				return array(
				    'result'   => $result,
				    'warnings' => array(),
				);
			});
		}
	}

	/**
	 * Creates a ComponentRenderResult from a payload.
	 *
	 * @param array<string,mixed> $payload
	 * @return ComponentRenderResult
	 */
	private function _create_result_from_payload(array $payload): ComponentRenderResult {
		return new ComponentRenderResult(
			(string) ($payload['markup'] ?? ''),
			$payload['script'] ?? null,
			$payload['style']  ?? null,
			(bool) ($payload['requires_media'] ?? false),
			(bool) ($payload['repeatable'] ?? false),
			$payload['context_schema'] ?? array()
		);
	}

	/**
	 * Renders a component without normalizing it.
	 *
	 * @param array<string,mixed> $context
	 * @return ComponentRenderResult
	 */
	private function _render_raw_component(string $alias, array $context): ComponentRenderResult {
		$payload = $this->views->render_payload($alias, $context);
		if (is_array($payload) && isset($payload['markup'])) {
			return $this->_create_result_from_payload($payload);
		}

		$markup = is_string($payload) ? $payload : $this->views->render($alias, $context);
		return new ComponentRenderResult((string) $markup);
	}
}
