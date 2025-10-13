<?php
/**
 * Basic Template Resolution Infrastructure
 *
 * This is a stub implementation that provides basic template override support
 * and prepares the interface for future hierarchical enhancement in the
 * template architecture standardization sprint.
 *
 * @package  RanPluginLib\Forms\Renderer
 * @author   Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license  GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link     https://github.com/RocketsAreNostalgic
 * @since    0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Renderer;

use Ran\PluginLib\Util\Logger;

/**
 * Basic template resolution with override support.
 *
 * This stub implementation provides:
 * - Simple template override resolution
 * - Template existence validation
 * - Fallback logic for missing templates
 * - Interface prepared for future hierarchical enhancement
 */
class TemplateResolver {
	/**
	 * Template override map.
	 * Structure: ['template_name' => 'override_template_path']
	 *
	 * @var array<string, string>
	 */
	private array $overrides = array();

	/**
	 * Base template directory path.
	 *
	 * @var string
	 */
	private string $base_path;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Creates a new TemplateResolver instance.
	 *
	 * @param string      $base_path Base path for template files
	 * @param Logger|null $logger    Optional logger instance
	 */
	public function __construct(string $base_path = '', ?Logger $logger = null) {
		$this->base_path = $base_path ?: dirname(__DIR__) . '/views';
		$this->logger    = $logger;
	}

	/**
	 * Resolve template path with basic override support.
	 *
	 * @param string $template_name Template name to resolve
	 * @param array  $overrides     Additional override map for this resolution
	 * @return string Resolved template path
	 * @throws \InvalidArgumentException If template cannot be resolved
	 */
	public function resolve_template(string $template_name, array $overrides = array()): string {
		// Merge instance overrides with provided overrides (provided takes precedence)
		$all_overrides = array_merge($this->overrides, $overrides);

		// Check for override first
		if (isset($all_overrides[$template_name])) {
			$override_path = $all_overrides[$template_name];
			if ($this->validate_template_exists($override_path)) {
				$this->_get_logger()->debug('TemplateResolver: Override template resolved', array(
					'template_name' => $template_name,
					'override_path' => $override_path
				));
				return $override_path;
			}
		}

		// Try default template path
		$default_path = $this->_build_default_path($template_name);
		if ($this->validate_template_exists($default_path)) {
			$this->_get_logger()->debug('TemplateResolver: Default template resolved', array(
				'template_name' => $template_name,
				'default_path'  => $default_path
			));
			return $default_path;
		}

		// Fallback to basic template if available
		$fallback_path = $this->_build_fallback_path($template_name);
		if ($this->validate_template_exists($fallback_path)) {
			$this->_get_logger()->warning('TemplateResolver: Using fallback template', array(
				'template_name' => $template_name,
				'fallback_path' => $fallback_path
			));
			return $fallback_path;
		}

		// Template resolution failed
		$this->_get_logger()->error('TemplateResolver: Template resolution failed', array(
			'template_name' => $template_name,
			'tried_paths'   => array($override_path ?? null, $default_path, $fallback_path)
		));

		throw new \InvalidArgumentException("Template '{$template_name}' could not be resolved");
	}

	/**
	 * Add template override.
	 *
	 * @param string $template_name Template name
	 * @param string $override_path Override template path
	 * @return void
	 */
	public function add_override(string $template_name, string $override_path): void {
		$this->overrides[$template_name] = $override_path;
		$this->_get_logger()->debug('TemplateResolver: Override added', array(
			'template_name' => $template_name,
			'override_path' => $override_path
		));
	}

	/**
	 * Validate that a template file exists.
	 *
	 * @param string $template_path Template file path
	 * @return bool True if template exists and is readable
	 */
	public function validate_template_exists(string $template_path): bool {
		return !empty($template_path) && is_file($template_path) && is_readable($template_path);
	}

	/**
	 * Build default template path.
	 *
	 * @param string $template_name Template name
	 * @return string Default template path
	 */
	private function _build_default_path(string $template_name): string {
		// Convert template name to file path
		// e.g., 'shared.field-wrapper' -> 'shared/field-wrapper.php'
		$path_parts = explode('.', $template_name);
		$file_path  = implode('/', $path_parts) . '.php';

		return $this->base_path . '/' . $file_path;
	}

	/**
	 * Build fallback template path.
	 *
	 * @param string $template_name Template name
	 * @return string Fallback template path
	 */
	private function _build_fallback_path(string $template_name): string {
		// Simple fallback: try shared directory
		$path_parts = explode('.', $template_name);
		$filename   = end($path_parts);

		return $this->base_path . '/shared/' . $filename . '.php';
	}

	/**
	 * Get the logger instance, creating a default one if needed.
	 *
	 * @return Logger
	 */
	private function _get_logger(): Logger {
		if ($this->logger === null) {
			$this->logger = new Logger();
		}
		return $this->logger;
	}
}
