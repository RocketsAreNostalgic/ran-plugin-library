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

	public function register_bool_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->bool_keys[$key] = true;
	}

	public function register_value_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->value_keys[$key] = true;
	}

	public function register_string_key(string $key): void {
		$key = trim($key);
		if ($key === '') {
			return;
		}
		$this->string_keys[$key] = true;
	}

	public function register_nested_rule(string $path, string $type): void {
		$path = trim($path);
		$type = trim($type);
		if ($path === '' || $type === '') {
			return;
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
