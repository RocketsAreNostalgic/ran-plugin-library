<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

interface FormsUpdateRouterInterface {
	/**
	 * @param array<string, callable(array): void> $handlers
	 * @param callable(string, array): void $fallback
	 */
	public function route(string $type, array $data, array $handlers, callable $fallback): void;

	/**
	 * @param array<string, callable(array): void> $handlers
	 * @param callable(string, array): void $fallback
	 */
	public function create_update_function(array $handlers, callable $fallback): callable;
}

class FormsUpdateRouter implements FormsUpdateRouterInterface {
	public function route(string $type, array $data, array $handlers, callable $fallback): void {
		$handler = $handlers[$type] ?? null;
		if (is_callable($handler)) {
			$handler($data);
			return;
		}

		$fallback($type, $data);
	}

	public function create_update_function(array $handlers, callable $fallback): callable {
		return function (string $type, array $data) use ($handlers, $fallback): void {
			$this->route($type, $data, $handlers, $fallback);
		};
	}
}
