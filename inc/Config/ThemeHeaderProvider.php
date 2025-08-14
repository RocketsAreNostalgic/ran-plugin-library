<?php

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

final class ThemeHeaderProvider implements HeaderProviderInterface {
	public function __construct(private string $stylesheet_dir, private ConfigAbstract $cfg) {
	}

	public function ensure_wp_loaded(): void {
		if (!\function_exists('get_stylesheet_directory') && defined('ABSPATH')) {
			require_once ABSPATH . 'wp-includes/theme.php';
		}
	}

	public function get_standard_headers(): array {
		return $this->cfg->_get_standard_theme_headers($this->stylesheet_dir);
	}

	public function get_base_identifiers(): array {
		$base_url  = function_exists('get_stylesheet_directory_uri') ? get_stylesheet_directory_uri() : '';
		$dir       = $this->stylesheet_dir;
		$base_path = $dir;
		$base_name = basename($dir);
		return array($base_path, $base_url, $base_name);
	}

	public function get_comment_source_path(): string {
		return rtrim($this->stylesheet_dir, '/\\') . '/style.css';
	}

	public function get_type(): ConfigType {
		return ConfigType::Theme;
	}

	public function get_default_app_option_slug(string $slug): string {
		return 'ran_' . $slug . '_app';
	}

	public function get_env_specific_normalized_keys(array $ids): array {
		$path = $ids['base_path'] ?? '';
		$url  = $ids['base_url']  ?? '';
		return array(
		    'StylesheetDir' => $path,
		    'StylesheetURL' => $url,
		);
	}
}


