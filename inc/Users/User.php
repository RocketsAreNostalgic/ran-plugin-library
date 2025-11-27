<?php
/**
 * Fluent User builder for creating users and applying plugin-scoped per-user settings.
 *
 * NOTE: This API is currently in beta and subject to change. Feedback is welcome.
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

final class User implements UserBuilderInterface {
	private string $email          = '';
	private string $login          = '';
	private string $first          = '';
	private string $last           = '';
	private string $role           = 'subscriber';
	private ?string $password      = null;
	private bool $generatePassword = true;
	private bool $notify           = false;

	// Options (RegisterOptions user scope)
	private bool $optGlobal    = false;
	private string $optStorage = 'meta'; // 'meta'|'option'
	/** @var array<string,mixed> */
	private array $optValues = array();
	/** @var array<string,mixed> */
	private array $optSchema              = array();
	private bool $optSeedDefaults         = false;
	private bool $optFlushOnSchema        = false;
	private ?WritePolicyInterface $policy = null;

	// Existing user policy: attach|fail|update-profile
	private string $onExists = 'attach';

	public function __construct(
		private readonly ConfigInterface $config,
		private readonly ?Logger $logger = null,
	) {
	}

	public function email(string $email): self {
		$this->email = $email;
		return $this;
	}
	public function login(string $login): self {
		$this->login = $login;
		return $this;
	}
	public function name(string $first, string $last): self {
		$this->first = $first;
		$this->last  = $last;
		return $this;
	}
	public function role(string $role = 'subscriber'): self {
		$this->role = $role;
		return $this;
	}
	public function password(string $password): self {
		$this->password         = $password;
		$this->generatePassword = false;
		return $this;
	}
	public function generate_password(): self {
		$this->generatePassword = true;
		$this->password         = null;
		return $this;
	}
	public function notify(bool $send = true): self {
		$this->notify = $send;
		return $this;
	}

	public function user_scope(bool $global = false, string $storage = 'meta'): self {
		$this->optGlobal  = $global;
		$this->optStorage = $storage;
		return $this;
	}
	/** @param array<string,mixed> $kv */
	public function options(array $kv): self {
		$this->optValues = $kv;
		return $this;
	}
	/** @param array<string,mixed> $schema */
	public function schema(array $schema, bool $seed_defaults = false, bool $flush = false): self {
		$this->optSchema        = $schema;
		$this->optSeedDefaults  = $seed_defaults;
		$this->optFlushOnSchema = $flush;
		return $this;
	}
	public function with_policy(WritePolicyInterface $policy): self {
		$this->policy = $policy;
		return $this;
	}
	public function on_exists(string $policy): self {
		$this->onExists = $policy;
		return $this;
	}

	public function create(): UserResult {
		// Validate required input
		if ($this->email === '') {
			throw new Exception('User builder: email is required.');
		}
		if ($this->first === '' && $this->last === '') {
			// Allow empty names but log if provided API expected names
			if ($this->logger && $this->logger->is_active()) {
				$this->logger->debug('User builder: proceeding without first/last name.');
			}
		}

		// Detect existing user by email when function exists
		$existingId = null;
		if (function_exists('get_user_by')) {
			$u = \get_user_by('email', $this->email);
			if ($u && is_object($u) && isset($u->ID)) {
				$existingId = (int) $u->ID;
			}
		}

		if (null !== $existingId) {
			if ($this->onExists === 'fail') {
				throw new Exception('User builder: user already exists for email.');
			}
			// attach or update-profile
			$userId = $existingId;
			if ($this->onExists === 'update-profile') {
				$this->update_profile_allowlist($userId);
			}
			$this->apply_options($userId);
			return new UserResult($userId, $this->email, $this->login_or_derived(), false);
		}

		// Create new user via helper
		$userId = InsertUserDataAbstract::insert_user(
			$this->email,
			$this->first,
			$this->last,
			$this->logger
		);

		// Optionally set role/password/notify if functions exist
		if ($this->password !== null && function_exists('wp_set_password')) {
			\wp_set_password($this->password, $userId);
		}
		if ($this->role !== '' && function_exists('wp_update_user')) {
			\wp_update_user(array('ID' => $userId, 'role' => $this->role));
		}
		if ($this->notify && function_exists('wp_new_user_notification')) {
			// WP 5.7+: wp_new_user_notification( $user_id, null, 'user' )
			try {
				\wp_new_user_notification($userId, null, 'user');
			} catch (\Throwable $e) { /* ignore */
			}
		}

		$this->apply_options($userId);
		return new UserResult($userId, $this->email, $this->login_or_derived(), true);
	}

	private function login_or_derived(): string {
		if ($this->login !== '') {
			return $this->login;
		}
		return strtolower($this->email);
	}

	private function update_profile_allowlist(int $userId): void {
		// Minimal, guarded updates
		if ($this->role !== '' && function_exists('wp_update_user')) {
			\wp_update_user(array('ID' => $userId, 'role' => $this->role));
		}
		if (($this->first !== '' || $this->last !== '') && function_exists('update_user_meta')) {
			if ($this->first !== '') {
				\update_user_meta($userId, 'first_name', $this->first);
			}
			if ($this->last !== '') {
				\update_user_meta($userId, 'last_name', $this->last);
			}
		}
	}

	private function apply_options(int $userId): void {
		if (empty($this->optSchema) && empty($this->optValues)) {
			return;
		}
		$store = new UserOptionsStore($this->config);
		$store->for_user($userId, $this->optGlobal, $this->optStorage);
		if ($this->policy instanceof WritePolicyInterface) {
			$store->with_policy($this->policy);
		}
		if (!empty($this->optSchema)) {
			$store->register_schema($this->optSchema, $this->optSeedDefaults, $this->optFlushOnSchema);
		}
		if (!empty($this->optValues)) {
			$store->set_many($this->optValues);
		}
	}
}
