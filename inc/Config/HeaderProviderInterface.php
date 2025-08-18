<?php

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

interface HeaderProviderInterface {
	/** Ensure required WP includes are loaded for this environment */
	public function ensure_wp_loaded(): void;

	/** Return associative standard headers for this environment */
	public function get_standard_headers(): array;

	/** Return base identifiers: [base_path, base_url, base_name] */
	public function get_base_identifiers(): array;

	/** Return absolute path to the file whose comment block contains headers */
	public function get_comment_source_path(): string;

	/** Return the ConfigType for this environment */
	public function get_type(): ConfigType;

	/** Default app option slug given derived $slug */
	public function get_default_app_option_slug(string $slug): string;

	/** Environment-specific normalized keys to add (e.g., Basename/File or StylesheetDir/URL) */
	public function get_env_specific_normalized_keys(array $ids): array;
}


