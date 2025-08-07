<?php
/**
 * Hook Registration Result Objects
 *
 * Provides comprehensive result tracking for hook registration operations,
 * including success/failure status, error reporting, and debugging information.
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
 * Enumeration of possible hook registration statuses
 */
enum RegistrationStatus: string {
	case SUCCESS                = 'success';
	case PARTIAL_SUCCESS        = 'partial_success';
	case FAILED_VALIDATION      = 'failed_validation';
	case METHOD_NOT_FOUND       = 'method_not_found';
	case DUPLICATE_REGISTRATION = 'duplicate_registration';
	case WORDPRESS_ERROR        = 'wordpress_error';
	case UNKNOWN_ERROR          = 'unknown_error';

	/**
	 * Check if this status represents a successful registration
	 */
	public function is_success(): bool {
		return $this === self::SUCCESS || $this === self::PARTIAL_SUCCESS;
	}

	/**
	 * Check if this status represents a failure
	 */
	public function is_failure(): bool {
		return !$this->is_success();
	}

	/**
	 * Get a human-readable description of the status
	 */
	public function get_description(): string {
		return match ($this) {
			self::SUCCESS                => 'All hooks registered successfully',
			self::PARTIAL_SUCCESS        => 'Some hooks registered successfully, others failed',
			self::FAILED_VALIDATION      => 'Hook definition validation failed',
			self::METHOD_NOT_FOUND       => 'Callback method does not exist',
			self::DUPLICATE_REGISTRATION => 'Hook already registered',
			self::WORDPRESS_ERROR        => 'WordPress hook registration failed',
			self::UNKNOWN_ERROR          => 'Unknown error occurred during registration',
		};
	}
}

/**
 * Represents an error that occurred during hook registration
 */
final readonly class RegistrationError {
	public function __construct(
        public string $hook_name,
        public string $callback,
        public string $error_message,
        public RegistrationStatus $error_type,
        public ?\Throwable $exception = null
    ) {
	}

	/**
	 * Create an error from an exception
	 */
	public static function from_exception(
        string $hook_name,
        string $callback,
        \Throwable $exception,
        RegistrationStatus $error_type = RegistrationStatus::UNKNOWN_ERROR
    ): self {
		return new self(
			$hook_name,
			$callback,
			$exception->getMessage(),
			$error_type,
			$exception
		);
	}

	/**
	 * Get a formatted error message for logging
	 */
	public function get_formatted_message(): string {
		return sprintf(
			'[%s] Hook: %s, Method: %s - %s',
			$this->error_type->value,
			$this->hook_name,
			$this->callback,
			$this->error_message
		);
	}

	/**
	 * Convert to array for serialization
	 */
	public function to_array(): array {
		return array(
		    'hook_name'     => $this->hook_name,
		    'callback'      => $this->callback,
		    'error_message' => $this->error_message,
		    'error_type'    => $this->error_type->value,
		    'has_exception' => $this->exception !== null,
		);
	}
}

/**
 * Immutable result object for hook registration operations
 *
 * Provides comprehensive information about the success or failure
 * of hook registration attempts, including detailed error reporting.
 */
