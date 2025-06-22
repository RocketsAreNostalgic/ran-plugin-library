<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Doubles;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;

/**
 * A test double for the Logger that collects all messages.
 *
 * This class extends the real Logger but overrides the logging methods
 * to store messages in a public array instead of writing them to a file.
 */
class CollectingLogger extends Logger {
	/**
	 * @var array<int, array{level: string, message: string, context: array<mixed>}>
	 */
	public array $collected_logs = [];

	public function __construct(ConfigInterface $config) {
		// Pass the config to the parent to ensure properties like is_active are set correctly.
		parent::__construct($config->get_plugin_data());
	}

	public function is_active(): bool {
		return true;
	}

	public function debug(string $message, array $context = []): void {
		$this->log($message, 'debug', $context);
	}

	public function info(string $message, array $context = []): void {
		$this->log($message, 'info', $context);
	}

	public function warning(string $message, array $context = []): void {
		$this->log($message, 'warning', $context);
	}

	public function error(string $message, array $context = []): void {
		$this->log($message, 'error', $context);
	}

	/**
	 * Overrides the parent log method to collect logs instead of writing them.
	 * The signature must match the parent method.
	 */
	protected function log(string $message, string $level, array $context = []): void {
		$this->collected_logs[] = [
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		];
	}

	public function get_logs(): array {
		return $this->collected_logs;
	}
}
