<?php
/**
 * SettingsScopeHelper: shared helpers for resolving option scopes in settings contexts.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Options\OptionScope;

final class SettingsScopeHelper {
	private function __construct() {
	}

	/**
	 * Normalize an incoming scope value from context into an OptionScope instance.
	 *
	 * @param array<string,mixed>|null $context
	 */
	public static function parse_scope(?array $context): ?OptionScope {
		if (!is_array($context) || !array_key_exists('scope', $context)) {
			return null;
		}

		$scopeValue = $context['scope'];
		if ($scopeValue instanceof OptionScope) {
			return $scopeValue;
		}
		if ($scopeValue === null) {
			return null;
		}

		try {
			return OptionScope::from((string) $scopeValue);
		} catch (\ValueError $e) {
			return null;
		}
	}

	public static function require_allowed(OptionScope $scope, OptionScope ...$allowed): OptionScope {
		foreach ($allowed as $allowedScope) {
			if ($scope === $allowedScope) {
				return $scope;
			}
		}

		$allowedList = implode(', ', array_map(static fn (OptionScope $value): string => $value->value, $allowed));
		throw new \InvalidArgumentException(
			sprintf('Scope "%s" is not supported. Allowed scopes: %s.', $scope->value, $allowedList)
		);
	}
}
