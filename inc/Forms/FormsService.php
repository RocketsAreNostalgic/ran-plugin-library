<?php
/**
 * FormsService: orchestrates shared component rendering resources across facades.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;

class FormsService {
	private ComponentManifest $manifest;
	private Logger $logger;

	public function __construct(ComponentManifest $manifest, Logger $logger) {
		$this->manifest = $manifest;
		$this->logger   = $logger;
	}

	public function start_session(array $form_defaults = array(), ?ValidatorPipelineService $pipeline = null): FormsServiceSession {
		$resolver = new FormsTemplateOverrideResolver($this->logger);
		return new FormsServiceSession($this->manifest, $resolver, $this->logger, $form_defaults, $pipeline);
	}

	public function manifest(): ComponentManifest {
		return $this->manifest;
	}

	public function take_warnings(): array {
		return $this->manifest->take_warnings();
	}
}
