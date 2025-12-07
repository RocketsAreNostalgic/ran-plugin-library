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
	private ?int $blog_id;
	/** @var int|null */
	private ?int $user_id;
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

	/**
	 * Private constructor. Use the provided static factories to create instances.
	 *
	 * @param string                  $op             Operation identifier (e.g., 'save_all').
	 * @param string                  $main_option    Root option name.
	 * @param string                  $scope          One of 'site'|'network'|'blog'|'user'.
	 * @param int|null                $blogId         Blog ID when scope='blog'.
	 * @param int|null                $userId         User ID when scope='user'.
	 * @param string|null             $user_storage   'meta'|'option' when scope='user'.
	 * @param bool                    $user_global    Whether to use global user storage semantics.
	 * @param bool                    $merge_from_db  Whether to merge from DB before writing.
	 * @param string|null             $key            Single key for targeted operations.
	 * @param array<int,string>|null  $keys           Multiple keys for batch operations.
	 * @param array<string,mixed>|null $options       Full options payload for save-all.
	 * @param array<int,string>|null  $changed_keys   Changed keys for migration.
	 *
	 * @return void
	 */
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
		$this->blog_id       = $blogId;
		$this->user_id       = $userId;
		$this->user_storage  = $user_storage;
		$this->user_global   = $user_global;
		$this->merge_from_db = $merge_from_db;
		$this->key           = $key;
		$this->keys          = $keys;
		$this->options       = $options;
		$this->changed_keys  = $changed_keys;
	}

	// ---------- Public getters ----------
	/**
	 * Get the operation identifier for this write context.
	 *
	 * @return string Operation name (e.g., 'save_all', 'set_option', 'add_option').
	 */
	public function op(): string {
		return $this->op;
	}
	/**
	 * Get the main option (root option name) used for persistence.
	 *
	 * @return string Main option name.
	 */
	public function main_option(): string {
		return $this->main_option;
	}
	/**
	 * Get the scope for this operation.
	 *
	 * @return string One of 'site'|'network'|'blog'|'user'.
	 */
	public function scope(): string {
		return $this->scope;
	}
	/**
	 * Get the target blog ID when scope is 'blog'.
	 *
	 * @return int|null Blog ID or null when not applicable.
	 */
	public function blog_id(): ?int {
		return $this->blog_id;
	}
	/**
	 * Get the target user ID when scope is 'user'.
	 *
	 * @return int|null User ID or null when not applicable.
	 */
	public function user_id(): ?int {
		return $this->user_id;
	}
	/**
	 * Get the user storage backend when scope is 'user'.
	 *
	 * @return string|null 'meta'|'option' or null when not applicable.
	 */
	public function user_storage(): ?string {
		return $this->user_storage;
	}
	/**
	 * Whether user writes should be treated as global (implementation specific).
	 *
	 * @return bool True when global user write behavior is desired.
	 */
	public function user_global(): bool {
		return $this->user_global;
	}
	/**
	 * Whether to merge with the database before persisting.
	 *
	 * @return bool True to merge from DB.
	 */
	public function merge_from_db(): bool {
		return $this->merge_from_db;
	}
	/**
	 * Get a single option key (for single-key operations).
	 *
	 * @return string|null Key or null when not applicable.
	 */
	public function key(): ?string {
		return $this->key;
	}
	/**
	 * Get multiple option keys (for add/multiple operations).
	 *
	 * @return array<int,string>|null List of keys or null when not applicable.
	 */
	public function keys(): ?array {
		return $this->keys;
	}
	/**
	 * Get full options payload for save-all operation.
	 *
	 * @return array<string,mixed>|null Options map or null when not applicable.
	 */
	public function options(): ?array {
		return $this->options;
	}
	/**
	 * Get changed keys for migration scenarios.
	 *
	 * @return array<int,string>|null List of changed keys or null when not applicable.
	 */
	public function changed_keys(): ?array {
		return $this->changed_keys;
	}

	// ---------- Factories (with validation) ----------
	/**
	 * Create a write context for saving the full options payload.
	 *
	 * @param string              $main_option   Root option name.
	 * @param string              $scope         One of 'site'|'network'|'blog'|'user'.
	 * @param int|null            $blogId        Target blog ID when scope='blog'.
	 * @param int|null            $userId        Target user ID when scope='user'.
	 * @param string              $user_storage  'meta'|'option' when scope='user'.
	 * @param bool                $user_global   Whether to use global user storage semantics.
	 * @param array<string,mixed> $options       Options map to persist.
	 * @param bool                $merge_from_db Whether to merge from DB before writing.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context for setting a single option key.
	 *
	 * @param string $main_option  Root option name.
	 * @param string $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null $blogId     Target blog ID when scope='blog'.
	 * @param int|null $userId     Target user ID when scope='user'.
	 * @param string $user_storage 'meta'|'option' when scope='user'.
	 * @param bool   $user_global  Whether to use global user storage semantics.
	 * @param string $key          Option key to set.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context for adding multiple option keys.
	 *
	 * @param string             $main_option  Root option name.
	 * @param string             $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null           $blogId       Target blog ID when scope='blog'.
	 * @param int|null           $userId       Target user ID when scope='user'.
	 * @param string             $user_storage 'meta'|'option' when scope='user'.
	 * @param bool               $user_global  Whether to use global user storage semantics.
	 * @param array<int,string>  $keys         Keys to add.
	 *
	 * @return self New WriteContext instance.
	 */
	public static function for_stage_options(
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
		return new self('stage_options', $main_option, $scope, $blogId, $userId, $user_storage, $user_global, false, null, array_values(array_map('strval', $keys)), null, null);
	}

	/**
	 * Create a write context for adding a single option key.
	 *
	 * @param string $main_option  Root option name.
	 * @param string $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null $blogId     Target blog ID when scope='blog'.
	 * @param int|null $userId     Target user ID when scope='user'.
	 * @param string $user_storage 'meta'|'option' when scope='user'.
	 * @param bool   $user_global  Whether to use global user storage semantics.
	 * @param string $key          Key to add.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context for deleting a single option key.
	 *
	 * @param string $main_option  Root option name.
	 * @param string $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null $blogId     Target blog ID when scope='blog'.
	 * @param int|null $userId     Target user ID when scope='user'.
	 * @param string $user_storage 'meta'|'option' when scope='user'.
	 * @param bool   $user_global  Whether to use global user storage semantics.
	 * @param string $key          Key to delete.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context for clearing all options under the root.
	 *
	 * @param string   $main_option  Root option name.
	 * @param string   $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null $blogId       Target blog ID when scope='blog'.
	 * @param int|null $userId       Target user ID when scope='user'.
	 * @param string   $user_storage 'meta'|'option' when scope='user'.
	 * @param bool     $user_global  Whether to use global user storage semantics.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context that seeds defaults if options are missing.
	 *
	 * @param string            $main_option  Root option name.
	 * @param string            $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null          $blogId       Target blog ID when scope='blog'.
	 * @param int|null          $userId       Target user ID when scope='user'.
	 * @param string            $user_storage 'meta'|'option' when scope='user'.
	 * @param bool              $user_global  Whether to use global user storage semantics.
	 * @param array<int,string> $keys         Default keys snapshot to seed.
	 *
	 * @return self New WriteContext instance.
	 */
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

	/**
	 * Create a write context for migrations (keys changed/renamed, etc.).
	 *
	 * @param string             $main_option   Root option name.
	 * @param string             $scope         One of 'site'|'network'|'blog'|'user'.
	 * @param int|null           $blogId        Target blog ID when scope='blog'.
	 * @param int|null           $userId        Target user ID when scope='user'.
	 * @param string             $user_storage  'meta'|'option' when scope='user'.
	 * @param bool               $user_global   Whether to use global user storage semantics.
	 * @param array<int,string>  $changed_keys  Keys that changed (for migration logic).
	 *
	 * @return self New WriteContext instance.
	 */
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
	/**
	 * Assert a string value is non-empty (helper used by factories).
	 *
	 * @param string $value Value to test.
	 * @param string $field Field name for error context.
	 *
	 * @return void
	 */
	private static function ssert_non_empty(string $value, string $field): void {
		if ($value === '') {
			throw new \InvalidArgumentException('WriteContext: field ' . $field . ' must be non-empty');
		}
	}

	/**
	 * Assert an array is non-empty (helper used by factories).
	 *
	 * @param array<mixed> $arr   Array to test.
	 * @param string       $field Field name for error context.
	 *
	 * @return void
	 */
	private static function ssert_non_emptyArray(array $arr, string $field): void {
		if ($arr === array()) {
			throw new \InvalidArgumentException('WriteContext: field ' . $field . ' must be a non-empty array');
		}
	}

	/**
	 * Normalize and validate the provided scope string.
	 *
	 * @param string $scope Scope value to normalize.
	 *
	 * @return string Normalized scope ('site'|'network'|'blog'|'user').
	 */
	private static function normalize_scope(string $scope): string {
		$scope = strtolower($scope);
		if ($scope !== 'site' && $scope !== 'network' && $scope !== 'blog' && $scope !== 'user') {
			throw new \InvalidArgumentException('WriteContext: invalid scope ' . $scope);
		}
		return $scope;
	}

	/**
	 * Validate blogId/userId/user_storage trio based on scope; returns normalized triplet.
	 *
	 * @param string   $scope        One of 'site'|'network'|'blog'|'user'.
	 * @param int|null $blogId       Blog ID when scope='blog'.
	 * @param int|null $userId       User ID when scope='user'.
	 * @param string|null $user_storage 'meta'|'option' when scope='user'.
	 *
	 * @return array{0:?int,1:?int,2:?string} Tuple of [blogId|null, userId|null, user_storage|null].
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
