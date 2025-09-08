<?php
/**
 * Factory for option storage adapters.
 *
 * @internal
 * @package Ran\PluginLib\Options\Storage
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Storage;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Util\WPWrappersTrait;

final class OptionStorageFactory {
	use WPWrappersTrait;
	/** @var ?Logger */
	private ?Logger $_logger = null;

	/**
	 * Optionally inject a Logger instance. If omitted, a default Logger is lazily created.
	 */
	public function __construct(?Logger $logger = null) {
		$this->_logger = $logger;
	}

	/**
	 * Lazy logger accessor
	 */
	private function get_logger(): Logger {
		if ($this->_logger instanceof Logger) {
			return $this->_logger;
		}
		$this->_logger = new Logger();
		return $this->_logger;
	}

	/**
	 * Create an OptionStorageInterface for the given scope and arguments.
	 *
	 * @param string|OptionScope $scope Scope identifier or enum.
	 * @param array{blog_id?:int,user_id?:int,user_global?:bool,user_storage?:'option'|'meta'} $args Additional constructor args
	 * @return OptionStorageInterface
	 */
	public function make(string|OptionScope $scope, array $args = array()): OptionStorageInterface {
		$logger = $this->get_logger();
		$enum   = \is_string($scope) ? self::normalize_scope($scope) : $scope;

		if ($logger->is_active()) {
			$logger->debug('OptionStorageFactory::make - normalized scope.', array(
			    'input_scope' => is_string($scope) ? $scope : $scope->name,
			    'enum'        => $enum->name,
			    'args_keys'   => array_keys($args),
			));
		}

		return match ($enum) {
			OptionScope::Site    => new SiteOptionStorage(),
			OptionScope::Network => new NetworkOptionStorage(),
			OptionScope::Blog    => (function () use ($args, $logger) {
				$blogId = isset($args['blog_id']) ? (int) $args['blog_id'] : (int) $this->_do_get_current_blog_id();
				if ($logger->is_active()) {
					$logger->debug('OptionStorageFactory::make - constructing BlogOptionStorage.', array(
					    'blog_id_arg_present' => array_key_exists('blog_id', $args),
					    'blog_id'             => $blogId,
					    'used_current_blog'   => !isset($args['blog_id']),
					));
				}
				return new BlogOptionStorage($blogId);
			})(),
			OptionScope::User => (function () use ($args, $logger) {
				$userId      = self::require_int($args, 'user_id');
				$userGlobal  = (bool) ($args['user_global'] ?? false);
				$userStorage = isset($args['user_storage']) ? strtolower((string) $args['user_storage']) : 'meta';
				if ($userStorage === 'meta') {
					if ($logger->is_active()) {
						$logger->debug('OptionStorageFactory::make - constructing UserMetaStorage.', array(
						    'user_id'      => $userId,
						    'user_storage' => $userStorage,
						));
					}
					return new UserMetaStorage($userId);
				}
				if ($logger->is_active()) {
					$logger->debug('OptionStorageFactory::make - constructing UserOptionStorage.', array(
					    'user_id'     => $userId,
					    'user_global' => $userGlobal,
					));
				}
				return new UserOptionStorage($userId, $userGlobal);
			})(),
		};
	}

	/**
	 * Normalize a string to OptionScope enum.
	 */
	private static function normalize_scope(string $scope): OptionScope {
		$s = strtolower(trim($scope));
		return match ($s) {
			'site'    => OptionScope::Site,
			'blog'    => OptionScope::Blog,
			'network' => OptionScope::Network,
			'user'    => OptionScope::User,
			default   => throw new \InvalidArgumentException("Unknown scope '$scope'")
		};
	}

	/**
	 * Require an integer key in args or throw.
	 * @param array $args
	 * @param string $key
	 * @return int
	 */
	private static function require_int(array $args, string $key): int {
		if (!array_key_exists($key, $args)) {
			throw new \InvalidArgumentException("Missing required argument: $key");
		}
		$val = $args[$key];
		if (!\is_int($val)) {
			throw new \InvalidArgumentException("Argument '$key' must be int");
		}
		return $val;
	}
}
