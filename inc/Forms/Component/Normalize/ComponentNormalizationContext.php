<?php

namespace Ran\PluginLib\Forms\Component\Normalize;

use Ran\PluginLib\Util\Logger;

class ComponentNormalizationContext {
	/** @var array<string,bool> */
	private array $reservedIds = array();
	/** @var array<int,string> */
	private array $warnings   = array();
	private string $component = '';
	private string $fieldId   = '';
	private Logger $logger;

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	public function take_warnings(): array {
		$warnings        = $this->warnings;
		$this->warnings  = array();
		$this->component = '';
		$this->fieldId   = '';
		return $warnings;
	}

	/**
	 * Add a warning message to be displayed to the user.
	 *
	 * @param string $message Warning message
	 */
	public function add_warning(string $message): void {
		$this->warnings[] = $message;
	}

	public function format_attributes(array $attributes): string {
		$parts = array();
		foreach ($attributes as $key => $value) {
			if ($value === null || $value === '') {
				continue;
			}
			$parts[] = sprintf('%s="%s"', esc_attr((string) $key), esc_attr((string) $value));
		}
		return implode(' ', $parts);
	}

	public function append_aria_described_by(array &$attributes, string $id): void {
		if ($id === '') {
			return;
		}
		$existing                       = isset($attributes['aria-describedby']) ? (string) $attributes['aria-describedby'] : '';
		$attributes['aria-describedby'] = trim($existing . ' ' . $id);
	}

	/**
	 * Reserves an Id for use in the DOM.
	 *
	 * @param  string|null $preferred
	 * @param  string      $fallback
	 *
	 * @return string
	 */
	public function reserve_id(?string $preferred, string $fallback): string {
		$original           = $preferred;
		$normalizedOriginal = $preferred !== null ? $this->normalize_id($preferred) : '';
		$base               = $normalizedOriginal;

		if ($base === '') {
			$base = $this->normalize_id($fallback);
		}
		if ($base === '') {
			$base = 'field';
		}
		$candidate = $base;
		$suffix    = 2;
		while (isset($this->reservedIds[$candidate])) {
			$candidate = $base . '__' . $suffix;
			$suffix++;
		}
		$this->reservedIds[$candidate] = true;
		$usedFallback                  = $normalizedOriginal === '';
		$hadCollision                  = $suffix > 2;
		if (($usedFallback || $hadCollision || ($normalizedOriginal !== '' && $candidate !== $normalizedOriginal)) && $this->component !== '') {
			$this->warnings[] = sprintf(
				'Component "%s" (field "%s") normalized DOM id "%s" to "%s".',
				$this->component,
				$this->fieldId,
				$original ?? $fallback,
				$candidate
			);
		}
		return $candidate;
	}

	public function normalize_id(string $value): string {
		$value = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($value));
		return trim((string) $value, '_-');
	}

	public function generate_id(string $name, string $value): string {
		return $this->normalize_id($name . '__' . $value);
	}

	public function reset_state(string $component, string $field_id): void {
		$this->reservedIds = array();
		$this->warnings    = array();
		$this->component   = $component;
		$this->fieldId     = $field_id;
	}

	/**
	 * Get the logger instance.
	 */
	public function get_logger(): Logger {
		return $this->logger;
	}
}