final readonly class RegistrationResult {
	/**
	 * @param RegistrationStatus $status Overall registration status
	 * @param array<HookDefinition> $successful_hooks Successfully registered hooks
	 * @param array<RegistrationError> $errors Errors that occurred during registration
	 * @param array<string, mixed> $metadata Additional metadata about the registration
	 */
	public function __construct(
        public RegistrationStatus $status,
        public array $successful_hooks = array(),
        public array $errors = array(),
        public array $metadata = array()
    ) {
	}

	/**
	 * Create a successful registration result
	 */
	public static function success(array $successful_hooks, array $metadata = array()): self {
		return new self(
			RegistrationStatus::SUCCESS,
			$successful_hooks,
			array(),
			$metadata
		);
	}

	/**
	 * Create a partial success result (some hooks succeeded, some failed)
	 */
	public static function partial_success(
        array $successful_hooks,
        array $errors,
        array $metadata = array()
    ): self {
		return new self(
			RegistrationStatus::PARTIAL_SUCCESS,
			$successful_hooks,
			$errors,
			$metadata
		);
	}

	/**
	 * Create a complete failure result
	 */
	public static function failure(
        RegistrationStatus $status,
        array $errors,
        array $metadata = array()
    ): self {
		return new self(
			$status,
			array(),
			$errors,
			$metadata
		);
	}

	/**
	 * Check if the registration was successful
	 */
	public function is_success(): bool {
		return $this->status->is_success();
	}

	/**
	 * Check if the registration failed
	 */
	public function is_failure(): bool {
		return $this->status->is_failure();
	}

	/**
	 * Get the number of successfully registered hooks
	 */
	public function get_success_count(): int {
		return count($this->successful_hooks);
	}

	/**
	 * Get the number of errors
	 */
	public function get_error_count(): int {
		return count($this->errors);
	}

	/**
	 * Get the total number of hooks processed
	 */
	public function get_total_count(): int {
		return $this->get_success_count() + $this->get_error_count();
	}

	/**
	 * Get success rate as a percentage
	 */
	public function get_success_rate(): float {
		$total = $this->get_total_count();
		if ($total === 0) {
			return 100.0;
		}
		return ($this->get_success_count() / $total) * 100.0;
	}

	/**
	 * Get all error messages as an array
	 */
	public function get_error_messages(): array {
		return array_map(
			fn(RegistrationError $error) => $error->get_formatted_message(),
			$this->errors
		);
	}

	/**
	 * Get errors grouped by type
	 */
	public function get_errors_by_type(): array {
		$grouped = array();
		foreach ($this->errors as $error) {
			$type = $error->error_type->value;
			if (!isset($grouped[$type])) {
				$grouped[$type] = array();
			}
			$grouped[$type][] = $error;
		}
		return $grouped;
	}

	/**
	 * Get a summary string for logging
	 */
	public function get_summary(): string {
		$total   = $this->get_total_count();
		$success = $this->get_success_count();
		$errors  = $this->get_error_count();
		$rate    = number_format($this->get_success_rate(), 1);

		return sprintf(
			'Hook Registration: %s (%d/%d successful, %s%% success rate)',
			$this->status->get_description(),
			$success,
			$total,
			$rate
		);
	}

	/**
	 * Convert to array for serialization/debugging
	 */
	public function to_array(): array {
		return array(
		    'status'             => $this->status->value,
		    'status_description' => $this->status->get_description(),
		    'success_count'      => $this->get_success_count(),
		    'error_count'        => $this->get_error_count(),
		    'total_count'        => $this->get_total_count(),
		    'success_rate'       => $this->get_success_rate(),
		    'successful_hooks'   => array_map(
		    	fn(HookDefinition $hook) => $hook->to_array(),
		    	$this->successful_hooks
		    ),
		    'errors' => array_map(
		    	fn(RegistrationError $error) => $error->to_array(),
		    	$this->errors
		    ),
		    'metadata' => $this->metadata,
		);
	}

	/**
	 * Merge this result with another result
	 */
	public function merge(RegistrationResult $other): self {
		$combined_successful = array_merge($this->successful_hooks, $other->successful_hooks);
		$combined_errors     = array_merge($this->errors, $other->errors);
		$combined_metadata   = array_merge($this->metadata, $other->metadata);

		// Determine combined status
		$combined_status = match (true) {
			empty($combined_errors)     => RegistrationStatus::SUCCESS,
			empty($combined_successful) => RegistrationStatus::UNKNOWN_ERROR,
			default                     => RegistrationStatus::PARTIAL_SUCCESS,
		};

		return new self(
			$combined_status,
			$combined_successful,
			$combined_errors,
			$combined_metadata
		);
	}

	/**
	 * Filter successful hooks by hook name
	 */
	public function get_successful_hooks_for(string $hook_name): array {
		return array_filter(
			$this->successful_hooks,
			fn(HookDefinition $hook) => $hook->hook_name === $hook_name
		);
	}

	/**
	 * Filter errors by hook name
	 */
	public function get_errors_for(string $hook_name): array {
		return array_filter(
			$this->errors,
			fn(RegistrationError $error) => $error->hook_name === $hook_name
		);
	}

	/**
	 * Check if a specific hook was successfully registered
	 */
	public function was_hook_registered(string $hook_name, string $callback): bool {
		foreach ($this->successful_hooks as $hook) {
			if ($hook->hook_name === $hook_name && $hook->callback === $callback) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the first error (if any) for debugging
	 */
	public function get_first_error(): ?RegistrationError {
		return $this->errors[0] ?? null;
	}
}
