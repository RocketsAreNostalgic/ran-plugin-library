<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use WP_Mock;

function register_translation_function_stubs(): void {
	$stringCallbacks = array(
		'__' => static function ($text, $domain = '') {
			return sprintf('__(%s) %s', $domain, $text);
		},
		'_x' => static function ($text, $context, $domain = '') {
			return sprintf('_x(%s|%s) %s', $domain, $context, $text);
		},
		'esc_html__' => static function ($text, $domain = '') {
			return sprintf('esc_html__(%s) %s', $domain, $text);
		},
		'esc_html_x' => static function ($text, $context, $domain = '') {
			return sprintf('esc_html_x(%s|%s) %s', $domain, $context, $text);
		},
		'esc_attr__' => static function ($text, $domain = '') {
			return sprintf('esc_attr__(%s) %s', $domain, $text);
		},
		'esc_attr_x' => static function ($text, $context, $domain = '') {
			return sprintf('esc_attr_x(%s|%s) %s', $domain, $context, $text);
		},
		'_n' => static function ($single, $plural, $number, $domain = '') {
			$chosen = $number === 1 ? $single : $plural;
			return sprintf('_n(%s|%d) %s', $domain, $number, $chosen);
		},
		'_nx' => static function ($single, $plural, $number, $context, $domain = '', $args = array()) {
			$chosen = $number === 1 ? $single : $plural;
			return sprintf('_nx(%s|%d|%s) %s', $domain, $number, $context, $chosen);
		},
	);

	WP_Mock::userFunction('_n')
		->withAnyArgs()
		->zeroOrMoreTimes()
		->andReturnUsing($stringCallbacks['_n']);

	foreach ($stringCallbacks as $name => $callback) {
		WP_Mock::userFunction($name)
			->withAnyArgs()
			->zeroOrMoreTimes()
			->andReturnUsing($callback);

		WP_Mock::userFunction('Ran\\PluginLib\\Util\\' . $name)
			->withAnyArgs()
			->zeroOrMoreTimes()
			->andReturnUsing($callback);

		if ($name === '__') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do___')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === '_x') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_x')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === '_n') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_n')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === '_nx') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_nx')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === 'esc_html__') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_html__')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === 'esc_html_x') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_html_x')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === 'esc_attr__') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_attr__')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		} elseif ($name === 'esc_attr_x') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_attr_x')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing($callback);
		}
	}

	$echoCallbacks = array(
		'_e' => static function ($text, $domain = ''): void {
			echo sprintf('_e(%s) %s', $domain, $text);
		},
		'esc_html_e' => static function ($text, $domain = ''): void {
			echo sprintf('esc_html_e(%s) %s', $domain, $text);
		},
		'esc_attr_e' => static function ($text, $domain = ''): void {
			echo sprintf('esc_attr_e(%s) %s', $domain, $text);
		},
	);

	foreach ($echoCallbacks as $name => $callback) {
		WP_Mock::userFunction($name)
			->withAnyArgs()
			->zeroOrMoreTimes()
			->andReturnUsing(function (...$args) use ($callback) {
				$callback(...$args);
				return null;
			});

		WP_Mock::userFunction('Ran\\PluginLib\\Util\\' . $name)
			->withAnyArgs()
			->zeroOrMoreTimes()
			->andReturnUsing(function (...$args) use ($callback) {
				$callback(...$args);
				return null;
			});

		if ($name === '_e') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_e')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing(function (...$args) use ($callback) {
					$callback(...$args);
					return null;
				});
		} elseif ($name === 'esc_html_e') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_html_e')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing(function (...$args) use ($callback) {
					$callback(...$args);
					return null;
				});
		} elseif ($name === 'esc_attr_e') {
			WP_Mock::userFunction('Ran\\PluginLib\\Util\\_do_esc_attr_e')
				->withAnyArgs()
				->zeroOrMoreTimes()
				->andReturnUsing(function (...$args) use ($callback) {
					$callback(...$args);
					return null;
				});
		}
	}
}
