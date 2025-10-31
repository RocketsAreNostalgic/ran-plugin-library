<?php
/**
 * FormsRaterLimiter: Rate limiter for front end forms.
 *
 * @package Ran\PluginLib\Forms
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */
declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\WPWrappersTrait;

class FormsRaterLimiter {
	use WPWrappersTrait;

	private int $max_attempts;
	private int $time_window;

	public function __construct(int $max_attempts = 5, int $time_window = 300) {
		$this->max_attempts = $max_attempts;
		$this->time_window  = $time_window; // 5 minutes
	}

	public function check_rate_limit(string $form_id, ?int $user_id = null): bool {
		$identifier = $this->get_rate_limit_key($form_id, $user_id);
		$attempts   = $this->_do_get_transient($identifier) ?: 0;

		return $attempts < $this->max_attempts;
	}

	public function record_attempt(string $form_id, ?int $user_id = null): void {
		$identifier = $this->get_rate_limit_key($form_id, $user_id);
		$attempts   = $this->_do_get_transient($identifier) ?: 0;

		$this->_do_set_transient($identifier, $attempts + 1, $this->time_window);
	}

	public function get_remaining_attempts(string $form_id, ?int $user_id = null): int {
		$identifier = $this->get_rate_limit_key($form_id, $user_id);
		$attempts   = $this->_do_get_transient($identifier) ?: 0;

		return max(0, $this->max_attempts - $attempts);
	}

	private function get_rate_limit_key(string $form_id, ?int $user_id): string {
		// Rate limit by user ID if logged in, IP if anonymous
		if ($user_id) {
			return "form_rate_limit_{$form_id}_user_{$user_id}";
		}

		$ip = $this->get_client_ip();
		return "form_rate_limit_{$form_id}_ip_" . md5($ip);
	}

	private function get_client_ip(): string {
		// WordPress-safe IP detection
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		}

		return $this->_do_sanitize_text_field($ip);
	}
}
