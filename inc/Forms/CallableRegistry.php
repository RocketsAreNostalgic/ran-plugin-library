<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

final class CallableRegistry {
	/** @var array<string,bool> */
	private array $bool_keys = array();
	/** @var array<string,bool> */
	private array $value_keys = array();
	/** @var array<string,bool> */
	private array $string_keys = array();
	/** @var array<string,string> */
	private array $nested_rules = array();

	private function assert_key_not_registered_elsewhere(string $key, string $family): void {
		if ($family !== 'bool' && isset($this->bool_keys[$key])) {
			throw new \InvalidArgumentException(sprintf('CallableRegistry: key "%s" already registered as bool key.', $key));
		}
		if ($family !== 'value' && isset($this->value_keys[$key])) {
			throw new \InvalidArgumentException(sprintf('CallableRegistry: key "%s" already registered as value key.', $key));
		}
		if ($family !== 'string' && isset($this->string_keys[$key])) {
			throw new \InvalidArgumentException(sprintf('CallableRegistry: key "%s" already registered as string key.', $key));
		}
	}

	public function register_bool_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->assert_key_not_registered_elsewhere($key, 'bool');
		$this->bool_keys[$key] = true;
	}

	public function register_value_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->assert_key_not_registered_elsewhere($key, 'value');
		$this->value_keys[$key] = true;
	}

	public function register_string_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->assert_key_not_registered_elsewhere($key, 'string');
		$this->string_keys[$key] = true;
	}

	public function register_nested_rule(string $path, string $type): void {
		$path = trim($path);
		$type = trim($type);
		if ($path === '' || $type === '') {
			return;
		}
		if (isset($this->nested_rules[$path]) && $this->nested_rules[$path] !== $type) {
			throw new \InvalidArgumentException(sprintf('CallableRegistry: nested rule "%s" already registered as type "%s".', $path, $this->nested_rules[$path]));
		}
		$this->nested_rules[$path] = $type;
	}

	/**
	 * @return array<int,string>
	 */
	public function bool_keys(): array {
		$keys = array_keys($this->bool_keys);
		sort($keys);
		return $keys;
	}

	/**
	 * @return array<int,string>
	 */
	public function value_keys(): array {
		$keys = array_keys($this->value_keys);
		sort($keys);
		return $keys;
	}

	/**
	 * @return array<int,string>
	 */
	public function string_keys(): array {
		$keys = array_keys($this->string_keys);
		sort($keys);
		return $keys;
	}

	/**
	 * @return array<string,string>
	 */
	public function nested_rules(): array {
		return $this->nested_rules;
	}
}
