<?php
/**
 * AdminSettings Field Builder API Tests
 *
 * Tests the builder API chain mechanics and metadata storage.
 * Does NOT test actual component rendering - see AdminSettingsBehaviorTest for render tests.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Tools\TestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * @covers \Ran\PluginLib\Forms\FormsBaseTrait::get_form_session
 */
final class AdminSettingsFieldBuilderApiTest extends TestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger_mock;
	public function setUp(): void {
		\WP_Mock::setUp();

		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('is_network_admin')->andReturn(false);
		\WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		\WP_Mock::userFunction('get_transient')->andReturn(false);
		\WP_Mock::userFunction('set_transient')->andReturn(true);
		\WP_Mock::userFunction('delete_transient')->andReturn(true);
		\WP_Mock::userFunction('sanitize_key')->andReturnArg(0);

		$this->logger_mock = new CollectingLogger();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	public function test_field_builder_proxy_exposes_explicit_methods(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('base-menu')
		    ->page('base-page')
		        ->section('base-section', 'Base Section')
		            ->field('base-input', 'Base Input', 'fields.input')
		                ->order(42)
		                ->attribute('data-test', 'input-base')
		                ->attribute('aria-label', 'Base Input Label')
		                ->attribute('data-extra', 'extra')
		                ->description('Base component description')
		                ->template('custom-field-wrapper')
		            ->end_field()
		        ->end_section()
		    ->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$fields = $this->getProperty($admin, 'fields');
		self::assertArrayHasKey('base-page', $fields);
		self::assertArrayHasKey('base-section', $fields['base-page']);
		$sectionFields = $fields['base-page']['base-section'];
		$fieldEntry    = null;
		foreach ($sectionFields as $f) {
			if ($f['id'] === 'base-input') {
				$fieldEntry = $f;
				break;
			}
		}
		self::assertNotNull($fieldEntry, 'Expected field entry for base-input.');
		self::assertSame('Base Input', $fieldEntry['label']);
		self::assertSame('fields.input', $fieldEntry['component']);
		self::assertSame(42, $fieldEntry['order']);
		self::assertSame('base-input', $fieldEntry['component_context']['name'] ?? null);
		self::assertSame('Base component description', $fieldEntry['component_context']['description'] ?? null);
		self::assertSame(
			array(
				'data-test'  => 'input-base',
				'aria-label' => 'Base Input Label',
				'data-extra' => 'extra',
			),
			$fieldEntry['component_context']['attributes'] ?? null
		);

		$session = $admin->get_form_session();
		self::assertNotNull($session, 'Form session should be initialized');
		$templateResolver = $session->template_resolver();
		$fieldOverrides   = $templateResolver->get_field_template_overrides('base-input');
		self::assertSame('custom-field-wrapper', $fieldOverrides['field-wrapper'] ?? null);
	}

	public function test_field_builder_proxy_disabled_method(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('button-menu')
		    ->page('button-page')
		        ->section('button-section', 'Button Section')
		            ->field('cta-button', 'Call To Action', 'elements.button')
		                ->disabled()
		                ->order(7)
		            ->end_field()
		            ->field('cta-button-enabled', 'Call To Action', 'elements.button')
		                ->disabled(false)
		            ->end_field()
		        ->end_section()
		    ->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$fields = $this->getProperty($admin, 'fields');
		self::assertArrayHasKey('button-page', $fields);
		self::assertArrayHasKey('button-section', $fields['button-page']);
		$sectionFields = $fields['button-page']['button-section'];

		$ctaField        = null;
		$ctaEnabledField = null;
		foreach ($sectionFields as $f) {
			if ($f['id'] === 'cta-button') {
				$ctaField = $f;
			} elseif ($f['id'] === 'cta-button-enabled') {
				$ctaEnabledField = $f;
			}
		}

		self::assertNotNull($ctaField, 'CTA button field missing.');
		self::assertSame(7, $ctaField['order']);
		self::assertSame('elements.button', $ctaField['component']);
		self::assertTrue($ctaField['component_context']['disabled'] ?? false);

		self::assertNotNull($ctaEnabledField, 'Enabled CTA button field missing.');
		self::assertSame('elements.button', $ctaEnabledField['component']);
		self::assertSame('Call To Action', $ctaEnabledField['label']);
		self::assertFalse(array_key_exists('disabled', $ctaEnabledField['component_context']));
	}

	private function createAdminSettings(): AdminSettings {
		$logger   = $this->logger_mock;
		$baseDir  = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader   = new ComponentLoader($baseDir, $logger);
		$manifest = new ComponentManifest($loader, $logger);
		$options  = RegisterOptions::site('field_builder_admin_settings', true, $logger);

		$options->register_schema(array(
		    'base-input'         => $this->stringSchema(),
		    'cta-button'         => $this->stringSchema(),
		    'cta-button-enabled' => $this->stringSchema(),
		));

		return new AdminSettings($options, $manifest, null, $logger);
	}

	/**
	 * @param array<int, array{context: array<string, mixed>}> $logs
	 */
	private function findLogByFieldId(array $logs, string $fieldId): ?array {
		for ($index = count($logs) - 1; $index >= 0; $index--) {
			$entry = $logs[$index];
			if (($entry['context']['field_id'] ?? null) === $fieldId) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Helper: Get a property value from AdminSettings via reflection.
	 */
	private function getProperty(AdminSettings $admin, string $property): mixed {
		$reflection = new \ReflectionClass($admin);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);
		return $prop->getValue($admin);
	}

	private function stringSchema(): array {
		return array(
		    'default'  => '',
		    'sanitize' => array(static function($value) {
		    	return is_scalar($value) ? trim((string) $value) : '';
		    }),
		    'validate' => array(static function($value, callable $emitWarning) {
		    	return is_string($value);
		    }),
		);
	}
}
