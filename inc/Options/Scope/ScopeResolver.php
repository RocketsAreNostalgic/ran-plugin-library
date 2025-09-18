<?php
/**
 * ScopeResolver: normalize (scope, entity) -> (OptionScope, storage_args).
 *
 * Centralizes scope handling for factories so callers avoid stringly-typed
 * storage args and get earlier validation. Designed to be used by both
 * Config::options() and new RegisterOptions().
 *
 * NOTE: This is a non-breaking skeleton; integrate gradually.
 *
 * @package Ran\PluginLib\Options\Scope
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Scope;

use InvalidArgumentException;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Options\Entity\ScopeEntity;
use Ran\PluginLib\Options\Storage\StorageContext;

final class ScopeResolver {
	/**
	 * Resolve to a typed StorageContext.
	 *
	 * Behavior:
	 * - If an entity is provided, prefer its scope. If a non-null $scope also disagrees
	 *   with the entity, throw to prevent ambiguous configuration.
	 * - If no entity is provided:
	 *   - site/network: return StorageContext for site/network
	 *   - blog/user: require an entity
	 *
	 * @param OptionScope|string|null $scope
	 * @param ScopeEntity|null $entity
	 * @return StorageContext
	 */
	public static function resolveToContext(OptionScope|string|null $scope, ?ScopeEntity $entity): StorageContext {
		// Normalize scope name (null/empty => 'site')
		if ($scope instanceof OptionScope) {
			$scopeName = strtolower($scope->name);
		} elseif (is_string($scope) && $scope !== '') {
			$scopeName = strtolower($scope);
		} else {
			$scopeName = 'site';
		}

		if ($scopeName === 'site') {
			return StorageContext::forSite();
		}
		if ($scopeName === 'network') {
			return StorageContext::forNetwork();
		}

		if ($scopeName === 'blog' || $scopeName === 'user') {
			if (!($entity instanceof ScopeEntity)) {
				throw new InvalidArgumentException('ScopeResolver: scope "' . $scopeName . '" requires an entity.');
			}
			if ($scopeName === 'blog') {
				if (!($entity instanceof BlogEntity)) {
					throw new InvalidArgumentException('ScopeResolver: Provided entity does not match blog scope.');
				}
				$blogId = (int) ($entity->id ?? 0);
				if ($blogId <= 0) {
					throw new InvalidArgumentException('ScopeResolver: blog scope requires a valid blog_id.');
				}
				return StorageContext::forBlog($blogId);
			}
			// user
			if (!($entity instanceof UserEntity)) {
				throw new InvalidArgumentException('ScopeResolver: Provided entity does not match user scope.');
			}
			$userId      = (int) $entity->id;
			$userStorage = strtolower((string) $entity->storage);
			$userGlobal  = (bool) $entity->global;
			if ($userId <= 0) {
				throw new InvalidArgumentException('ScopeResolver: user scope requires a valid user_id.');
			}
			return StorageContext::forUser($userId, $userStorage, $userGlobal);
		}

		// Fallback
		return StorageContext::forSite();
	}

	/**
	 * Normalize a string scope to OptionScope.
	 *
	 * @param string $scope
	 * @return OptionScope
	 */
	private static function fromString(string $scope): OptionScope {
		$key = strtolower(trim($scope));
		return match ($key) {
			'site', '' => OptionScope::Site,
			'network' => OptionScope::Network,
			'blog'    => OptionScope::Blog,
			'user'    => OptionScope::User,
			default   => OptionScope::Site, // fallback to site instead of throwing
		};
	}
}
