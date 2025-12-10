<?php
/**
 * AdminMenuRegistry: Lightweight admin menu registration without expensive dependencies.
 *
 * This class provides a fluent interface for registering WordPress admin menus
 * without requiring RegisterOptions or ComponentManifest. These expensive
 * dependencies are only created when a page is actually rendered.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.3.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Lightweight admin menu registry.
 *
 * Registers WordPress admin menus without requiring expensive dependencies.
 * AdminSettings is created on-demand when a page is actually rendered.
 *
 * Usage:
 * ```php
 * $config->admin_menu($context)->register(function (AdminMenuRegistry $m) {
 *     $m->settings_page('my-settings')
 *         ->heading('My Settings')
 *         ->on_render(function (AdminSettings $s) {
 *             $s->section('general', 'General')
 *                 ->field('name', 'Name', 'fields.input')
 *                 ->end_field()
 *             ->end_section();
 *         });
 * });
 * ```
 */
class AdminMenuRegistry {
	use WPWrappersTrait;

	/**
	 * Page slug prefix for plugin admin pages.
	 */
	public const PAGE_SLUG_PREFIX = 'kplr-';

	/**
	 * Stored parameters for deferred RegisterOptions creation.
	 */
	private string $option_key;
	private ?StorageContext $storage_context;
	private bool $autoload;
	private ?ConfigInterface $config;
	private Logger $logger;

	/**
	 * Collected menu groups with their pages.
	 *
	 * @var array<string, array{meta: array, pages: array<string, array>}>
	 */
	private array $menu_groups = array();

	/**
	 * Render callbacks for each page.
	 *
	 * @var array<string, callable>
	 */
	private array $render_callbacks = array();

	/**
	 * The AdminSettings instance, created on-demand.
	 */
	private ?AdminSettings $settings = null;

	/**
	 * The RegisterOptions instance, created on-demand.
	 */
	private ?RegisterOptions $options = null;

	/**
	 * Whether menus have been registered with WordPress.
	 */
	private bool $menus_registered = false;

	/**
	 * Constructor.
	 *
	 * @param string $option_key The main option key for RegisterOptions.
	 * @param StorageContext|null $storage_context Storage context for scope-aware persistence.
	 * @param bool $autoload Whether to autoload options.
	 * @param Logger $logger Logger instance.
	 * @param ConfigInterface|null $config Optional Config for namespace resolution.
	 */
	public function __construct(
		string $option_key,
		?StorageContext $storage_context,
		bool $autoload,
		Logger $logger,
		?ConfigInterface $config = null
	) {
		$this->option_key      = $option_key;
		$this->storage_context = $storage_context;
		$this->autoload        = $autoload;
		$this->logger          = $logger;
		$this->config          = $config;
	}

	/**
	 * Register menus via callback.
	 *
	 * The callback receives this registry and should define menu structure
	 * using settings_page() or menu_group()->page() patterns.
	 *
	 * @param callable $callback Receives AdminMenuRegistry instance.
	 * @return void
	 */
	public function register(callable $callback): void {
		// Run callback to collect menu structure (lightweight, no deps)
		$callback($this);

		// Register WordPress menus on admin_menu hook
		$this->_do_add_action('admin_menu', array( $this, '_register_wordpress_menus' ), 10);

		// Register admin_init for Settings API registration
		$this->_do_add_action('admin_init', array( $this, '_register_settings_api' ), 10);

		$this->logger->debug('admin_menu_registry.hooks_registered', array(
			'option_key' => $this->option_key,
		));
	}

	/**
	 * Create a settings page under the WordPress Settings menu.
	 *
	 * Convenience method that creates a menu group parented to options-general.php.
	 *
	 * @param string $page_slug The page slug (will be prefixed with kplr-).
	 * @return MenuRegistryPageBuilder
	 */
	public function settings_page(string $page_slug): MenuRegistryPageBuilder {
		$group_slug = $page_slug . '_settings_group';
		$group      = new MenuRegistryGroupBuilder($this, $group_slug, $this->logger);
		$group->parent('options-general.php');
		return $group->page($page_slug);
	}

	/**
	 * Create a custom top-level menu group.
	 *
	 * @param string $group_slug The menu group slug.
	 * @return MenuRegistryGroupBuilder
	 */
	public function menu_group(string $group_slug): MenuRegistryGroupBuilder {
		return new MenuRegistryGroupBuilder($this, $group_slug, $this->logger);
	}

	/**
	 * Register a menu group with its metadata and pages.
	 *
	 * @internal Called by MenuRegistryGroupBuilder.
	 *
	 * @param string $group_slug The group slug.
	 * @param array $meta The group metadata.
	 * @param array<string, array> $pages The pages in this group.
	 * @return void
	 */
	public function _commit_group(string $group_slug, array $meta, array $pages): void {
		$this->menu_groups[$group_slug] = array(
			'meta'  => $meta,
			'pages' => $pages,
		);
	}

