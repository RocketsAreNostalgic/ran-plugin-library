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
use Ran\PluginLib\Forms\ErrorNoticeRenderer;

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
 *         });
 * });
 * ```
 */
class AdminMenuRegistry implements SettingsRegistryInterface {
	use WPWrappersTrait;

	public const PAGE_SLUG_PREFIX = 'kplr-';

	private string $option_key;
	private ?StorageContext $storage_context;
	private bool $autoload;
	private ?ConfigInterface $config;
	private Logger $logger;

	private string $page_slug_prefix;

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
	 * Tracks which page render callbacks have already run in this request.
	 *
	 * @var array<string,bool>
	 */
	private array $render_callbacks_ran = array();

	/**
	 * Stored error from register() callback, to display on pages.
	 */
	private ?\Throwable $register_error = null;

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
		$this->option_key       = $option_key;
		$this->storage_context  = $storage_context;
		$this->autoload         = $autoload;
		$this->logger           = $logger;
		$this->config           = $config;
		$this->page_slug_prefix = $this->_resolve_page_slug_prefix();
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
		try {
			$callback($this);
		} catch (\Throwable $e) {
			$this->logger->error('admin_menu_registry.register_callback_error', array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			));

			// Store error to display on pages, but continue registering
			// whatever menus were defined before the error occurred
			$this->register_error = $e;
		}

		// Register WordPress menus on admin_menu hook
		// Even if there was an error, register what we have so pages still load
		$this->_do_add_action('admin_menu', array( $this, '_register_wordpress_menus' ), 10);

		// Register admin_init for Settings API registration
		$this->_do_add_action('admin_init', array( $this, '_register_settings_api' ), 10);

		$this->_do_add_action('admin_enqueue_scripts', array( $this, '_enqueue_page_assets' ), 10, 1);

		// If there was an error, show admin notice so developers see it on any page
		if ($this->register_error !== null) {
			$error = $this->register_error;
			$this->_do_add_action('admin_notices', function () use ($error) {
				ErrorNoticeRenderer::render($error, 'AdminMenuRegistry Error');
			});

			// If no menus were defined at all, register fallback page for direct URL access
			if (empty($this->menu_groups)) {
				$this->_register_error_fallback_page($error);
			}
		}

		$this->logger->debug('admin_menu_registry.hooks_registered', array(
			'option_key'  => $this->option_key,
			'has_error'   => $this->register_error !== null,
			'menu_groups' => count($this->menu_groups),
		));
	}

	public function _enqueue_page_assets($hook_suffix = ''): void {
		$current_page = $_GET['page'] ?? '';
		if ($current_page === '' || !isset($this->render_callbacks[$current_page])) {
			return;
		}

		$this->_ensure_settings();
		if ($this->settings === null) {
			return;
		}

		$aliases = $this->settings->collect_used_component_aliases($current_page);
		$this->settings->get_component_manifest()->enqueue_assets_for_aliases($aliases);
	}

	/**
	 * Get the option key used for storage.
	 *
	 * @return string
	 */
	public function get_option_key(): string {
		return $this->option_key;
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
	public function prefix_page_slug(string $slug): string {
		if (str_starts_with($slug, $this->page_slug_prefix)) {
			return $slug;
		}
		return $this->page_slug_prefix . $slug;
	}

	public function get_page_slug_prefix(): string {
		return $this->page_slug_prefix;
	}

	private function _resolve_page_slug_prefix(): string {
		$prefix = '';
		if ($this->config !== null) {
			$cfg = $this->config->get_config();
			$ran = $cfg['RAN'] ?? null;
			if (is_array($ran) && isset($ran['AdminPageSlugPrefix']) && $ran['AdminPageSlugPrefix'] !== '') {
				$prefix = (string) $ran['AdminPageSlugPrefix'];
			}
		}

		$prefix = trim($prefix);
		if ($prefix === '') {
			$prefix = $this->option_key;
		}
		$prefix = trim($prefix);
		if ($prefix === '') {
			$prefix = self::PAGE_SLUG_PREFIX;
		}
		if (!str_ends_with($prefix, '-')) {
			$prefix .= '-';
		}
		return $prefix;
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

			$prefixed_group_slug = $this->prefix_page_slug($group_slug);
			$first_page_slug     = array_key_first($pages);
			$submenu_parent      = $meta['parent'] ?? null;
			$skip_first          = false;

			if ($submenu_parent === null) {
				// Top-level menu
				$this->logger->debug('admin_menu_registry.adding_top_level_menu', array(
					'group_slug' => $group_slug,
					'menu_slug'  => $prefixed_group_slug,
					'heading'    => $meta['heading']    ?? '',
					'menu_title' => $meta['menu_title'] ?? '',
					'icon'       => $meta['icon']       ?? '',
					'position'   => $meta['position']   ?? null,
					'first_page' => $first_page_slug,
				));
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
				// Settings submenu - use first page's metadata since settings_page() sets it there
				$first_page_meta = $pages[$first_page_slug] ?? array();
				$this->logger->debug('admin_menu_registry.adding_settings_page', array(
					'group_slug' => $group_slug,
					'page_slug'  => $first_page_slug,
					'heading'    => $first_page_meta['heading']    ?? $meta['heading'] ?? '',
					'menu_title' => $first_page_meta['menu_title'] ?? $meta['menu_title'] ?? '',
				));
				$this->_do_add_options_page(
					$first_page_meta['heading']    ?? $meta['heading'] ?? '',
					$first_page_meta['menu_title'] ?? $meta['menu_title'] ?? '',
					$first_page_meta['capability'] ?? $meta['capability'] ?? 'manage_options',
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
		$current_page = $_GET['page']       ?? '';
		$pagenow      = $GLOBALS['pagenow'] ?? '';
		$is_post_save = $pagenow === 'options.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

		// On POST to options.php, WordPress sends option_page in POST data
		// This is the group name (e.g., 'kepler_stress_test_settings_group')
		if ($is_post_save && $current_page === '') {
			$option_page = $_POST['option_page'] ?? '';
			// Check if this is our option group
			$expected_group = $this->option_key . '_group';
			if ($option_page === $expected_group) {
				// This is a save for our settings - run ALL render callbacks
				// to ensure schema is defined for all fields
				$this->_ensure_settings();
				if ($this->settings !== null) {
					$this->_run_all_render_callbacks();
					$this->settings->boot();
				}
				return;
			}
		}

		if ($current_page === '' || !str_starts_with($current_page, $this->page_slug_prefix)) {
			return;
		}

		try {
			// Ensure AdminSettings is created and booted for Settings API registration
			$this->_ensure_settings();

			if ($this->settings !== null) {
				// Restore validation messages from transient NOW.
				// AdminSettings registers this on admin_init, but since we're already
				// inside admin_init when _ensure_settings() creates the instance,
				// the hook never fires. Call it directly here.
				$this->settings->restore_form_messages();

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
					$this->render_callbacks_ran[$current_page] = true;
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

			if (!($this->render_callbacks_ran[$page_slug] ?? false)) {
				$page_args = array(
					'heading'    => $page_meta['heading']    ?? '',
					'menu_title' => $page_meta['menu_title'] ?? '',
					'capability' => $page_meta['capability'] ?? 'manage_options',
					'parent'     => null,
				);
				if (isset($page_meta['style'])) {
					$page_args['style'] = $page_meta['style'];
				}
				if (isset($page_meta['before'])) {
					$page_args['before'] = $page_meta['before'];
				}
				if (isset($page_meta['after'])) {
					$page_args['after'] = $page_meta['after'];
				}
				$page_builder = $this->settings->settings_page(
					$page_slug,
					null,
					$page_args
				);
				$callback($page_builder);
				$this->render_callbacks_ran[$page_slug] = true;
			}

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
			ErrorNoticeRenderer::renderWithContext($e, 'AdminSettings Error', 'page', $page_slug);
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
	 * Run all render callbacks to define schema for all pages.
	 *
	 * Called during POST save (options.php) when we don't know which page the
	 * form submission originated from. WordPress's Settings API doesn't provide
	 * page context during sanitization, so ALL field schemas must be registered
	 * to ensure any submitted field can be validated.
	 *
	 * Performance implications:
	 * - On POST: All pages' render callbacks execute, registering all fields
	 *   (e.g., 132 fields across 12 pages in the stress test). The resulting
	 *   schema bundle is cached per-request via FormsCore::__schema_bundle_cache.
	 * - On GET: Only the current page's callback runs, registering only that
	 *   page's fields (e.g., 9 fields for the checkboxes page).
	 *
	 * This means components used on other pages (like _raw_html, _hr on the
	 * elements page) will be processed during POST even when saving a different
	 * page. Debug logs for these are gated behind RAN_VERBOSE_DEBUG.
	 *
	 * @see FormsCore::_resolve_schema_bundle() Schema bundle caching
	 * @see FormsCore::_assemble_initial_bucketed_schema() Field iteration
	 *
	 * @return void
	 */
	private function _run_all_render_callbacks(): void {
		if ($this->settings === null) {
			return;
		}

		foreach ($this->render_callbacks as $page_slug => $callback) {
			try {
				$page_meta = $this->_find_page_meta($page_slug);

				// Create a page builder context for the callback
				$page_builder = $this->settings->settings_page(
					$page_slug,
					null,
					array(
						'heading'    => $page_meta['heading']    ?? '',
						'menu_title' => $page_meta['menu_title'] ?? '',
						'capability' => $page_meta['capability'] ?? 'manage_options',
						'parent'     => null,
					)
				);

				// Run the callback to define fields/schema
				$callback($page_builder);
			} catch (\Throwable $e) {
				$this->logger->error('admin_menu_registry.render_callback_error', array(
					'page_slug' => $page_slug,
					'message'   => $e->getMessage(),
				));
			}
		}
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

			if ($this->config !== null) {
				$registrations = array();
				foreach ($this->menu_groups as $group) {
					$pages = $group['pages'] ?? array();
					if (!is_array($pages)) {
						continue;
					}
					foreach ($pages as $page_meta) {
						if (!is_array($page_meta)) {
							continue;
						}
						$register_components = $page_meta['register_components'] ?? array();
						if (!is_array($register_components)) {
							continue;
						}
						foreach ($register_components as $options) {
							if (!is_array($options)) {
								continue;
							}
							$registrations[] = $options;
						}
					}
				}
				$component_loader->register_components_batch($registrations, $this->config);
			}
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
	 * Register a fallback error page when the register callback fails.
	 *
	 * This ensures users see the error on the page they were trying to access,
	 * rather than getting "Sorry, you are not allowed to access this page."
	 *
	 * @param \Throwable $e The caught exception.
	 * @return void
	 */
	protected function _register_error_fallback_page(\Throwable $e): void {
		$is_dev = ErrorNoticeRenderer::isDevMode();

		// Try to detect page slug from current request
		$page_slug = isset($_GET['page']) ? \sanitize_text_field($_GET['page']) : null;

		if ($page_slug === null || !str_starts_with($page_slug, $this->page_slug_prefix)) {
			return; // Not one of our pages
		}

		$render_error = function () use ($is_dev) {
			// Error is already shown via admin_notices, just show a simple page wrapper
			ErrorNoticeRenderer::renderFallbackPage('Settings Error', 'Settings Error', $is_dev);
		};

		// Register the fallback page on admin_menu
		$this->_do_add_action('admin_menu', function () use ($page_slug, $render_error) {
			$this->_do_add_options_page(
				'Settings Error',
				'Settings Error',
				'manage_options',
				$page_slug,
				$render_error
			);
		}, 10);
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
