<?php
/**
 * CollectingLogger class for WordPress plugins.
 *
 * @package Ran\PluginLib\Util
 */

declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Psr\Log\LogLevel;
use Ran\PluginLib\Util\Logger;

/**
 * This class extends the Logger but overrides the logging methods
 * to store messages in a public array instead of writing them to disk.
 * This can be used to redirect messages to STDERR for debugging purposes,
 * or to collect messages as a test double for testing purposes.
 */
class CollectingLogger extends Logger {
	/**
	 * @var array<int, array{level: string, message: string, context: array<mixed>}>
	 */
	public array $collected_logs = array();

	public function __construct(array $config = array()) {
		// Pass the config to the parent to ensure properties like is_active are set correctly.
		parent::__construct($config);
	}

	public function is_active(): bool {
		return true;
	}

	public function emergency(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::EMERGENCY, $message, $context);
	}

	public function alert(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::ALERT, $message, $context);
	}

	public function critical(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	public function error(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::ERROR, $message, $context);
	}

	public function warning(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::WARNING, $message, $context);
	}

	public function notice(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	public function info(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::INFO, $message, $context);
	}

	public function debug(string|\Stringable $message, array $context = array()): void {
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Overrides the parent log method to collect logs instead of writing them.
	 * The signature must match the parent method.
	 */
	public function log($level, string|\Stringable $message, array $context = array()): void {
		$this->collected_logs[] = array(
			'level'   => (string) $level,
			'message' => (string) $message,
			'context' => $context,
		);
	}

	public function get_logs(): array {
		return $this->collected_logs;
	}
}
