<?php
/**
 * WriteContext – typed policy context for persistence gates
 *
 * @internal
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

final class WriteContext {
	/** @var string */
	private string $op;
	/** @var string */
	private string $main_option;
	/** @var string */
	private string $scope; // 'site'|'network'|'blog'|'user'
	/** @var int|null */
	private ?int $blogId;
	/** @var int|null */
	private ?int $userId;
	/** @var string|null */
	private ?string $user_storage; // 'meta'|'option' when scope=user
	/** @var bool */
	private bool $user_global;
	/** @var bool */
	private bool $merge_from_db;

	// Op-specific
	/** @var string|null */
	private ?string $key;
	/** @var array<int,string>|null */
	private ?array $keys;
	/** @var array<string, mixed>|null */
	private ?array $options;
	/** @var array<int,string>|null */
	private ?array $changed_keys;

	private function __construct(
		string $op,
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		?string $user_storage,
		bool $user_global,
		bool $merge_from_db,
		?string $key,
		?array $keys,
		?array $options,
		?array $changed_keys
	) {
		$this->op            = $op;
		$this->main_option   = $main_option;
		$this->scope         = $scope;
		$this->blogId        = $blogId;
		$this->userId        = $userId;
		$this->user_storage  = $user_storage;
		$this->user_global   = $user_global;
		$this->merge_from_db = $merge_from_db;
		$this->key           = $key;
		$this->keys          = $keys;
		$this->options       = $options;
		$this->changed_keys  = $changed_keys;
	}

	// ---------- Public getters ----------
	public function op(): string {
		return $this->op;
	}
	public function main_option(): string {
		return $this->main_option;
	}
	public function scope(): string {
		return $this->scope;
	}
	public function blogId(): ?int {
		return $this->blogId;
	}
	public function userId(): ?int {
		return $this->userId;
	}
	public function user_storage(): ?string {
		return $this->user_storage;
	}
	public function user_global(): bool {
		return $this->user_global;
	}
	public function merge_from_db(): bool {
		return $this->merge_from_db;
	}
	public function key(): ?string {
		return $this->key;
	}
	/** @return array<int,string>|null */
	public function keys(): ?array {
		return $this->keys;
	}
	/** @return array<string,mixed>|null */
	public function options(): ?array {
		return $this->options;
	}
	/** @return array<int,string>|null */
	public function changed_keys(): ?array {
		return $this->changed_keys;
	}

	// ---------- Factories (with validation) ----------
	public static function for_save_all(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		array $options,
		bool $merge_from_db
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('save_all', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, $merge_from_db, null, null, $options, null);
	}

	public static function for_set_option(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		string $key
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_empty($key, 'key');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('set_option', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, $key, null, null, null);
	}

	/** @param array<int,string> $keys */
	public static function for_add_options(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		array $keys
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_emptyArray($keys, 'keys');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('add_options', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, null, array_values(array_map('strval', $keys)), null, null);
	}

	public static function for_add_option(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		string $key
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_empty($key, 'key');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('add_option', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, $key, null, null, null);
	}

	public static function for_delete_option(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		string $key
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_empty($key, 'key');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('delete_option', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, $key, null, null, null);
	}

	public static function for_clear(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('clear', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, null, null, null, null);
	}

	public static function for_seed_if_missing(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		array $keys // defaults keys snapshot
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_emptyArray($keys, 'keys');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('seed_if_missing', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, null, array_values(array_map('strval', $keys)), null, null);
	}

	/** @param array<int,string> $changed_keys */
	public static function for_migrate(
		string $main_option,
		string $scope,
		?int $blogId,
		?int $userId,
		string $user_storage,
		bool $user_global,
		array $changed_keys
	): self {
		self::ssert_non_empty($main_option, 'main_option');
		self::ssert_non_emptyArray($changed_keys, 'changed_keys');
		$scope                            = self::normalize_scope($scope);
		[$blogId, $userId, $user_storage] = self::validate_scope_triplet($scope, $blogId, $userId, $user_storage);
		return new self('migrate', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, null, null, null, array_values(array_map('strval', $changed_keys)));
	}

	// ---------- Validation helpers ----------
	private static function ssert_non_empty(string $value, string $field): void {
		if ($value === '') {
			throw new \InvalidArgumentException('WriteContext: field ' . $field . ' must be non-empty');
		}
	}

	/** @param array<mixed> $arr */
	private static function ssert_non_emptyArray(array $arr, string $field): void {
		if ($arr === array()) {
			throw new \InvalidArgumentException('WriteContext: field ' . $field . ' must be a non-empty array');
		}
	}

	private static function normalize_scope(string $scope): string {
		$scope = strtolower($scope);
		if ($scope !== 'site' && $scope !== 'network' && $scope !== 'blog' && $scope !== 'user') {
			throw new \InvalidArgumentException('WriteContext: invalid scope ' . $scope);
		}
		return $scope;
	}

	/**
	 * Validate blogId/userId/user_storage trio based on scope; returns normalized triplet
	 * @return array{0:?int,1:?int,2:?string}
	 */
	private static function validate_scope_triplet(string $scope, ?int $blogId, ?int $userId, ?string $user_storage): array {
		if ($scope === 'blog') {
			if (!is_int($blogId)) {
				throw new \InvalidArgumentException('WriteContext: blog scope requires blogId');
			}
			return array($blogId, null, null);
		}
		if ($scope === 'user') {
			if (!is_int($userId)) {
				throw new \InvalidArgumentException('WriteContext: user scope requires userId');
			}
			$user_storage = strtolower((string)($user_storage ?? 'meta'));
			if ($user_storage !== 'meta' && $user_storage !== 'option') {
				throw new \InvalidArgumentException('WriteContext: user_storage must be meta|option');
			}
			return array(null, $userId, $user_storage);
		}
		// site/network
		return array(null, null, null);
	}
}
