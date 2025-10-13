<?php
/**
 * Settings: Scope-aware facade that wraps the appropriate settings implementation.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Settings\SettingsInterface;
use Ran\PluginLib\Settings\UserSettingsInterface;
use Ran\PluginLib\Settings\AdminSettingsInterface;

/**
 * Scope-aware facade that wraps the appropriate settings implementation.
 */
final class Settings implements SettingsInterface {
	/**
	 * @var SettingsInterface
	 */
	private SettingsInterface $inner;

	public function __construct(RegisterOptions $options, ?Logger $logger = null, ?ComponentManifest $components = null) {
		$context = $options->get_storage_context();
		$scope   = $context->scope instanceof OptionScope ? $context->scope : null;
		/** @var AdminSettingsInterface|UserSettingsInterface $settings */
		$settings       = null;
		$registryLogger = $logger instanceof Logger ? $logger : $options->get_logger();
		$registry       = $components instanceof ComponentManifest
			? $components
			: new ComponentManifest(new ComponentLoader(dirname(__DIR__) . '/Forms/Components'), $registryLogger);
		$service = new FormService($registry);

		try {
			$settings = $scope === OptionScope::User
				? new UserSettings($options, $logger, $registry, $service)
				: new AdminSettings($options, $logger, $registry, $service); // Site, Network, Blog
		} catch (\Exception $e) {
			throw new \LogicException('Invalid options object, failed to create settings instance.', 0, $e);
		}
		$this->inner = $settings;
	}

	public function resolve_options(?array $context = null): RegisterOptions {
		return $this->inner->resolve_options($context);
	}

	public function boot(): void {
		$this->inner->boot();
	}

	/**
	 * Expose the underlying concrete settings instance when direct access is required.
	 */
	public function inner(): AdminSettingsInterface|UserSettingsInterface {
		if ($this->inner instanceof AdminSettingsInterface || $this->inner instanceof UserSettingsInterface) {
			return $this->inner;
		}

		throw new \LogicException('Settings inner implementation must be AdminSettingsInterface or UserSettingsInterface.');
	}

	public function __call(string $name, array $arguments): mixed {
		if (!\method_exists($this->inner, $name)) {
			throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', $this->inner::class, $name));
		}

		return $this->inner->{$name}(...$arguments);
	}
}
