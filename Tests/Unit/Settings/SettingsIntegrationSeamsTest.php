<?php
/**
 * Settings Integration Seams Test
 *
 * Tests cross-boundary integration between:
 * - Config → RegisterOptions → Settings (AdminSettings/UserSettings)
 * - ComponentManifest → Settings → Render
 * - Schema Bundle Resolution across layers
 * - External Component Discovery → Validation → Sanitization
 *
 * These tests verify that the pipeline works end-to-end, not just individual layers.
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Util\Logger;

/**
 * Integration tests that cross layer boundaries.
 *
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Settings\UserSettings
 * @covers \Ran\PluginLib\Options\RegisterOptions
 * @covers \Ran\PluginLib\Forms\Component\ComponentManifest
 * @group integration
 * @group settings
 */
class SettingsIntegrationSeamsTest extends PluginLibTestCase {
	protected Logger $logger;

	/** @var array<string, mixed> Simulated site options storage */
	protected array $siteOptions = array();

	/** @var array<string, array<string, mixed>> Simulated user meta storage */
	protected array $userMetaValues = array();

	public function setUp(): void {
		parent::setUp();
		$this->logger         = new Logger(array());
		$this->siteOptions    = array();
		$this->userMetaValues = array();
		$this->setupWordPressMocks();
	}

