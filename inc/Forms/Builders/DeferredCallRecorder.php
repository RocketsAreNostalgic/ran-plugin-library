<?php
/**
 * DeferredCallRecorder - Records method calls for later replay.
 *
 * This class captures fluent method calls with their arguments and call site
 * information, allowing them to be replayed on a target builder at render time.
 * Provides excellent debugging support by tracking where each call originated.
 *
 * @package Ran\PluginLib\Forms\Renderer
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Util\Logger;

/**
 * Records method calls for deferred execution with full debugging support.
 */
class DeferredCallRecorder {
	/**
	 * Recorded calls with metadata.
	 *
	 * @var array<int, array{method: string, args: array, file: string, line: int}>
	 */
	private array $calls = array();

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger;

	/**
	 * Whether we're in dev mode.
	 *
	 * @var bool
	 */
	private bool $is_dev;

	/**
	 * Constructor.
	 *
	 * @param Logger|null $logger Optional logger for debugging.
	 */
	public function __construct(?Logger $logger = null) {
		$this->logger = $logger;
		$this->is_dev = defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * Record a method call with its arguments and call site.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @param int    $depth  Stack trace depth to find caller (default 1).
	 * @return void
	 */
	public function record(string $method, array $args, int $depth = 1): void {
		$trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 2);
		$caller = $trace[$depth + 1] ?? $trace[$depth] ?? array();

		$this->calls[] = array(
			'method' => $method,
			'args'   => $args,
			'file'   => $caller['file'] ?? 'unknown',
			'line'   => $caller['line'] ?? 0,
		);

		$this->logger?->debug('deferred_call.recorded', array(
			'method' => $method,
			'file'   => $caller['file'] ?? 'unknown',
			'line'   => $caller['line'] ?? 0,
		));
	}

	/**
	 * Replay all recorded calls on a target object.
	 *
	 * @param object $target The object to replay calls on.
	 * @return object The target object (for chaining).
	 * @throws \RuntimeException If a method call fails, with enhanced error info.
	 */
	public function replay(object $target): object {
		$this->logger?->debug('deferred_call.replay_start', array(
			'target_class' => get_class($target),
			'call_count'   => count($this->calls),
		));

		foreach ($this->calls as $index => $call) {
			$this->logger?->debug('deferred_call.replaying', array(
				'index'      => $index,
				'method'     => $call['method'],
				'defined_at' => $call['file'] . ':' . $call['line'],
			));

			try {
				$result = $target->{$call['method']}(...$call['args']);
				// If method returns a new builder, use that for subsequent calls
				if (is_object($result)) {
					$target = $result;
				}
			} catch (\Throwable $e) {
				throw new \RuntimeException(
					sprintf(
						"Error replaying %s() defined at %s:%d\n\nOriginal error: %s\n\nCall chain:\n%s",
						$call['method'],
						$call['file'],
						$call['line'],
						$e->getMessage(),
						$this->formatCallChain($index)
					),
					0,
					$e
				);
			}
		}

		$this->logger?->debug('deferred_call.replay_complete', array(
			'target_class' => get_class($target),
		));

		return $target;
	}

	/**
	 * Format the call chain up to a given index for debugging.
	 *
	 * @param int $upToIndex Index to format up to (inclusive).
	 * @return string Formatted call chain.
	 */
	private function formatCallChain(int $upToIndex): string {
		$lines  = array();
		$indent = '';

		for ($i = 0; $i <= $upToIndex && $i < count($this->calls); $i++) {
			$call    = $this->calls[$i];
			$marker  = ($i === $upToIndex) ? ' ← ERROR' : '';
			$lines[] = sprintf(
				'%s└─ %s() at %s:%d%s',
				$indent,
				$call['method'],
				basename($call['file']),
				$call['line'],
				$marker
			);
			$indent .= '   ';
		}

		return implode("\n", $lines);
	}

	/**
	 * Get all recorded calls (for debugging/inspection).
	 *
	 * @return array<int, array{method: string, args: array, file: string, line: int}>
	 */
	public function getCalls(): array {
		return $this->calls;
	}

	/**
	 * Check if any calls have been recorded.
	 *
	 * @return bool
	 */
	public function hasCalls(): bool {
		return !empty($this->calls);
	}

	/**
	 * Clear all recorded calls.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->calls = array();
	}

	/**
	 * Get a human-readable summary of recorded calls.
	 *
	 * @return string
	 */
	public function getSummary(): string {
		if (empty($this->calls)) {
			return 'No deferred calls recorded.';
		}

		$lines = array('Deferred calls (' . count($this->calls) . '):');
		foreach ($this->calls as $i => $call) {
			$lines[] = sprintf(
				'  %d. %s() at %s:%d',
				$i + 1,
				$call['method'],
				basename($call['file']),
				$call['line']
			);
		}

		return implode("\n", $lines);
	}
}
