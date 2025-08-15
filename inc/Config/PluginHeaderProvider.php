<?php

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

final class PluginHeaderProvider implements HeaderProviderInterface {
	public function __construct(private string $plugin_file, private ConfigAbstract $cfg) {
	}

	public function ensure_wp_loaded(): void {
		// @codeCoverageIgnoreStart
		if (!\function_exists('get_plugin_data') && defined('ABSPATH')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// @codeCoverageIgnoreEnd
	}

	public function get_standard_headers(): array {
		return $this->cfg->_get_standard_plugin_headers($this->plugin_file);
	}

	public function get_base_identifiers(): array {
		$base_url  = \function_exists('plugin_dir_url')  ? \plugin_dir_url($this->plugin_file)  : '';
		$base_path = \function_exists('plugin_dir_path') ? \plugin_dir_path($this->plugin_file) : '';
		$base_name = \function_exists('plugin_basename') ? \plugin_basename($this->plugin_file) : basename($this->plugin_file);
		return array($base_path, $base_url, $base_name);
	}

	public function get_comment_source_path(): string {
		return $this->plugin_file;
	}

	public function get_type(): ConfigType {
		return ConfigType::Plugin;
	}

	public function get_default_app_option_slug(string $slug): string {
		return $slug; // plugins default to derived slug as app option
	}

	public function get_env_specific_normalized_keys(array $ids): array {
		return array(
		    'Basename' => $ids['base_name'] ?? '',
		    'File'     => $this->plugin_file,
		);
	}
}