	/**
	 * Store a render callback for a page.
	 *
	 * @internal Called by MenuRegistryPageBuilder.
	 *
	 * @param string $page_slug The prefixed page slug.
	 * @param callable $callback The render callback.
	 * @return void
	 */
	public function _store_render_callback(string $page_slug, callable $callback): void {
		$this->render_callbacks[$page_slug] = $callback;
	}

	/**
	 * Prefix a page slug with the standard prefix.
	 *
	 * @param string $slug The slug to prefix.
	 * @return string The prefixed slug.
	 */
	public static function prefix_slug(string $slug): string {
		if (str_starts_with($slug, self::PAGE_SLUG_PREFIX)) {
			return $slug;
		}
		return self::PAGE_SLUG_PREFIX . $slug;
	}

	/**
	 * Get all collected page slugs (prefixed).
	 *
	 * @return array<string>
	 */
	public function get_page_slugs(): array {
		$slugs = array();
		foreach ($this->menu_groups as $group) {
			foreach ($group['pages'] as $page_slug => $page_meta) {
				$slugs[] = $page_slug;
			}
		}
		return $slugs;
	}

	/**
	 * Register all collected menus with WordPress.
	 *
	 * @internal Called by WordPress admin_menu hook.
	 * @return void
	 */
	public function _register_wordpress_menus(): void {
		if ($this->menus_registered) {
			return;
		}
		$this->menus_registered = true;

		foreach ($this->menu_groups as $group_slug => $group) {
			$meta  = $group['meta'];
			$pages = $group['pages'];

			if (empty($pages)) {
				continue;
			}

			$prefixed_group_slug = self::prefix_slug($group_slug);
			$first_page_slug     = array_key_first($pages);
			$submenu_parent      = $meta['parent'] ?? null;
			$skip_first          = false;

			if ($submenu_parent === null) {
				// Top-level menu
				$this->_do_add_menu_page(
					$meta['heading']    ?? '',
					$meta['menu_title'] ?? '',
					$meta['capability'] ?? 'manage_options',
					$prefixed_group_slug,
					function () use ($first_page_slug) {
						$this->_render_page($first_page_slug);
					},
					$meta['icon']     ?? '',
					$meta['position'] ?? null
				);
				$submenu_parent = $prefixed_group_slug;
				$skip_first     = true;
			} elseif ($submenu_parent === 'options-general.php') {
				// Settings submenu
				$this->_do_add_options_page(
					$meta['heading']    ?? '',
					$meta['menu_title'] ?? '',
					$meta['capability'] ?? 'manage_options',
					$first_page_slug,
					function () use ($first_page_slug) {
						$this->_render_page($first_page_slug);
					},
					$meta['position'] ?? null
				);
				$skip_first = true;
			} else {
				// Submenu under existing parent
				$this->_do_add_submenu_page(
					$submenu_parent,
					$meta['heading']    ?? '',
					$meta['menu_title'] ?? '',
					$meta['capability'] ?? 'manage_options',
					$first_page_slug,
					function () use ($first_page_slug) {
						$this->_render_page($first_page_slug);
					}
				);
				$skip_first = true;
			}

			// Register subpages
			foreach ($pages as $page_slug => $page_meta) {
				if ($skip_first && $page_slug === $first_page_slug) {
					continue;
				}
				$this->_do_add_submenu_page(
					$submenu_parent,
					$page_meta['heading']    ?? $page_slug,
					$page_meta['menu_title'] ?? $page_slug,
					$page_meta['capability'] ?? $meta['capability'] ?? 'manage_options',
					$page_slug,
					function () use ($page_slug) {
						$this->_render_page($page_slug);
					}
				);
			}
		}

		$this->logger->debug('admin_menu_registry.menus_registered', array(
			'groups' => array_keys($this->menu_groups),
			'pages'  => $this->get_page_slugs(),
		));
	}

