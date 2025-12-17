<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

final class FormsCallbackInvoker {
	public static function invoke(callable $callback, array $ctx): mixed {
		$callable_parameters = self::get_callable_parameter_count($callback);
		if ($callable_parameters === 0) {
			return $callback();
		}

		return $callback($ctx);
	}

	private static function get_callable_parameter_count(callable $callback): int {
		try {
			if ($callback instanceof \Closure) {
				return (new \ReflectionFunction($callback))->getNumberOfParameters();
			}

			if (is_string($callback)) {
				return (new \ReflectionFunction($callback))->getNumberOfParameters();
			}

			if (is_array($callback) && count($callback) === 2) {
				$object_or_class = $callback[0];
				$method          = $callback[1];
				if (is_string($method)) {
					if (is_object($object_or_class) && method_exists($object_or_class, $method)) {
						return (new \ReflectionMethod($object_or_class, $method))->getNumberOfParameters();
					}
					if (is_string($object_or_class) && method_exists($object_or_class, $method)) {
						return (new \ReflectionMethod($object_or_class, $method))->getNumberOfParameters();
					}
				}
			}

			if (is_object($callback) && method_exists($callback, '__invoke')) {
				return (new \ReflectionMethod($callback, '__invoke'))->getNumberOfParameters();
			}
		} catch (\Throwable $e) {
			return 1;
		}

		return 1;
	}
}
