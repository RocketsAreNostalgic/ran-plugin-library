<?php
/**
 * Hook Definition Value Object
 *
 * Represents a single WordPress hook registration with type safety and validation.
 * This value object ensures consistent hook definitions across the plugin library.
 *
 * This implementation is inspired by the polymorphic interface concepts from
 * https://carlalexander.ca/polymorphism-wordpress-interfaces/
 *
 * @package Ran\PluginLib\HooksAccessory
 * @since 0.0.10
 */

declare(strict_types=1);

namespace Ran\PluginLib\HooksAccessory;

/**
 * Immutable value object representing a WordPress hook definition
 *
 * This class provides type safety and validation for hook registrations,
 * ensuring consistent behavior across all hook registration patterns.
 */
final readonly class HookDefinition {
	/**
	 * Create a new hook definition
	 *
	 * @param string $hook_name The WordPress hook name (e.g., 'wp_init', 'the_content')
	 * @param string $callback The method name to call when the hook fires
	 * @param int $priority The hook priority (lower numbers = earlier execution)
	 * @param int $accepted_args Number of arguments the callback accepts
	 * @param string $hook_type Type of hook: 'action' or 'filter'
	 * @throws \InvalidArgumentException If any parameter is invalid
	 */
	public function __construct(
        public string $hook_name,
        public string $callback,
        public int $priority = 10,
        public int $accepted_args = 1,
        public string $hook_type = 'action'
    ) {
		$this->validate();
	}

	/**
	 * Create a HookDefinition from various input formats
	 *
	 * Supports multiple input formats for flexibility:
	 * - 'callback' (string)
	 * - ['callback', priority] (array)
	 * - ['callback', priority, accepted_args] (array)
	 *
	 * @param string $hook_name The WordPress hook name
	 * @param string|array $definition The hook definition in various formats
	 * @return self
	 * @throws \InvalidArgumentException If the definition format is invalid
	 */
	public static function create(string $hook_name, string|array $definition, string $hook_type = 'action'): self {
		if (is_string($definition)) {
			return new self($hook_name, $definition, 10, 1, $hook_type);
		}

		if (is_array($definition)) {
			if (count($definition) === 2) {
				return new self($hook_name, $definition[0], (int)$definition[1], 1, $hook_type);
			}

			if (count($definition) === 3) {
				return new self($hook_name, $definition[0], (int)$definition[1], (int)$definition[2], $hook_type);
			}

			$callback      = $definition[0] ?? null;
			$priority      = $definition[1] ?? 10;
			$accepted_args = $definition[2] ?? 1;

			if (!is_string($callback)) {
				throw new \InvalidArgumentException('Hook definition array must have a string method name as first element');
			}

			if (!is_int($priority)) {
				throw new \InvalidArgumentException('Hook priority must be an integer');
			}

			if (!is_int($accepted_args)) {
				throw new \InvalidArgumentException('Accepted args must be an integer');
			}

			return new self($hook_name, $callback, $priority, $accepted_args, $hook_type);
		}

		throw new \InvalidArgumentException('Hook definition must be a string or array');
	}

	/**
	 * Create multiple HookDefinitions from an array of hook configurations
	 *
	 * @param array<string, string|array> $hooks_config Array of hook_name => definition pairs
	 * @return array<HookDefinition>
	 * @throws \InvalidArgumentException If any hook definition is invalid
	 */
	public static function create_multiple(array $hooks_config, string $hook_type = 'action'): array {
		$definitions = array();

		foreach ($hooks_config as $hook_name => $definition) {
			if (!is_string($hook_name)) {
				throw new \InvalidArgumentException('Hook names must be strings');
			}

			$definitions[] = self::create($hook_name, $definition, $hook_type);
		}

		return $definitions;
	}

	/**
	 * Validate the hook definition parameters
	 *
	 * @throws \InvalidArgumentException If any parameter is invalid
	 */
	private function validate(): void {
		if (empty($this->hook_name)) {
			throw new \InvalidArgumentException('Hook name cannot be empty');
		}

		if (!is_string($this->callback) || empty($this->callback)) {
			throw new \InvalidArgumentException('Method name must be a non-empty string');
		}

		if (!in_array($this->hook_type, array('action', 'filter'), true)) {
			throw new \InvalidArgumentException("Hook type must be either 'action' or 'filter', '" . $this->hook_type . "' given");
		}

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->callback)) {
			throw new \InvalidArgumentException('Method name must be a valid PHP method name');
		}

		if ($this->priority < 0) {
			throw new \InvalidArgumentException('Hook priority cannot be negative');
		}

		if ($this->accepted_args < 0) {
			throw new \InvalidArgumentException('Accepted args cannot be negative');
		}
	}

	/**
	 * Check if this hook definition is valid for a given object
	 *
	 * @param object $object The object to validate against
	 * @return bool True if the method exists and is callable
	 */
	public function is_valid_for_object(object $object): bool {
		return method_exists($object, $this->callback) && is_callable(array($object, $this->callback));
	}

	/**
	 * Get a unique identifier for this hook definition
	 *
	 * Useful for deduplication and tracking purposes.
	 *
	 * @return string Unique identifier
	 */
	public function get_unique_id(): string {
		return md5(serialize(array($this->hook_name, $this->callback, $this->priority, $this->accepted_args, $this->hook_type)));
	}

	/**
	 * Get a human-readable string representation
	 *
	 * @return string String representation for debugging
	 */
	public function to_string(): string {
		return sprintf(
			'%s -> %s (priority: %d, args: %d, type: %s)',
			$this->hook_name,
			$this->callback,
			$this->priority,
			$this->accepted_args,
			$this->hook_type
		);
	}

	/**
	 * Convert to array format for serialization
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'hook_name'     => $this->hook_name,
			'callback'      => $this->callback,
			'priority'      => $this->priority,
			'accepted_args' => $this->accepted_args,
			'hook_type'     => $this->hook_type,
		);
	}

	/**
	 * Create from array format
	 *
	 * @param array<string, mixed> $data Array data
	 * @return self
	 * @throws \InvalidArgumentException If array format is invalid
	 */
	public static function from_array(array $data): self {
		$required_keys = array('hook_name', 'callback', 'priority', 'accepted_args');

		foreach ($required_keys as $key) {
			if (!array_key_exists($key, $data)) {
				throw new \InvalidArgumentException("Missing required key: {$key}");
			}
		}

		return new self(
			$data['hook_name'],
			$data['callback'],
			(int)$data['priority'],
			(int)$data['accepted_args'],
			$data['hook_type'] ?? 'action'
		);
	}

	/**
	 * Compare two hook definitions for equality
	 *
	 * @param HookDefinition $other The other definition to compare
	 * @return bool True if definitions are identical
	 */
	public function equals(HookDefinition $other): bool {
		return $this->hook_name === $other->hook_name && $this->callback === $other->callback && $this->priority === $other->priority && $this->accepted_args === $other->accepted_args;
	}

	/**
	 * Check if this definition conflicts with another (same hook/priority, different method)
	 *
	 * @param HookDefinition $other The other definition to check
	 * @return bool True if there's a potential conflict
	 */
	public function conflicts_with(HookDefinition $other): bool {
		return $this->hook_name === $other->hook_name && $this->priority === $other->priority && $this->callback !== $other->callback;
	}
}
