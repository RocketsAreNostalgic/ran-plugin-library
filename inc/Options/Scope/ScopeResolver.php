<?php
/**
 * ScopeResolver: normalize (scope, entity) -> (OptionScope, storage_args).
 *
 * Centralizes scope handling for factories so callers avoid stringly-typed
 * storage args and get earlier validation. Designed to be used by both
 * Config::options() and RegisterOptions::_from_config().
 *
 * NOTE: This is a non-breaking skeleton; integrate gradually.
 *
 * @package Ran\PluginLib\Options\Scope
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Scope;

use InvalidArgumentException;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Entity\ScopeEntity;

final class ScopeResolver {
	/**
	 * Resolve the normalized OptionScope and validated storage args.
	 *
	 * Behavior (conservative, non-breaking):
	 * - If an entity is provided, prefer its scope and storage args.
	 *   If a non-null $scope is also provided and disagrees with the entity,
	 *   we throw to prevent ambiguous configuration.
	 * - If no entity is provided:
	 *   - site/network: return empty storage args with normalized scope
	 *   - blog/user: currently allow resolution with empty storage args to
	 *     remain non-breaking, but strongly recommend providing an entity.
	 *
	 * @param OptionScope|string|null $scope  Target scope, or null to default to site
	 * @param ScopeEntity|null         $entity Scope entity carrying identifiers
	 * @return array{scope: OptionScope, storage_args: array<string,mixed>}
	 */
	public static function resolve(OptionScope|string|null $scope, ?ScopeEntity $entity): array {
		// Normalize incoming scope to a string key for comparison; null => 'site' (legacy default)
		if ($scope instanceof OptionScope) {
			$scopeName = strtolower($scope->name);
		} elseif (is_string($scope) && $scope !== '') {
			$scopeName = strtolower($scope);
		} else {
			$scopeName = 'site';
		}

		// Fast-path: site semantics (ignore entity, return null scope to avoid extra re-read)
		if ($scopeName === 'site') {
			return array(
			    'scope'        => null,            // preserve prior behavior: null means site and avoids re-read
			    'storage_args' => array(),
			);
		}

		// Network: ignore entity, no storage args
		if ($scopeName === 'network') {
			return array(
			    'scope'        => 'network',
			    'storage_args' => array(),
			);
		}

		// Blog/User: require a matching entity; this mirrored previous validation in Config::options()
		if ($scopeName === 'blog' || $scopeName === 'user') {
			if (!($entity instanceof ScopeEntity)) {
				throw new InvalidArgumentException('ScopeResolver: scope "' . $scopeName . '" requires an entity.');
			}
			$entityScope = strtolower($entity->getScope()->name);
			if ($entityScope !== $scopeName) {
				throw new InvalidArgumentException('ScopeResolver: Provided scope does not match entity scope.');
			}
			return array(
			    'scope'        => $scopeName,
			    'storage_args' => $entity->toStorageArgs(),
			);
		}

		// Unknown scope: fall back to site semantics (ignore entity)
		return array(
		    'scope'        => null,
		    'storage_args' => array(),
		);
	}

	/**
	 * Normalize a string scope to OptionScope.
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
