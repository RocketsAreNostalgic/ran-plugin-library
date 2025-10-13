<?php
/**
 * Universal Form Message Handling Infrastructure
 *
 * This class provides universal message handling logic for both warnings and notices
 * across all form contexts (AdminSettings, UserSettings, and future contexts).
 *
 * @package  RanPluginLib\Forms\Renderer
 * @author   Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license  GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link     https://github.com/RocketsAreNostalgic
 * @since    0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Renderer;

use Ran\PluginLib\Util\Logger;

/**
 * Universal message handling logic for both warnings and notices across all form contexts.
 *
 * Key responsibilities:
 * - Structured message storage (warnings vs notices)
 * - Field-specific message retrieval
 * - Pending values management for validation failures
 * - Value resolution logic
 */
class FormMessageHandler {
	/**
	 * Messages organized by field ID.
	 * Structure: ['field_id' => ['warnings' => [...], 'notices' => [...]]]
	 *
	 * @var array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	private array $messages_by_field = array();

	/**
	 * Pending values when validation fails.
	 * These are the values that failed validation and should be displayed back to the user.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $pending_values = null;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Creates a new FormMessageHandler instance.
	 *
	 * @param Logger|null $logger Optional logger instance
	 */
	public function __construct(?Logger $logger = null) {
		$this->logger = $logger;
	}

	/**
	 * Set messages for all fields from structured message data.
	 *
	 * @param array<string, array{warnings: array<int, string>, notices: array<int, string>}> $messages
	 * @return void
	 */
	public function set_messages(array $messages): void {
		$this->messages_by_field = array();

		foreach ($messages as $field_id => $field_messages) {
			$sanitized_field_id = $this->_sanitize_field_id($field_id);

			// Ensure proper structure
			$this->messages_by_field[$sanitized_field_id] = array(
				'warnings' => $field_messages['warnings'] ?? array(),
				'notices'  => $field_messages['notices']  ?? array()
			);
		}

		$this->_get_logger()->debug('FormMessageHandler: Messages set', array(
			'field_count'    => count($this->messages_by_field),
			'total_warnings' => array_sum(array_map(fn($m) => count($m['warnings']), $this->messages_by_field)),
			'total_notices'  => array_sum(array_map(fn($m) => count($m['notices']), $this->messages_by_field))
		));
	}

	/**
	 * Get messages for a specific field.
	 *
	 * @param string $field_id Field identifier
	 * @return array{warnings: array<int, string>, notices: array<int, string>}
	 */
	public function get_messages_for_field(string $field_id): array {
		$sanitized_field_id = $this->_sanitize_field_id($field_id);

		return $this->messages_by_field[$sanitized_field_id] ?? array(
			'warnings' => array(),
			'notices'  => array()
		);
	}

	/**
	 * Set pending values for when validation fails.
	 *
	 * @param array<string, mixed>|null $values Pending values or null to clear
	 * @return void
	 */
	public function set_pending_values(?array $values): void {
		$this->pending_values = $values;

		$this->_get_logger()->debug('FormMessageHandler: Pending values set', array(
			'has_pending'   => $values !== null,
			'pending_count' => $values !== null ? count($values) : 0
		));
	}

	/**
	 * Get effective values by choosing between stored and pending values based on validation state.
	 *
	 * @param array<string, mixed> $stored_values Stored/saved values
	 * @return array<string, mixed> Effective values to display
	 */
	public function get_effective_values(array $stored_values): array {
		// If we have pending values (validation failed), use those for display
		if ($this->pending_values !== null) {
			$this->_get_logger()->debug('FormMessageHandler: Using pending values due to validation failure');
			return $this->pending_values;
		}

		// Otherwise use stored values
		return $stored_values;
	}

	/**
	 * Check if there are any validation failures.
	 *
	 * @return bool True if there are validation warnings
	 */
	public function has_validation_failures(): bool {
		foreach ($this->messages_by_field as $field_messages) {
			if (!empty($field_messages['warnings'])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get count of warnings across all fields.
	 *
	 * @return int Total warning count
	 */
	public function get_warning_count(): int {
		$count = 0;
		foreach ($this->messages_by_field as $field_messages) {
			$count += count($field_messages['warnings']);
		}
		return $count;
	}

	/**
	 * Get count of notices across all fields.
	 *
	 * @return int Total notice count
	 */
	public function get_notice_count(): int {
		$count = 0;
		foreach ($this->messages_by_field as $field_messages) {
			$count += count($field_messages['notices']);
		}
		return $count;
	}

	/**
	 * Add a single message to a specific field.
	 *
	 * @param string $field_id Field identifier
	 * @param string $message Message text
	 * @param string $type Message type ('warning' or 'notice')
	 * @return void
	 */
	public function add_message(string $field_id, string $message, string $type = 'warning'): void {
		$sanitized_field_id = $this->_sanitize_field_id($field_id);
		$message            = trim((string) $message);

		if ($message === '') {
			return;
		}

		if (!in_array($type, array('warning', 'notice'), true)) {
			throw new \InvalidArgumentException("FormMessageHandler: Invalid message type '{$type}'. Must be 'warning' or 'notice'.");
		}

		if (!isset($this->messages_by_field[$sanitized_field_id])) {
			$this->messages_by_field[$sanitized_field_id] = array('warnings' => array(), 'notices' => array());
		}

		$this->messages_by_field[$sanitized_field_id][$type . 's'][] = $message;

		$this->_get_logger()->debug('FormMessageHandler: Message added', array(
			'field_id' => $sanitized_field_id,
			'type'     => $type,
			'message'  => $message
		));
	}

	/**
	 * Get all messages for all fields.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function get_all_messages(): array {
		return $this->messages_by_field;
	}

	/**
	 * Clear all messages and pending values.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->messages_by_field = array();
		$this->pending_values    = null;

		$this->_get_logger()->debug('FormMessageHandler: All messages and pending values cleared');
	}

	/**
	 * Sanitize field ID for safe array access.
	 *
	 * @param string $field_id Raw field ID
	 * @return string Sanitized field ID
	 */
	private function _sanitize_field_id(string $field_id): string {
		// Remove any potentially dangerous characters and normalize
		return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($field_id));
	}

	/**
	 * Get the logger instance, creating a default one if needed.
	 *
	 * @return Logger
	 */
	private function _get_logger(): Logger {
		if ($this->logger === null) {
			$this->logger = new Logger();
		}
		return $this->logger;
	}
}