	/**
	 * Register Settings API for the current page.
	 *
	 * @internal Called by WordPress admin_init hook.
	 * @return void
	 */
	public function _register_settings_api(): void {
		// Only register if we're on one of our pages
		$current_page = $_GET['page'] ?? '';
		if ($current_page === '' || !str_starts_with($current_page, self::PAGE_SLUG_PREFIX)) {
			// Also check for POST saves to options.php
			$pagenow = $GLOBALS['pagenow'] ?? '';
			if (!($pagenow === 'options.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
				return;
			}
		}

		try {
			// Ensure AdminSettings is created and booted for Settings API registration
			$this->_ensure_settings();

			if ($this->settings !== null) {
				// AdminSettings::boot() handles register_setting() and add_settings_section/field
				// But we need to run the render callback first to define sections/fields
				$callback = $this->render_callbacks[$current_page] ?? null;
				if ($callback !== null) {
					// Find the page metadata
					$page_meta = $this->_find_page_meta($current_page);

					// Create a page builder context for the callback
					$page_builder = $this->settings->settings_page(
						$current_page,
						null,
						array(
							'heading'    => $page_meta['heading']    ?? '',
							'menu_title' => $page_meta['menu_title'] ?? '',
							'capability' => $page_meta['capability'] ?? 'manage_options',
							'parent'     => null,
						)
					);

					$callback($page_builder);
					$this->settings->boot();
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error('admin_menu_registry.settings_api_error', array(
				'page'    => $current_page,
				'message' => $e->getMessage(),
			));
		}
	}

	/**
	 * Render a page. Creates AdminSettings on-demand.
	 *
	 * @param string $page_slug The page slug to render.
	 * @return void
	 */
	public function _render_page(string $page_slug): void {
		$callback = $this->render_callbacks[$page_slug] ?? null;
		if ($callback === null) {
			$this->logger->warning('admin_menu_registry.no_render_callback', array(
				'page_slug' => $page_slug,
			));
			return;
		}

		try {
			// Create AdminSettings NOW (first time only)
			$this->_ensure_settings();

			if ($this->settings === null) {
				$this->logger->error('admin_menu_registry.settings_creation_failed', array(
					'page_slug' => $page_slug,
				));
				return;
			}

			// Find the page metadata to get group context
			$page_meta = $this->_find_page_meta($page_slug);

			// Create a page builder context for the callback
			// The callback receives a page builder so it can use section() directly
			$page_builder = $this->settings->settings_page(
				$page_slug,
				null,
				array(
					'heading'    => $page_meta['heading']    ?? '',
					'menu_title' => $page_meta['menu_title'] ?? '',
					'capability' => $page_meta['capability'] ?? 'manage_options',
					'parent'     => null, // Menu already registered by AdminMenuRegistry
				)
			);

			// Run the on_render callback with the page builder
			$callback($page_builder);

			// Boot and render
			$this->settings->boot();
			$this->settings->__render($page_slug);
		} catch (\Throwable $e) {
			$this->logger->error('admin_menu_registry.render_error', array(
				'page_slug' => $page_slug,
				'message'   => $e->getMessage(),
				'file'      => $e->getFile(),
				'line'      => $e->getLine(),
			));

			// Show error in admin
			echo '<div class="notice notice-error"><p><strong>Settings Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
		}
	}

	/**
	 * Find page metadata by slug.
	 *
	 * @param string $page_slug The page slug.
	 * @return array The page metadata.
	 */
	private function _find_page_meta(string $page_slug): array {
		foreach ($this->menu_groups as $group) {
			if (isset($group['pages'][$page_slug])) {
				return $group['pages'][$page_slug];
			}
		}
		return array();
	}

	/**
	 * Get or create AdminSettings instance.
	 *
	 * @return AdminSettings|null
	 */
	private function _ensure_settings(): ?AdminSettings {
		if ($this->settings !== null) {
			return $this->settings;
		}

		$this->logger->debug('admin_menu_registry.creating_settings', array(
			'option_key' => $this->option_key,
		));

		try {
			// NOW create expensive dependencies
			$this->options = new RegisterOptions(
				$this->option_key,
				$this->storage_context,
				$this->autoload,
				$this->logger
			);

			$component_loader = new ComponentLoader(
				dirname(__DIR__) . '/Forms/Components',
				$this->logger
			);
			$manifest = new ComponentManifest($component_loader, $this->logger);

			$this->settings = new AdminSettings(
				$this->options,
				$manifest,
				$this->config,
				$this->logger
			);

			return $this->settings;
		} catch (\Throwable $e) {
			$this->logger->error('admin_menu_registry.settings_creation_error', array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));
			return null;
		}
	}

	/**
	 * Get the RegisterOptions instance (created on-demand).
	 *
	 * @return RegisterOptions|null
	 */
	public function get_options(): ?RegisterOptions {
		$this->_ensure_settings();
		return $this->options;
	}

	/**
	 * Get the AdminSettings instance (created on-demand).
	 *
	 * @return AdminSettings|null
	 */
	public function get_settings(): ?AdminSettings {
		return $this->_ensure_settings();
	}

	/**
	 * Get the logger instance.
	 *
	 * @return Logger
	 */
	public function get_logger(): Logger {
		return $this->logger;
	}

	/**
	 * Get the config instance.
	 *
	 * @return ConfigInterface|null
	 */
	public function get_config(): ?ConfigInterface {
		return $this->config;
	}
}
