<?php
/**
 * FormsService: orchestrates shared component rendering resources across facades.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;

class FormsService {
	private ComponentManifest $manifest;
	private Logger $logger;

	public function __construct(ComponentManifest $manifest, Logger $logger) {
		$this->manifest = $manifest;
		$this->logger   = $logger;
	}

	public function start_session(?FormsAssets $assets = null, array $form_defaults = array(), ?ValidatorPipelineService $pipeline = null): FormsServiceSession {
		$bucket   = $assets ?? new FormsAssets();
		$resolver = new FormsTemplateOverrideResolver($this->logger);
		return new FormsServiceSession($this->manifest, $bucket, $resolver, $this->logger, $form_defaults, $pipeline);
	}

	public function manifest(): ComponentManifest {
		return $this->manifest;
	}

	public function take_warnings(): array {
		return $this->manifest->take_warnings();
	}
}
