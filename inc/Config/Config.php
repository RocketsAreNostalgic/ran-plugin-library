<?php
/**
 * A convenience Config class.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Config;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\ScopeEntity;
use Ran\PluginLib\Options\Scope\ScopeResolver;

/**
 * Final Config class which holds key information about the plugin.
 */
class Config extends ConfigAbstract implements ConfigInterface {
	/**
	 * Initialize configuration from a plugin root file.
	 *
	 * @param string $pluginFile Absolute path to the plugin root file (typically __FILE__).
	 * @return self
	 */
	public static function fromPluginFile(string $pluginFile): self {
		$instance = new self();
		$instance->_hydrateFromPlugin($pluginFile);
		return $instance;
	}

	/**
	 * Initialize configuration from a plugin root file with a custom logger.
	 * Ensures the logger is used during hydration.
	 */
	public static function fromPluginFileWithLogger(string $pluginFile, \Ran\PluginLib\Util\Logger $logger): self {
		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromPlugin($pluginFile);
		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory.
	 *
	 * @param string|null $stylesheetDir Optional absolute path to the theme stylesheet directory.
	 * @return self
	 */
	public static function fromThemeDir(?string $stylesheetDir = null): self {
		$instance = new self();
		$instance->_hydrateFromTheme($stylesheetDir ?? '');
		return $instance;
	}

	/**
	 * Initialize configuration for a theme directory with a custom logger.
	 * Ensures the logger is used during hydration.
	 */
	public static function fromThemeDirWithLogger(?string $stylesheetDir, \Ran\PluginLib\Util\Logger $logger): self {
		$instance = new self();
		$instance->set_logger($logger);
		$instance->_hydrateFromTheme($stylesheetDir ?? '');
		return $instance;
	}

	/**
     * Accessor: get a pre-wired RegisterOptions instance for this app's options key.
     *
     * Semantics (no-write accessor):
     * - Returns a RegisterOptions instance bound to `get_options_key()` and this Config's logger.
     * - Recognized args (all optional):
     *   - `autoload` (bool, default: true) — default autoload policy to apply on future writes.
     *   - `scope` ('site'|'network'|'blog'|'user' or OptionScope), default: 'site'.
     *   - `entity` (ScopeEntity|null) — required for 'blog' and 'user' scopes; ignored for 'site' and 'network'.
     * - This method itself performs no DB writes, seeding, or flushing.
     * - Unknown args are ignored and a warning is emitted via this Config's logger.
     *
     * Persistence:
     * - Use the returned RegisterOptions instance to perform explicit write operations when desired,
     *   e.g. `$opts->add_options([...]); $opts->flush();` or `$opts->register_schema($schema, seed_defaults: true, flush: true);`.
     *
     * @param array $args Recognized args only; unknown keys are ignored with a warning.
     * @return \Ran\PluginLib\Options\RegisterOptions
     */
	public function options(array $args = array()): \Ran\PluginLib\Options\RegisterOptions {
		// Normalize args with defaults (recognized keys only)
		$defaults = array(
		    'autoload' => true,
		    'scope'    => 'site',
		    'entity'   => null,
		);
		$args = is_array($args) ? array_merge($defaults, $args) : $defaults;

		$autoload = (bool) ($args['autoload'] ?? true);
		$scopeArg = $args['scope']  ?? 'site'; // string|OptionScope
		$entity   = $args['entity'] ?? null;   // expected ScopeEntity or null

		// Normalize final scope and storage args via shared resolver
		$resolved     = ScopeResolver::resolve($scopeArg, ($entity instanceof ScopeEntity) ? $entity : null);
		$scope        = $resolved['scope'];
		$storage_args = $resolved['storage_args'];

		// Warn on unknown/operational args (no behavior change; no writes)
		$recognized = array('autoload', 'scope', 'entity');
		$unknown    = array_diff(array_keys($args), $recognized);
		if (!empty($unknown)) {
			$logger = $this->get_logger();
			if ($logger && method_exists($logger, 'warning')) {
				$logger->warning('Config::options(): Ignored args: ' . implode(',', $unknown));
			}
		}

		// Build instance via slimmed from_config + fluent chaining
		$opts = RegisterOptions::_from_config($this, $autoload, $scope, $storage_args);

		// Chain fluents for configuration (80/20 pattern)
		$opts = $opts->with_logger($this->get_logger());

		return $opts;
	}
}