	protected function setupWordPressMocks(): void {
		// Site options
		\WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = false) {
			return $this->siteOptions[$name] ?? $default;
		});
		\WP_Mock::userFunction('update_option')->andReturnUsing(function ($name, $value) {
			$this->siteOptions[$name] = $value;
			return true;
		});
		\WP_Mock::userFunction('add_option')->andReturnUsing(function ($name, $value) {
			if (!isset($this->siteOptions[$name])) {
				$this->siteOptions[$name] = $value;
				return true;
			}
			return false;
		});

		// User meta
		\WP_Mock::userFunction('get_user_meta')->andReturnUsing(function ($userId, $key, $single) {
			$value = $this->userMetaValues[$key][$userId] ?? ($single ? '' : array());
			return $single ? $value : array($value);
		});
		\WP_Mock::userFunction('update_user_meta')->andReturnUsing(function ($userId, $key, $value) {
			if (!isset($this->userMetaValues[$key])) {
				$this->userMetaValues[$key] = array();
			}
			$this->userMetaValues[$key][$userId] = $value;
			return true;
		});
		\WP_Mock::userFunction('add_user_meta')->andReturnUsing(function ($userId, $key, $value) {
			if (!isset($this->userMetaValues[$key][$userId])) {
				if (!isset($this->userMetaValues[$key])) {
					$this->userMetaValues[$key] = array();
				}
				$this->userMetaValues[$key][$userId] = $value;
				return true;
			}
			return false;
		});

		// Common WP functions
		\WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($k) => strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $k)));
		\WP_Mock::userFunction('esc_html')->andReturnUsing(fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
		\WP_Mock::userFunction('esc_attr')->andReturnUsing(fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'));
		\WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);
		\WP_Mock::userFunction('__')->andReturnArg(0);
		\WP_Mock::userFunction('esc_html__')->andReturnArg(0);
		\WP_Mock::userFunction('wp_json_encode')->andReturnUsing(fn($d) => json_encode($d));
		\WP_Mock::userFunction('is_multisite')->andReturn(false);
		\WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		\WP_Mock::userFunction('current_user_can')->andReturn(true);
		\WP_Mock::userFunction('wp_create_nonce')->andReturn('test_nonce_123');
		\WP_Mock::userFunction('settings_fields')->andReturn('');
		\WP_Mock::userFunction('do_settings_sections')->andReturn('');
		\WP_Mock::userFunction('submit_button')->andReturn('<button type="submit">Save</button>');

		// Transient mocks (disable caching for tests)
		\WP_Mock::userFunction('get_transient')->andReturn(false);
		\WP_Mock::userFunction('set_transient')->andReturn(true);
		\WP_Mock::userFunction('delete_transient')->andReturn(true);
	}

	// =========================================================================
	// SEAM 1: Config → RegisterOptions → AdminSettings
	// =========================================================================

	/**
	 * @test
	 * Verify Config values flow through RegisterOptions into AdminSettings.
	 */
	public function test_seam_config_to_registeroptions_to_adminsettings(): void {
		// Arrange: Create Config mock with specific values
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH'      => __DIR__,
			'SLUG'      => 'test-plugin',
			'NAMESPACE' => 'TestPlugin',
		));
		$config->method('get_namespace')->willReturn('TestPlugin');

		// Create RegisterOptions with site scope
		$options = new RegisterOptions('test_admin_opts', StorageContext::forSite(), false, $this->logger);

		// Create component infrastructure
		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		// Act: Create AdminSettings - this crosses Config → Options → Settings boundary
		$settings = new AdminSettings($options, $manifest, $config, $this->logger);

		// Assert: Verify the chain is connected
		self::assertSame('test_admin_opts', $options->get_main_option_name());
		self::assertInstanceOf(AdminSettings::class, $settings);
	}

	/**
	 * @test
	 * Verify AdminSettings render uses ComponentManifest defaults for field rendering.
	 */
	public function test_seam_componentmanifest_defaults_flow_to_adminsettings_render(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$this->siteOptions['manifest_render_test'] = array('test_field' => 'stored_value');
		$options                                   = new RegisterOptions('manifest_render_test', StorageContext::forSite(), false, $this->logger);

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		// Register a component with defaults in the manifest
		$manifest->register('test.input', static function (array $context): ComponentRenderResult {
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			$name  = htmlspecialchars((string) ($context['name'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input type="text" name="' . $name . '" value="' . $value . '" data-seam="manifest-to-render">',
				component_type: 'input'
			);
		});

		$settings = new AdminSettings($options, $manifest, $config, $this->logger);

		$settings->menu_group('test-group')
			->page('test-page')
				->section('s1', 'Test Section')
					->field('test_field', 'Test Field', 'test.input')
				->end_section()
			->end_page()
		->end_menu_group();

		// Act: Render crosses Settings → Manifest → Loader boundary
		$output = $this->captureOutput(fn() => $settings->render('test-page'));

		// Assert: Manifest component was used, value from RegisterOptions flowed through
		self::assertStringContainsString('data-seam="manifest-to-render"', $output);
		self::assertStringContainsString('value="stored_value"', $output);
	}

	/**
	 * @test
	 * Verify AdminSettings _sanitize uses schema from RegisterOptions.
	 */
	public function test_seam_adminsettings_sanitize_uses_registeroptions_schema(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$sanitizerCalled                           = false;
		$this->siteOptions['schema_sanitize_test'] = array();
		$options                                   = new RegisterOptions('schema_sanitize_test', StorageContext::forSite(), false, $this->logger);

		// Register schema with sanitizer at the RegisterOptions level
		$options->register_schema(array(
			'schema_field' => array(
				'default'  => '',
				'sanitize' => array(function ($v) use (&$sanitizerCalled) {
					$sanitizerCalled = true;
					return strtoupper(trim((string) $v));
				}),
				'validate' => array(fn($v, $emit) => true),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult('<input type="text">', component_type: 'input');
		});

		$settings = new AdminSettings($options, $manifest, $config, $this->logger);

		$settings->menu_group('sg')
			->page('sp')
				->section('ss', 'Section')
					->field('schema_field', 'Schema Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(fn() => $settings->render('sp'));

		// Act: _sanitize crosses Settings → RegisterOptions schema boundary
		$result = $settings->_sanitize(array('schema_field' => '  hello  '));

		// Assert: Schema sanitizer from RegisterOptions was invoked
		self::assertTrue($sanitizerCalled, 'RegisterOptions schema sanitizer should be called');
		self::assertSame('HELLO', $result['schema_field'] ?? null);
	}

	// =========================================================================
	// SEAM 2: Config → RegisterOptions → UserSettings
	// =========================================================================

	/**
	 * @test
	 * Verify Config values flow through RegisterOptions into UserSettings.
	 */
	public function test_seam_config_to_registeroptions_to_usersettings(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH'      => __DIR__,
			'SLUG'      => 'test-plugin',
			'NAMESPACE' => 'TestPlugin',
		));
		$config->method('get_namespace')->willReturn('TestPlugin');

		// Create RegisterOptions with user scope
		$options = new RegisterOptions('test_user_opts', StorageContext::forUser(123), false, $this->logger);

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		// Act: Create UserSettings - this crosses Config → Options → Settings boundary
		$settings = new UserSettings($options, $manifest, $config, $this->logger);

		// Assert: Verify the chain is connected
		self::assertSame('test_user_opts', $options->get_main_option_name());
		self::assertInstanceOf(UserSettings::class, $settings);
	}

	/**
	 * @test
	 * Verify UserSettings render uses ComponentManifest defaults for field rendering.
	 */
	public function test_seam_componentmanifest_defaults_flow_to_usersettings_render(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$this->userMetaValues['user_manifest_test'] = array(456 => array('profile_field' => 'user_stored_value'));
		$options                                    = new RegisterOptions('user_manifest_test', StorageContext::forUser(456), false, $this->logger);

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		// Register a component with defaults in the manifest
		$manifest->register('profile.input', static function (array $context): ComponentRenderResult {
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			$name  = htmlspecialchars((string) ($context['name'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input type="text" name="' . $name . '" value="' . $value . '" data-seam="user-manifest-to-render">',
				component_type: 'input'
			);
		});

		$settings = new UserSettings($options, $manifest, $config, $this->logger);

		$settings->collection('profile')
			->section('s1', 'Profile Section')
				->field('profile_field', 'Profile Field', 'profile.input')
			->end_section()
		->end_collection();

		// Act: Render crosses Settings → Manifest → Loader boundary
		$output = $this->captureOutput(fn() => $settings->render('profile'));

		// Assert: Manifest component was used, value from RegisterOptions flowed through
		self::assertStringContainsString('data-seam="user-manifest-to-render"', $output);
		self::assertStringContainsString('value="user_stored_value"', $output);
	}

	/**
	 * @test
	 * Verify UserSettings save_settings uses schema from RegisterOptions.
	 */
	public function test_seam_usersettings_save_uses_registeroptions_schema(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$sanitizerCalled                          = false;
		$this->userMetaValues['user_schema_save'] = array(789 => array());
		$options                                  = new RegisterOptions('user_schema_save', StorageContext::forUser(789), false, $this->logger);

		// Register schema with sanitizer at the RegisterOptions level
		$options->register_schema(array(
			'user_schema_field' => array(
				'default'  => '',
				'sanitize' => array(function ($v) use (&$sanitizerCalled) {
					$sanitizerCalled = true;
					return strtoupper(trim((string) $v));
				}),
				'validate' => array(fn($v, $emit) => true),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult('<input type="text">', component_type: 'input');
		});

		$settings = new UserSettings($options, $manifest, $config, $this->logger);

		$settings->collection('profile')
			->section('ss', 'Section')
				->field('user_schema_field', 'User Schema Field', 'fields.input')
			->end_section()
		->end_collection();

		$this->captureOutput(fn() => $settings->render('profile'));

		// Act: save_settings crosses Settings → RegisterOptions schema boundary
		$settings->save_settings(array('user_schema_field' => '  hello world  '), array('user_id' => 789));

		// Assert: Schema sanitizer from RegisterOptions was invoked
		self::assertTrue($sanitizerCalled, 'RegisterOptions schema sanitizer should be called');
		$stored = $this->userMetaValues['user_schema_save'][789]['user_schema_field'] ?? null;
		self::assertSame('HELLO WORLD', $stored);
	}

	// =========================================================================
	// SEAM 3: External Component Discovery → Manifest → Settings
	// =========================================================================

	/**
	 * @test
	 * Verify external component registration flows through all layers for AdminSettings.
	 */
	public function test_seam_external_component_discovery_adminsettings(): void {
		$externalDir = sys_get_temp_dir() . '/ran-seam-admin-ext-' . uniqid();
		mkdir($externalDir . '/SeamComponent', 0777, true);

		$viewCode = '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
$value = htmlspecialchars((string)($context["value"] ?? ""), ENT_QUOTES);
return new ComponentRenderResult(
    \'<div data-seam="external-component-admin">\' . $value . \'</div>\',
    component_type: "input"
);';
		file_put_contents($externalDir . '/SeamComponent/View.php', $viewCode);

		try {
			$config = $this->createMock(ConfigInterface::class);
			$config->method('get_config')->willReturn(array(
				'PATH'      => $externalDir,
				'SLUG'      => 'seam-test',
				'NAMESPACE' => 'SeamTest',
			));
			$config->method('get_namespace')->willReturn('SeamTest');

			$this->siteOptions['seam_ext_admin'] = array('ext_field' => 'external_value');
			$options                             = new RegisterOptions('seam_ext_admin', StorageContext::forSite(), false, $this->logger);

			$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
			$loader->register('admin.root-wrapper', 'default-page.php');
			$loader->register('section', 'section.php');
			$loader->register('field-wrapper', 'field-wrapper.php');
			$manifest = new ComponentManifest($loader, $this->logger);

			$settings = new AdminSettings($options, $manifest, $config, $this->logger);

			// Act: Register external component - crosses Config → Loader → Manifest boundary
			$settings->register_component('seam-component', array(
				'path'   => 'SeamComponent',
				'prefix' => 'ext',
			));

			$settings->menu_group('ext-group')
				->page('ext-page')
					->section('ext-section', 'External Section')
						->field('ext_field', 'External Field', 'ext.seam-component')
					->end_section()
				->end_page()
			->end_menu_group();

			$output = $this->captureOutput(fn() => $settings->render('ext-page'));

			// Assert: External component was discovered and rendered with stored value
			self::assertStringContainsString('data-seam="external-component-admin"', $output);
			self::assertStringContainsString('external_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify external component registration flows through all layers for UserSettings.
	 */
	public function test_seam_external_component_discovery_usersettings(): void {
		$externalDir = sys_get_temp_dir() . '/ran-seam-user-ext-' . uniqid();
		mkdir($externalDir . '/UserSeamComponent', 0777, true);

		$viewCode = '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
$value = htmlspecialchars((string)($context["value"] ?? ""), ENT_QUOTES);
return new ComponentRenderResult(
    \'<div data-seam="external-component-user">\' . $value . \'</div>\',
    component_type: "input"
);';
		file_put_contents($externalDir . '/UserSeamComponent/View.php', $viewCode);

		try {
			$config = $this->createMock(ConfigInterface::class);
			$config->method('get_config')->willReturn(array(
				'PATH'      => $externalDir,
				'SLUG'      => 'user-seam-test',
				'NAMESPACE' => 'UserSeamTest',
			));
			$config->method('get_namespace')->willReturn('UserSeamTest');

			$this->userMetaValues['seam_ext_user'] = array(999 => array('user_ext_field' => 'user_external_value'));
			$options                               = new RegisterOptions('seam_ext_user', StorageContext::forUser(999), false, $this->logger);

			$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
			$loader->register('user.root-wrapper', 'default-page.php');
			$loader->register('section', 'section.php');
			$loader->register('field-wrapper', 'field-wrapper.php');
			$manifest = new ComponentManifest($loader, $this->logger);

			$settings = new UserSettings($options, $manifest, $config, $this->logger);

			// Act: Register external component - crosses Config → Loader → Manifest boundary
			$settings->register_component('user-seam-component', array(
				'path'   => 'UserSeamComponent',
				'prefix' => 'ext',
			));

			$settings->collection('profile')
				->section('ext-section', 'External Section')
					->field('user_ext_field', 'User External Field', 'ext.user-seam-component')
				->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));

			// Assert: External component was discovered and rendered with stored value
			self::assertStringContainsString('data-seam="external-component-user"', $output);
			self::assertStringContainsString('user_external_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// SEAM 4: Full Round-Trip (Render → Save → Retrieve)
	// =========================================================================

	/**
	 * @test
	 * Verify full round-trip: AdminSettings render → _sanitize → stored value retrieval.
	 */
	public function test_seam_adminsettings_full_roundtrip(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$this->siteOptions['roundtrip_admin'] = array('roundtrip_field' => 'initial_value');
		$options                              = new RegisterOptions('roundtrip_admin', StorageContext::forSite(), false, $this->logger);

		$options->register_schema(array(
			'roundtrip_field' => array(
				'default' => '',
				// Idempotent sanitizer: uppercase and trim
				'sanitize' => array(fn($v) => strtoupper(trim((string) $v))),
				'validate' => array(fn($v, $emit) => true),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input type="text" value="' . $value . '" data-roundtrip="admin">',
				component_type: 'input'
			);
		});

		$settings = new AdminSettings($options, $manifest, $config, $this->logger);

		$settings->menu_group('rt')
			->page('rt-page')
				->section('rt-section', 'Roundtrip Section')
					->field('roundtrip_field', 'Roundtrip Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		// Step 1: Initial render shows stored value (NOT sanitized - sanitizers run on save, not read)
		$output1 = $this->captureOutput(fn() => $settings->render('rt-page'));
		self::assertStringContainsString('value="initial_value"', $output1);

		// Step 2: Sanitize new value
		$sanitized = $settings->_sanitize(array('roundtrip_field' => '  new_value  '));
		self::assertSame('NEW_VALUE', $sanitized['roundtrip_field']);

		// Step 3: Simulate WordPress saving the sanitized value
		$this->siteOptions['roundtrip_admin']['roundtrip_field'] = $sanitized['roundtrip_field'];

		// Step 4: Create fresh instance and verify stored value is retrieved
		$options2  = new RegisterOptions('roundtrip_admin', StorageContext::forSite(), false, $this->logger);
		$settings2 = new AdminSettings($options2, $manifest, $config, $this->logger);

		$settings2->menu_group('rt')
			->page('rt-page')
				->section('rt-section', 'Roundtrip Section')
					->field('roundtrip_field', 'Roundtrip Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$output2 = $this->captureOutput(fn() => $settings2->render('rt-page'));
		self::assertStringContainsString('value="NEW_VALUE"', $output2);
	}

	/**
	 * @test
	 * Verify full round-trip: UserSettings render → save_settings → stored value retrieval.
	 */
	public function test_seam_usersettings_full_roundtrip(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$userId                                 = 555;
		$this->userMetaValues['roundtrip_user'] = array($userId => array('user_roundtrip_field' => 'user_initial_value'));
		$options                                = new RegisterOptions('roundtrip_user', StorageContext::forUser($userId), false, $this->logger);

		$options->register_schema(array(
			'user_roundtrip_field' => array(
				'default' => '',
				// Idempotent sanitizer: lowercase and trim
				'sanitize' => array(fn($v) => strtolower(trim((string) $v))),
				'validate' => array(fn($v, $emit) => true),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input type="text" value="' . $value . '" data-roundtrip="user">',
				component_type: 'input'
			);
		});

		$settings = new UserSettings($options, $manifest, $config, $this->logger);

		$settings->collection('profile')
			->section('rt-section', 'Roundtrip Section')
				->field('user_roundtrip_field', 'User Roundtrip Field', 'fields.input')
			->end_section()
		->end_collection();

		// Step 1: Initial render shows stored value
		$output1 = $this->captureOutput(fn() => $settings->render('profile'));
		self::assertStringContainsString('value="user_initial_value"', $output1); // Already lowercase

		// Step 2: Save new value (save_settings handles sanitization internally)
		$settings->save_settings(array('user_roundtrip_field' => '  new_user_value  '), array('user_id' => $userId));

		// Step 3: Verify stored value was sanitized
		$storedValue = $this->userMetaValues['roundtrip_user'][$userId]['user_roundtrip_field'] ?? null;
		self::assertSame('new_user_value', $storedValue); // Lowercase and trimmed

		// Step 4: Create fresh instance and verify stored value is retrieved
		$options2  = new RegisterOptions('roundtrip_user', StorageContext::forUser($userId), false, $this->logger);
		$settings2 = new UserSettings($options2, $manifest, $config, $this->logger);

		$settings2->collection('profile')
			->section('rt-section', 'Roundtrip Section')
				->field('user_roundtrip_field', 'User Roundtrip Field', 'fields.input')
			->end_section()
		->end_collection();

		$output2 = $this->captureOutput(fn() => $settings2->render('profile'));
		self::assertStringContainsString('value="new_user_value"', $output2);
	}

	// =========================================================================
	// SEAM 5: Validation Failure Propagation
	// =========================================================================

	/**
	 * @test
	 * Verify validation failure in RegisterOptions schema prevents AdminSettings save.
	 */
	public function test_seam_validation_failure_prevents_adminsettings_save(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$this->siteOptions['val_fail_admin'] = array('validated_field' => 'original_value');
		$options                             = new RegisterOptions('val_fail_admin', StorageContext::forSite(), false, $this->logger);

		$validatorCalled = false;
		$options->register_schema(array(
			'validated_field' => array(
				'default'  => '',
				'sanitize' => array(fn($v) => trim((string) $v)),
				'validate' => array(function ($v, $emit) use (&$validatorCalled) {
					$validatorCalled = true;
					if (strlen((string) $v) < 5) {
						$emit('Value must be at least 5 characters');
						return false;
					}
					return true;
				}),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult('<input type="text">', component_type: 'input');
		});

		$settings = new AdminSettings($options, $manifest, $config, $this->logger);

		$settings->menu_group('vf')
			->page('vf-page')
				->section('vf-section', 'Validation Section')
					->field('validated_field', 'Validated Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(fn() => $settings->render('vf-page'));

		// Act: Try to sanitize with invalid value
		$result = $settings->_sanitize(array('validated_field' => 'ab'));

		// Assert: Validator was called, original value preserved
		self::assertTrue($validatorCalled, 'Validator should be called');
		self::assertSame('original_value', $result['validated_field'] ?? null, 'Original value should be preserved on validation failure');
	}

	/**
	 * @test
	 * Verify validation failure in RegisterOptions schema prevents UserSettings save.
	 *
	 * Note: This test verifies that the validator is called and rejects invalid input.
	 * The invalid value should NOT be persisted.
	 */
	public function test_seam_validation_failure_prevents_usersettings_save(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin');

		$userId = 777;
		// Start with empty storage - we'll verify the invalid value is NOT written
		$this->userMetaValues['val_fail_user'] = array($userId => array());
		$options                               = new RegisterOptions('val_fail_user', StorageContext::forUser($userId), false, $this->logger);

		$validatorCalled        = false;
		$validatorReceivedValue = null;
		$options->register_schema(array(
			'user_validated_field' => array(
				'default'  => 'default_value',
				'sanitize' => array(fn($v) => trim((string) $v)),
				'validate' => array(function ($v, $emit) use (&$validatorCalled, &$validatorReceivedValue) {
					$validatorCalled        = true;
					$validatorReceivedValue = $v;
					if (strlen((string) $v) < 5) {
						$emit('Value must be at least 5 characters');
						return false;
					}
					return true;
				}),
			),
		));

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$manifest = new ComponentManifest($loader, $this->logger);

		$manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult('<input type="text">', component_type: 'input');
		});

		$settings = new UserSettings($options, $manifest, $config, $this->logger);

		$settings->collection('profile')
			->section('vf-section', 'Validation Section')
				->field('user_validated_field', 'User Validated Field', 'fields.input')
			->end_section()
		->end_collection();

		$this->captureOutput(fn() => $settings->render('profile'));

		// Act: Try to save with invalid value (too short)
		$settings->save_settings(array('user_validated_field' => 'ab'), array('user_id' => $userId));

		// Assert: Validator was called with the submitted value
		self::assertTrue($validatorCalled, 'Validator should be called');
		self::assertSame('ab', $validatorReceivedValue, 'Validator should receive the sanitized (trimmed) value');

		// The invalid value 'ab' should NOT have been persisted
		$storedOptions = $this->userMetaValues['val_fail_user'][$userId] ?? array();
		self::assertNotSame('ab', $storedOptions['user_validated_field'] ?? null, 'Invalid value should NOT be persisted');
	}

	// =========================================================================
	// SEAM 6: Scope Mismatch Detection
	// =========================================================================

	/**
	 * @test
	 * Verify AdminSettings rejects user-scoped RegisterOptions.
	 */
	public function test_seam_adminsettings_rejects_user_scope(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));

		$options = new RegisterOptions('wrong_scope', StorageContext::forUser(123), false, $this->logger);

		$loader   = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$manifest = new ComponentManifest($loader, $this->logger);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('AdminSettings requires site context');

		new AdminSettings($options, $manifest, $config, $this->logger);
	}

	/**
	 * @test
	 * Verify UserSettings rejects site-scoped RegisterOptions.
	 */
	public function test_seam_usersettings_rejects_site_scope(): void {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));

		$options = new RegisterOptions('wrong_scope', StorageContext::forSite(), false, $this->logger);

		$loader   = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$manifest = new ComponentManifest($loader, $this->logger);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('UserSettings requires user context');

		new UserSettings($options, $manifest, $config, $this->logger);
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	protected function captureOutput(callable $fn): string {
		ob_start();
		$fn();
		return ob_get_clean() ?: '';
	}

	protected function cleanupDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->cleanupDirectory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}
