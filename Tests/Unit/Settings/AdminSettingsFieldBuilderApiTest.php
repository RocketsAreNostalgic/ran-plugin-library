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
		->end_menu_group();

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'base-page'
			    && ($entry['context']['section_id'] ?? null)   === 'base-section'
			    && ($entry['context']['field_id'] ?? null)     === 'base-input';
		});
		self::assertNotEmpty($fieldLogs, 'Expected field updated logs for base-input.');
		$fieldContext = $this->findLogByFieldId($fieldLogs, 'base-input')['context'];
		self::assertSame('Base Input', $fieldContext['label']);
		self::assertSame('fields.input', $fieldContext['component']);
		self::assertSame(42, $fieldContext['order']);
		self::assertSame('base-input', $fieldContext['component_context']['name'] ?? null);
		self::assertSame('Base component description', $fieldContext['component_context']['description'] ?? null);
		self::assertSame(
			array(
				'data-test'  => 'input-base',
				'aria-label' => 'Base Input Label',
				'data-extra' => 'extra',
			),
			$fieldContext['component_context']['attributes'] ?? null
		);

		$templateLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.template_override'
			    && ($entry['context']['element_type'] ?? null) === 'field'
			    && ($entry['context']['element_id'] ?? null)   === 'base-input';
		});
		self::assertCount(1, $templateLogs, 'Expected template override log for base-input field.');
		self::assertSame(
			array('field-wrapper' => 'custom-field-wrapper'),
			$templateLogs[0]['context']['overrides'] ?? null
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
		->end_menu_group();

		$buttonLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'button-page'
			    && ($entry['context']['section_id'] ?? null)   === 'button-section';
		});
		self::assertNotEmpty($buttonLogs, 'Expected button field update logs.');

		$ctaLog = $this->findLogByFieldId($buttonLogs, 'cta-button');
		self::assertNotNull($ctaLog, 'CTA button log missing.');
		$ctaContext = $ctaLog['context'];
		self::assertSame(7, $ctaContext['order']);
		self::assertSame('elements.button', $ctaContext['component']);
		self::assertTrue($ctaContext['component_context']['disabled'] ?? false);

		$ctaEnabledLog = $this->findLogByFieldId($buttonLogs, 'cta-button-enabled');
		self::assertNotNull($ctaEnabledLog, 'Enabled CTA button log missing.');
		$ctaEnabledContext = $ctaEnabledLog['context'];
		self::assertSame('elements.button', $ctaEnabledContext['component']);
		self::assertSame('Call To Action', $ctaEnabledContext['label']);
		self::assertFalse(array_key_exists('disabled', $ctaEnabledContext['component_context']));
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
