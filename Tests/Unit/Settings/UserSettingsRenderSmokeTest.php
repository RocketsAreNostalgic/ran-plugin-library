<?php
/**
 * UserSettings External Component Render Smoke Tests
 *
 * Tests external component registration and rendering for UserSettings.
 * Covers the 8-combination matrix and save-flow tests.
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * @covers \Ran\PluginLib\Settings\UserSettings
 */
class UserSettingsRenderSmokeTest extends PluginLibTestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger;
	protected array $optionValues   = array();
	protected array $userMetaValues = array();

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->logger_mock instanceof CollectingLogger
			? $this->logger_mock
			: new CollectingLogger(array());
		$this->logger->collected_logs = array();
		$this->optionValues           = array();
		$this->userMetaValues         = array();

		WP_Mock::userFunction('get_option')->andReturnUsing(
			fn($name, $default = array()) => $this->optionValues[$name] ?? $default
		);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_user_meta')->andReturnUsing(
			fn($user_id, $key, $single) => $this->userMetaValues[$key] ?? array()
		);
		WP_Mock::userFunction('update_user_meta')->andReturnUsing(
			function($user_id, $key, $value) {
				$this->userMetaValues[$key] = $value;
				return true;
			}
		);
		WP_Mock::userFunction('delete_user_meta')->andReturn(true);
		WP_Mock::userFunction('get_user_option')->andReturn(false);
		WP_Mock::userFunction('update_user_option')->andReturn(true);
		WP_Mock::userFunction('delete_user_option')->andReturn(true);
		WP_Mock::userFunction('get_current_user_id')->andReturn(123);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/** @test */
	public function test_matrix_1_view_only(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m1-' . uniqid();
		mkdir($externalDir . '/ViewOnly', 0777, true);
		file_put_contents($externalDir . '/ViewOnly/View.php', $this->createViewPhp('view-only'));

		try {
			$this->userMetaValues['matrix_1'] = array('field_1' => 'stored_value_1');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_1');
			$settings->register_component('view-only', array('path' => 'ViewOnly', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s1', 'Section')->field('field_1', 'Label 1', 'ext.view-only')->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="view-only"', $output);
			self::assertStringContainsString('stored_value_1', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_2_view_validator(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m2-' . uniqid();
		mkdir($externalDir . '/ViewValidator', 0777, true);
		file_put_contents($externalDir . '/ViewValidator/View.php', $this->createViewPhp('view-validator'));
		file_put_contents($externalDir . '/ViewValidator/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'ViewValidator'));

		try {
			$this->userMetaValues['matrix_2'] = array('field_2' => 'validated_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_2');
			$settings->register_component('view-validator', array('path' => 'ViewValidator', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s2', 'Section')->field('field_2', 'Label 2', 'ext.view-validator')->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="view-validator"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_3_view_normalizer(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m3-' . uniqid();
		mkdir($externalDir . '/ViewNormalizer', 0777, true);
		file_put_contents($externalDir . '/ViewNormalizer/View.php', $this->createViewPhp('view-normalizer'));
		file_put_contents($externalDir . '/ViewNormalizer/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'ViewNormalizer'));

		try {
			$this->userMetaValues['matrix_3'] = array('field_3' => 'normalized_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_3');
			$settings->register_component('view-normalizer', array('path' => 'ViewNormalizer', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s3', 'Section')->field('field_3', 'Label 3', 'ext.view-normalizer')->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="view-normalizer"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_4_view_validator_normalizer(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m4-' . uniqid();
		mkdir($externalDir . '/FullStack', 0777, true);
		file_put_contents($externalDir . '/FullStack/View.php', $this->createViewPhp('full-stack'));
		file_put_contents($externalDir . '/FullStack/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'FullStack'));
		file_put_contents($externalDir . '/FullStack/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'FullStack'));

		try {
			$this->userMetaValues['matrix_4'] = array('field_4' => 'full_stack_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_4');
			$settings->register_component('full-stack', array('path' => 'FullStack', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s4', 'Section')->field('field_4', 'Label 4', 'ext.full-stack')->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="full-stack"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_5_view_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m5-' . uniqid();
		mkdir($externalDir . '/ViewDevSchema', 0777, true);
		file_put_contents($externalDir . '/ViewDevSchema/View.php', $this->createViewPhp('view-dev-schema'));

		try {
			$this->userMetaValues['matrix_5'] = array('field_5' => 'schema_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_5');
			$settings->register_component('view-dev-schema', array('path' => 'ViewDevSchema', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s5', 'Section')
					->field('field_5', 'Label 5', 'ext.view-dev-schema', array(
						'schema' => array('default' => 'default_value', 'sanitize' => array(fn($v) => trim((string) $v))),
					))
				->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="view-dev-schema"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_6_view_validator_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m6-' . uniqid();
		mkdir($externalDir . '/ValidatorDevSchema', 0777, true);
		file_put_contents($externalDir . '/ValidatorDevSchema/View.php', $this->createViewPhp('validator-dev-schema'));
		file_put_contents($externalDir . '/ValidatorDevSchema/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'ValidatorDevSchema'));

		try {
			$this->userMetaValues['matrix_6'] = array('field_6' => 'validated_schema_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_6');
			$settings->register_component('validator-dev-schema', array('path' => 'ValidatorDevSchema', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s6', 'Section')
					->field('field_6', 'Label 6', 'ext.validator-dev-schema', array(
						'schema' => array('default' => 'validated_default'),
					))
				->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="validator-dev-schema"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_7_view_normalizer_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m7-' . uniqid();
		mkdir($externalDir . '/NormalizerDevSchema', 0777, true);
		file_put_contents($externalDir . '/NormalizerDevSchema/View.php', $this->createViewPhp('normalizer-dev-schema'));
		file_put_contents($externalDir . '/NormalizerDevSchema/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'NormalizerDevSchema'));

		try {
			$this->userMetaValues['matrix_7'] = array('field_7' => 'normalized_schema_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_7');
			$settings->register_component('normalizer-dev-schema', array('path' => 'NormalizerDevSchema', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s7', 'Section')
					->field('field_7', 'Label 7', 'ext.normalizer-dev-schema', array(
						'schema' => array('default' => 'normalized_default'),
					))
				->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="normalizer-dev-schema"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/** @test */
	public function test_matrix_8_full_stack_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-user-m8-' . uniqid();
		mkdir($externalDir . '/FullStackSchema', 0777, true);
		file_put_contents($externalDir . '/FullStackSchema/View.php', $this->createViewPhp('full-stack-schema'));
		file_put_contents($externalDir . '/FullStackSchema/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'FullStackSchema'));
		file_put_contents($externalDir . '/FullStackSchema/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'FullStackSchema'));

		try {
			$this->userMetaValues['matrix_8'] = array('field_8' => 'ultimate_value');
			$settings                         = $this->createExternalComponentSettings($externalDir, 'matrix_8');
			$settings->register_component('full-stack-schema', array('path' => 'FullStackSchema', 'prefix' => 'ext'));

			$settings->collection('profile')
				->section('s8', 'Section')
					->field('field_8', 'Label 8', 'ext.full-stack-schema', array(
						'schema' => array('default' => 'ultimate_default', 'validate' => array(fn($v, $emit) => !empty($v))),
					))
				->end_section()
			->end_collection();

			$output = $this->captureOutput(fn() => $settings->render('profile'));
			self::assertStringContainsString('data-marker="full-stack-schema"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// SAVE FLOW TESTS
	// Note: UserSettings save-flow tests have been moved to SettingsIntegrationSeamsTest
	// which provides comprehensive cross-boundary integration testing for both
	// AdminSettings and UserSettings, including sanitizer/validator execution,
	// external component discovery, and validation failure propagation.
	// =========================================================================

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	private function createExternalComponentSettings(string $externalDir, string $optionName): UserSettings {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => $externalDir));
		$config->method('get_namespace')->willReturn('TestPlugin\\Components');

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);

		// Register loader mappings (required for path resolution)
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('section-wrapper', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$loader->register('shared.field-wrapper', 'field-wrapper.php');

		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionName, StorageContext::forUser(123), false, $this->logger);

		return new UserSettings($options, $manifest, $config, $this->logger);
	}

	private function createViewPhp(string $marker): string {
		return '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$name  = htmlspecialchars($context["name"] ?? "", ENT_QUOTES, "UTF-8");
$value = htmlspecialchars((string)($context["value"] ?? ""), ENT_QUOTES, "UTF-8");
$label = htmlspecialchars($context["label"] ?? "", ENT_QUOTES, "UTF-8");

$markup = sprintf(
    \'<div class="ext-component" data-marker="%s"><label>%%s</label><input type="text" name="%%s" value="%%s"></div>\',
    \'' . $marker . '\'
);
$markup = sprintf($markup, $label, $name, $value);

return new ComponentRenderResult(markup: $markup, component_type: "input");
';
	}

	private function createValidatorPhp(string $namespace, string $componentName): string {
		return <<<PHP
<?php
namespace $namespace\\$componentName;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
class Validator implements ValidatorInterface {
    public function __construct(?\Psr\Log\LoggerInterface \$logger = null) {}
    public function validate(mixed \$value, array \$context, callable \$emitWarning): bool { return true; }
}
PHP;
	}

	private function createNormalizerPhp(string $namespace, string $componentName): string {
		return <<<PHP
<?php
namespace $namespace\\$componentName;
use Ran\PluginLib\Forms\Component\Normalize\NormalizeInterface;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Util\TranslationService;
class Normalizer implements NormalizeInterface {
    public function __construct(ComponentLoader \$views) {}
    public function render(array \$context, ComponentNormalizationContext \$session, string \$componentAlias): array {
        return array('payload' => array('markup' => '<div>normalized</div>', 'script' => null, 'style' => null, 'requires_media' => false, 'context_schema' => array('required' => array(), 'optional' => array(), 'defaults' => array())), 'warnings' => array());
    }
    public static function create_translation_service(string \$textDomain = 'ran-plugin-lib'): TranslationService {
        return new TranslationService(\$textDomain);
    }
}
PHP;
	}

	private function cleanupDirectory(string $dir): void {
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

	private function createExternalComponentSettingsWithNamespace(string $externalDir, string $optionName, string $namespace): UserSettings {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH'      => $externalDir,
			'SLUG'      => 'ext-test',
			'NAMESPACE' => $namespace,
		));
		$config->method('get_namespace')->willReturn($namespace);

		$loader = new ComponentLoader(__DIR__ . '/../fixtures/templates', $this->logger);
		$loader->register('user.root-wrapper', 'default-page.php');
		$loader->register('root-wrapper', 'default-page.php');
		$loader->register('section', 'section.php');
		$loader->register('section-wrapper', 'section.php');
		$loader->register('field-wrapper', 'field-wrapper.php');
		$loader->register('shared.field-wrapper', 'field-wrapper.php');

		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionName, StorageContext::forUser(123), false, $this->logger);

		return new UserSettings($options, $manifest, $config, $this->logger);
	}

	private function injectManifestDefaults(UserSettings $settings, string $alias, array $defaults): void {
		$settingsRef  = new \ReflectionObject($settings);
		$manifestProp = $settingsRef->getProperty('components');
		$manifestProp->setAccessible(true);
		$manifest = $manifestProp->getValue($settings);

		$manifestRef  = new \ReflectionObject($manifest);
		$metadataProp = $manifestRef->getProperty('componentMetadata');
		$metadataProp->setAccessible(true);
		$metadata = $metadataProp->getValue($manifest);

		if (!is_array($metadata)) {
			$metadata = array();
		}

		$current              = $metadata[$alias] ?? array();
		$current['defaults']  = $defaults;
		$current['validator'] = UserSettingsMockComponentValidator::class;
		$metadata[$alias]     = $current;

		$metadataProp->setValue($manifest, $metadata);

		$factoriesCacheProp = $manifestRef->getProperty('validatorFactoriesCache');
		$factoriesCacheProp->setAccessible(true);
		$factoriesCacheProp->setValue($manifest, null);
	}

	private function clearCatalogueCache(UserSettings $settings): void {
		$settingsRef = new \ReflectionObject($settings);
		$cacheProp   = $settingsRef->getProperty('__catalogue_cache');
		$cacheProp->setAccessible(true);
		$cacheProp->setValue($settings, null);
	}
}

/**
 * Mock validator for testing save flow.
 */
class UserSettingsMockComponentValidator implements \Ran\PluginLib\Forms\Component\Validate\ValidatorInterface {
	public function __construct(?\Psr\Log\LoggerInterface $logger = null) {
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		return true;
	}
}
