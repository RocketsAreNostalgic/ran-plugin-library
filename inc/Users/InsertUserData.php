<?php
/**
 * Concrete implementation for inserting user data and applying plugin-scoped per-user options.
 *
 * @internal
 * @package Ran\PluginLib\Users
 */

declare(strict_types=1);

namespace Ran\PluginLib\Users;

use Exception;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

final class InsertUserData extends InsertUserDataAbstract implements InsertUserDataInterface {
	public function __construct(
		private readonly ConfigInterface $config,
		private readonly ?Logger $logger = null,
		private readonly ?WritePolicyInterface $policy = null,
	) {
	}

	/**
	 * Accepts a structured array of user data. Minimal required keys:
	 * - email (string)
	 * - first_name (string)
	 * - last_name (string)
	 *
	 * Optional keys for plugin-scoped per-user options:
	 * - options (array<string,mixed>) — values to persist via RegisterOptions (user scope)
	 * - global (bool) — network-wide flag (only meaningful with storage='option'), default false
	 * - storage ('meta'|'option') — storage backend for per-user options, default 'meta'
	 *
	 * @param  array<string,mixed> $user_data
	 */
	public function insert_user_data(array $user_data): bool|Exception {
		try {
			$email = (string) ($user_data['email'] ?? '');
			$first = (string) ($user_data['first_name'] ?? '');
			$last  = (string) ($user_data['last_name'] ?? '');

			if ($email === '' || $first === '' || $last === '') {
				throw new Exception('InsertUserData: missing required fields (email, first_name, last_name).');
			}

			// Create user via abstract helper (uses logger if provided)
			$userId = parent::insert_user($email, $first, $last, $this->logger);

			// Apply plugin-scoped per-user options if provided
			if (isset($user_data['options']) && is_array($user_data['options'])) {
				$global  = (bool) ($user_data['global'] ?? false);
				$storage = is_string($user_data['storage'] ?? null) ? (string) $user_data['storage'] : 'meta';

				$repo = new UserOptionsStore($this->config);
				$repo->for_user($userId, $global, $storage);
				if ($this->policy instanceof WritePolicyInterface) {
					$repo->with_policy($this->policy);
				}
				$repo->set_many($user_data['options']);
			}

			return true;
		} catch (Exception $e) {
			return $e;
		}
	}

	/**
	 * Initialize feature (no-op for this service).
	 * @return self|false
	 */
	public function init(): self|false {
		return $this;
	}
}
