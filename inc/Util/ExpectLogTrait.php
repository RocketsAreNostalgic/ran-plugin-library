<?php
/**
 * ExpectLogTrait.php
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <support@ran.org>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Util;

/**
 * ExpectLogTrait.php
 *
 * @package Ran\PluginLib\Util
 * @author  Ran Plugin Lib <support@ran.org>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.1.0
 */
trait ExpectLogTrait {
	/**
	 * Verifies that log messages were recorded by the CollectingLogger.
	 *
	 * This helper inspects logs collected by a `CollectingLogger` instance
	 * to verify that a message matching the specified criteria was logged the
	 * expected number of times. It checks for the presence of one or more
	 * substrings within the log message, making tests less brittle than
	 * matching exact strings.
	 *
	 * It requires the test class to have a `logger_mock` property that is an
	 * instance of `\Ran\PluginLib\Tests\Unit\Doubles\CollectingLogger`.
	 *
	 * @param string       $level            The log level to check (e.g., 'debug', 'warning').
	 * @param string|array $message_contains A substring or array of substrings the log message must contain.
	 * @param int          $times            The number of times the log is expected to be found.
	 * @param bool         $verbose          If true, prints detailed failure information to STDERR.
	 * @param bool         $debug            If true, prints diagnostic information to STDERR at the start of the check.
	 * @return void
	 */
	protected function expectLog(string $level, string|array $message_contains, int $times = 1, bool $verbose = false, bool $debug = false): void {
		if (!is_array($message_contains)) {
			$message_contains = array($message_contains);
		}

		if (!$this->logger_mock instanceof \Ran\PluginLib\Util\CollectingLogger) {
			$this->fail('\n\n[EXPECTLOG] The expectLog() helper requires a CollectingLogger property named "logger_mock" to be set on the test class.');
		}

		if ($verbose) {
			fprintf(STDERR, "\n\n[EXPECTLOG] START ----------------------\n");
			fprintf(STDERR, "[EXPECTLOG] Expecting %d message(s) of level '%s' containing: '%s'.\n", $times, $level, implode("', '", $message_contains));
		}

		$all_logs    = $this->logger_mock->get_logs();
		$found_count = 0;

		foreach ($all_logs as $log_entry) {
			if ($log_entry['level'] !== $level) {
				continue;
			}

			$log_message          = $log_entry['message'];
			$all_substrings_found = true;
			foreach ($message_contains as $substring) {
				if (strpos($log_message, $substring) === false) {
					$all_substrings_found = false;
					break;
				}
			}

			if ($all_substrings_found) {
				$found_count++;
			}
		}

		if ($found_count === $times) {
			$this->assertTrue(true, "Log expectation met for level '{$level}'.");
			return;
		}

		// --- Failure Path ---
		$failure_message = sprintf(
			"Expected to find %d log message(s) of level '%s' containing ['%s'], but found %d.",
			$times,
			$level,
			implode("', '", $message_contains),
			$found_count
		);

		if ($verbose) {
			fprintf(STDERR, "[EXPECTLOG] EXPECTATION FAILED \n");
			fprintf(STDERR, "[EXPECTLOG] Expected parts: '%s'\n", implode("', '", $message_contains));
			fprintf(STDERR, "[EXPECTLOG] Expected times: %d\n", $times);

			$relevant_logs = array_filter($all_logs, fn($log) => $log['level'] === $level);

			if (empty($relevant_logs)) {
				fprintf(STDERR, "[EXPECTLOG] Received: No logs for level '%s' were recorded.\n", $level);
			} else {
				fprintf(STDERR, "[EXPECTLOG] Received logs for level '%s':\n", $level);
				foreach ($relevant_logs as $log_entry) {
					fprintf(STDERR, "[EXPECTLOG]  - %s\n", $log_entry['message']);
				}
			}
		}

		if ($verbose && $debug) {
			fprintf(STDERR, "\n\n[EXPECTLOG DEBUG] All logging messages:\n");
			foreach ($all_logs as $log_entry) {
				fprintf(STDERR, "[EXPECTLOG DEBUG] LEVEL: '%s', MESSAGE: '%s'", $log_entry['level'], $log_entry['message']);
			}
		}

		if ( $verbose ) {
			fprintf(STDERR, "\n[expectLog] END ----------------------\n\n");
		}

		$this->fail($failure_message);
	}
}
