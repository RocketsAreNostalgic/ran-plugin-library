<?php
declare(strict_types=1);

namespace Ran\PluginLib\Forms\Upload;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\MediaHandler;
use Ran\PluginLib\EnqueueAccessory\ScriptsHandler;

/**
 * Provides registration helpers for the Forms media picker assets.
 */
class MediaPickerAssets {
	private const SCRIPT_HANDLE = 'ran-forms-media-picker';
	private const SCRIPT_PATH   = '/inc/Forms/assets/media-picker.js';

	public function __construct(
		private ConfigInterface $config,
		private ScriptsHandler $scripts_handler,
		private MediaHandler $media_handler
	) {
	}

	/**
	 * Registers media picker script and wp_enqueue_media requirements for admin & public contexts.
	 */
	public function register(): void {
		$cfg     = $this->config->get_config();
		$baseUrl = isset($cfg['URL']) ? rtrim((string) $cfg['URL'], '/\\') : '';
		if ($baseUrl === '') {
			return;
		}

		$this->ensureMediaToolsStaged();
		$this->registerScripts($baseUrl, isset($cfg['Version']) ? (string) $cfg['Version'] : null);
	}

	private function ensureMediaToolsStaged(): void {
		$this->media_handler->add(
			array(
				array(
					'hook' => 'admin_enqueue_scripts',
					'args' => array(),
				),
				array(
					'hook' => 'wp_enqueue_scripts',
					'args' => array(),
				),
			)
		);
	}

	private function registerScripts(string $baseUrl, ?string $version): void {
		$asset = array(
			'handle' => self::SCRIPT_HANDLE,
			'src'    => $baseUrl . self::SCRIPT_PATH,
			'deps'   => array('media-editor', 'media-views'),
		);
		if ($version !== null && $version !== '') {
			$asset['version'] = $version;
		}

		$this->scripts_handler->add(
			array(
				array_merge($asset, array('hook' => 'admin_enqueue_scripts')),
				array_merge($asset, array('hook' => 'wp_enqueue_scripts')),
			)
		);
	}
}

