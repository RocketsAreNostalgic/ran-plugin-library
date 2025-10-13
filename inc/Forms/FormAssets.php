<?php
/**
 * FormAssets: Aggregates per-component asset declarations for settings and forms.
 *
 * @package Ran\PluginLib\Forms
 *
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

/**
 * Collects script/style definitions emitted by components and tracks media requirements.
 */
final class FormAssets {
	/** @var array<string, ScriptDefinition> */
	private array $scripts = array();
	/** @var array<string, StyleDefinition> */
	private array $styles        = array();
	private bool $requires_media = false;

	public function ingest(ComponentRenderResult $result): void {
		if ($result->has_script()) {
			$this->scripts[$result->script->handle] = $result->script;
		}
		if ($result->has_style()) {
			$this->styles[$result->style->handle] = $result->style;
		}
		if ($result->requires_media) {
			$this->requires_media = true;
		}
	}

	/**
	 * @return array<string, ScriptDefinition>
	 */
	public function scripts(): array {
		return $this->scripts;
	}

	/**
	 * @return array<string, StyleDefinition>
	 */
	public function styles(): array {
		return $this->styles;
	}

	public function requires_media(): bool {
		return $this->requires_media;
	}

	public function has_assets(): bool {
		return $this->requires_media || $this->scripts !== array() || $this->styles !== array();
	}
}
