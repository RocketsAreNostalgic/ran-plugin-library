<?php
/**
 * CollectingLogger
 * A collecting logger that pools logs in memory for later inspection in tests or debugging.
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Util;

use Ran\PluginLib\Util\Logger;
use Psr\Log\LogLevel;

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
			'context' => $this->sanitize_context($context),
		);
	}

	public function get_logs(): array {
		return $this->collected_logs;
	}

	/**
	 * Stream all collected logs to a writer without building large intermediate strings.
	 *
	 * @param callable $writer Receives two arguments: (int $index, array $entry).
	 *
	 * @return void
	 */
	public function drain(callable $writer): void {
		foreach ($this->collected_logs as $index => $entry) {
			$writer($index, $entry);
		}
	}

	/**
	 * Persist collected logs to a file, streaming entries to avoid excess memory use.
	 *
	 * @param string $file_path Absolute path to the destination file.
	 *
	 * @return string The path that was written.
	 */
	public function drain_to_file(string $file_path): string {
		$handle = @fopen($file_path, 'wb');
		if ($handle === false) {
			throw new \RuntimeException('Unable to open log dump file for writing: ' . $file_path);
		}

		$write = static function($line) use ($handle): void {
			fwrite($handle, $line);
		};

		$write('Test logger drain at ' . gmdate('c') . PHP_EOL);
		$write('Log entries: ' . \count($this->collected_logs) . PHP_EOL . str_repeat('=', 40) . PHP_EOL);

		$this->drain(function(int $index, array $entry) use ($write): void {
			$write('Entry #' . ($index + 1) . PHP_EOL);
			$write('Level: ' . ($entry['level'] ?? '') . PHP_EOL);
			$write('Message: ' . ($entry['message'] ?? '') . PHP_EOL);
			$context = $entry['context'] ?? array();
			$write('Context: ' . json_encode($context, JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL);
			$write(str_repeat('-', 40) . PHP_EOL);
		});

		fclose($handle);

		return $file_path;
	}

	/**
	 * Return all log records that satisfy the given predicate.
	 *
	 * @param callable(array{level:string,message:string,context:array}):bool $predicate
	 * @return array<int, array{level:string,message:string,context:array}>
	 */
	public function find_logs(callable $predicate): array {
		return array_values(array_filter($this->collected_logs, $predicate));
	}

	/**
	 * Recursively sanitize context values to avoid retaining non-serializable structures.
	 *
	 * @param array<string|int, mixed> $context
	 * @return array<string|int, mixed>
	 */
	protected function sanitize_context(array $context, int $depth = 0): array {
		if ($depth > 8) {
			return array('[depth_limit]' => true);
		}

		$sanitized = array();
		foreach ($context as $key => $value) {
			$sanitized[$key] = $this->sanitize_value($value, $depth + 1, (string) $key);
		}

		return $sanitized;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	protected function sanitize_value(mixed $value, int $depth, string $key = '') {
		if ($depth > 8) {
			return '[depth_limit]';
		}

		if (is_array($value)) {
			return $this->sanitize_summary_if_applicable($key, $this->sanitize_context($value, $depth + 1));
		}

		if ($value instanceof \Closure) {
			return $this->describe_closure($value);
		}

		if (is_object($value)) {
			return 'object(' . get_class($value) . ')';
		}

		if (is_resource($value)) {
			return 'resource(' . get_resource_type($value) . ')';
		}

		if (is_scalar($value) || $value === null) {
			return $value;
		}

		return '[unsupported]';
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function sanitize_summary_if_applicable(string $key, array $value): array {
		if ($key === 'sanitize_summary' || $key === 'validate_summary') {
			return $this->summarize_bucket_map($value);
		}

		if ($key === 'default_summary') {
			return $this->summarize_default($value);
		}

		return $value;
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function summarize_bucket_map(array $value): array {
		$summary = array(
			'component'  => array('count' => 0, 'descriptors' => array()),
			'schema'     => array('count' => 0, 'descriptors' => array()),
			'other_keys' => array(),
		);

		foreach ($value as $bucket => $data) {
			if ($bucket === 'component' || $bucket === 'schema') {
				$descriptors = array();
				if (is_array($data)) {
					$descriptors = $this->normalize_descriptors_array($data['descriptors'] ?? array());
					$count       = isset($data['count']) ? (int) $data['count'] : count($descriptors);
				} else {
					$count = 0;
				}
				$summary[$bucket] = array(
					'count'       => $count,
					'descriptors' => $descriptors,
				);
				continue;
			}

			$summary['other_keys'][$bucket] = $data;
		}

		return $summary;
	}

	/**
	 * @param array<string|int,mixed> $value
	 * @return array<string|int,mixed>
	 */
	protected function summarize_default(array $value): array {
		return array(
			'has_default' => (bool) ($value['has_default'] ?? false),
			'type'        => $value['type'] ?? null,
			'value'       => $this->summarize_default_value($value['value'] ?? null),
		);
	}

	protected function summarize_default_value(mixed $value): mixed {
		if (is_scalar($value) || $value === null) {
			return $value;
		}

		if (is_array($value)) {
			return array('count' => count($value));
		}

		return is_object($value) ? 'object(' . get_class($value) . ')' : '[unsupported]';
	}

	/**
	 * @param mixed $descriptors
	 * @return array<int,string>
	 */
	protected function normalize_descriptors_array(mixed $descriptors): array {
		if (!is_array($descriptors)) {
			return array();
		}

		return array_values(array_map(static fn($item): string => is_string($item) ? $item : '[invalid]', $descriptors));
	}

	protected function describe_closure(\Closure $closure): string {
		if (function_exists('spl_object_id')) {
			return 'Closure#' . spl_object_id($closure);
		}

		if (function_exists('spl_object_hash')) {
			return 'Closure#' . spl_object_hash($closure);
		}

		return 'Closure';
	}
}
