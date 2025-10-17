<?php
/**
 * FormService: orchestrates shared component rendering resources across facades.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Forms\Component\ComponentManifest;

class FormService {
	private ComponentManifest $manifest;

	public function __construct(ComponentManifest $manifest) {
		$this->manifest = $manifest;
	}

	public function start_session(?FormAssets $assets = null): FormServiceSession {
		$bucket = $assets ?? new FormAssets();
		return new FormServiceSession($this->manifest, $bucket);
	}

	public function manifest(): ComponentManifest {
		return $this->manifest;
	}

	public function take_warnings(): array {
		return $this->manifest->take_warnings();
	}
}
