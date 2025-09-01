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
     *   - `initial` (array<string,mixed>, default: []) — values merged in-memory on the instance.
     *   - `schema` (array<string,mixed>, default: []) — schema merged in-memory on the instance.
     *   - `scope` ('site'|'network'|'blog'|'user' or OptionScope), default: 'site'.
     *   - `blog_id` (int|null) — used when scope='blog'.
     *   - `user_id` (int) — required when scope='user'.
     *   - `user_global` (bool, default: false) — forwarded for user scope.
     *   - `policy` (\Ran\PluginLib\Options\WritePolicyInterface|null) — custom immutable write policy.
     * - This method itself performs no DB writes, seeding, or flushing.
     * - Unknown args are ignored and a warning is emitted via this Config's logger.
     *
     * Persistence:
     * - Use the returned RegisterOptions instance to perform explicit write operations when desired,
     *   e.g. `$opts->add_options([...]); $opts->flush();` or `$opts->register_schema($schema, seedDefaults: true, flush: true);`.
     *
     * @param array $args Recognized args only; unknown keys are ignored with a warning.
     * @return \Ran\PluginLib\Options\RegisterOptions
     */
	public function options(array $args = array()): \Ran\PluginLib\Options\RegisterOptions {
		// Normalize args with defaults (recognized keys only)
		$defaults = array(
		    'autoload'     => true,
		    'initial'      => array(),
		    'schema'       => array(),
		    'scope'        => null,
		    'blog_id'      => null,
		    'user_id'      => null,
		    'user_global'  => false,
		    'user_storage' => null,
		    'policy'       => null,
		);
		$args = is_array($args) ? array_merge($defaults, $args) : $defaults;

		$autoload = (bool) ($args['autoload'] ?? true);
		$initial  = is_array($args['initial'] ?? null) ? $args['initial'] : array();
		$schema   = is_array($args['schema'] ?? null) ? $args['schema'] : array();
		$scope    = $args['scope']  ?? null; // string|OptionScope|null
		$policy   = $args['policy'] ?? null; // WritePolicyInterface|null
		if (null !== $policy && !($policy instanceof \Ran\PluginLib\Options\WritePolicyInterface)) {
			// Unknown type; ignore and warn
			$policy = null;
			$logger = $this->get_logger();
			if ($logger && method_exists($logger, 'warning')) {
				$logger->warning('Config::options(): Ignored policy (must implement WritePolicyInterface).');
			}
		}
		$storageArgs = array();
		// Only forward args relevant to the selected scope to avoid unintended effects.
		$isUserScope = ($scope === 'user') || ($scope instanceof \Ran\PluginLib\Options\OptionScope && $scope === \Ran\PluginLib\Options\OptionScope::User);
		$isBlogScope = ($scope === 'blog') || ($scope instanceof \Ran\PluginLib\Options\OptionScope && $scope === \Ran\PluginLib\Options\OptionScope::Blog);
		if ($isBlogScope && array_key_exists('blog_id', $args) && null !== $args['blog_id']) {
			$storageArgs['blog_id'] = (int) $args['blog_id'];
		}
		if ($isUserScope && array_key_exists('user_id', $args) && null !== $args['user_id']) {
			$storageArgs['user_id'] = (int) $args['user_id'];
		}
		if ($isUserScope && array_key_exists('user_global', $args)) {
			$storageArgs['user_global'] = (bool) $args['user_global'];
		}
		if ($isUserScope && array_key_exists('user_storage', $args) && is_string($args['user_storage'])) {
			$storageArgs['user_storage'] = $args['user_storage'];
		} elseif ($isUserScope && null === $args['user_storage']) {
			// Default to meta for user scope when not provided
			$storageArgs['user_storage'] = 'meta';
		}

		// Warn on unknown/operational args (no behavior change; no writes)
		$recognized = array('autoload', 'initial', 'schema', 'scope', 'blog_id', 'user_id', 'user_global', 'user_storage', 'policy');
		$unknown    = array_diff(array_keys($args), $recognized);
		if (!empty($unknown)) {
			$logger = $this->get_logger();
			if ($logger && method_exists($logger, 'warning')) {
				$logger->warning('Config::options(): Ignored args: ' . implode(',', $unknown));
			}
		}

		// Build instance via factory (no writes performed here)
		$opts = RegisterOptions::from_config(
			$this,
			$initial,
			$autoload,
			$this->get_logger(),
			$schema,
			$scope,
			$storageArgs,
			$policy
		);

		return $opts;
	}
}
