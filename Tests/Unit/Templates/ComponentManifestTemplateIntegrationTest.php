<?php
/**
 * Integration test for ComponentManifest template registration access.
 * Validates that ComponentManifest provides access to ComponentLoader for template registration.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Templates;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Component\ComponentManifest
 * @covers \Ran\PluginLib\Forms\Component\ComponentLoader
 * @covers \Ran\PluginLib\Forms\Component\ComponentRenderResult
 */
class ComponentManifestTemplateIntegrationTest extends TestCase {
	private ComponentManifest $manifest;
	private string $fixtures_root;

	protected function setUp(): void {
		WP_Mock::setUp();

		WP_Mock::userFunction('get_option')->andReturn(false);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_site_option')->andReturn(false);
		WP_Mock::userFunction('update_site_option')->andReturn(true);
		WP_Mock::userFunction('delete_site_option')->andReturn(true);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('is_multisite')->andReturn(false);
		WP_Mock::userFunction('wp_hash')->andReturnUsing(static fn(string $value): string => md5($value));

		$this->fixtures_root = dirname(__DIR__) . '/fixtures/templates';

		$logger         = new Logger();
		$loader         = new ComponentLoader($this->fixtures_root, $logger);
		$this->manifest = new ComponentManifest($loader, $logger);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Test that ComponentManifest provides access to ComponentLoader for template registration.
	 */
	public function test_component_manifest_provides_loader_access(): void {
		// Access ComponentLoader through ComponentManifest (simulating real usage)
		$loader = $this->get_component_loader_from_manifest();

		$this->assertInstanceOf(ComponentLoader::class, $loader);
	}

	/**
	 * Test template registration through ComponentManifest integration.
	 */
	public function test_template_registration_through_manifest(): void {
		// Access ComponentLoader through ComponentManifest
		$loader = $this->get_component_loader_from_manifest();

		// Register template
		$loader->register('integration.test', 'manifest/integration-test.php');

		// Verify template is registered
		$aliases = $loader->aliases();
		$this->assertArrayHasKey('integration.test', $aliases);
		$this->assertEquals('manifest/integration-test.php', $aliases['integration.test']);

		// Test rendering
		$result = $loader->render('integration.test', array('message' => 'Integration Success'));
		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertEquals('<div class="integration-test">Integration Success</div>', $result->markup);
		$this->assertEquals('layout_wrapper', $result->component_type);
	}

	/**
	 * Test batch template registration through ComponentManifest.
	 */
	public function test_batch_template_registration_through_manifest(): void {
		// Create test templates
		$templates = array(
		    'batch.page'          => 'manifest/batch/page.php',
		    'batch.section'       => 'manifest/batch/section.php',
		    'batch.field-wrapper' => 'manifest/batch/field-wrapper.php',
		);

		// Access ComponentLoader through ComponentManifest
		$loader = $this->get_component_loader_from_manifest();

		// Register templates using chained calls
		$loader
		    ->register('batch.page', $templates['batch.page'])
		    ->register('batch.section', $templates['batch.section'])
		    ->register('batch.field-wrapper', $templates['batch.field-wrapper']);

		// Verify all templates registered
		$aliases = $loader->aliases();
		foreach (array_keys($templates) as $alias) {
			$this->assertArrayHasKey($alias, $aliases);
		}

		// Test rendering
		$this->assertEquals('<main class="batch-page">Page Content</main>',
			$loader->render('batch.page', array('inner_html' => 'Page Content'))->markup);
		$this->assertEquals('layout_wrapper', $loader->render('batch.page', array('inner_html' => 'Page Content'))->component_type);
		$this->assertEquals('<section class="batch-section">Section Content</section>',
			$loader->render('batch.section', array('inner_html' => 'Section Content'))->markup);
		$this->assertEquals('layout_wrapper', $loader->render('batch.section', array('inner_html' => 'Section Content'))->component_type);
		$this->assertEquals('<div class="batch-field"><input></div>',
			$loader->render('batch.field-wrapper', array('inner_html' => '<input>'))->markup);
		$this->assertEquals('layout_wrapper', $loader->render('batch.field-wrapper', array('inner_html' => '<input>'))->component_type);
	}

	/**
	 * Test that ComponentManifest and ComponentLoader work together for both components and templates.
	 */
	public function test_unified_component_and_template_system(): void {
		// Access ComponentLoader through ComponentManifest
		$loader = $this->get_component_loader_from_manifest();

		$loader
		    ->register('test.component', 'components/test-component.php')
		    ->register('test.wrapper', 'wrappers/test-wrapper.php');

		// Register component factory with ComponentManifest
		$this->manifest->register('test.component', function(array $context) use ($loader) {
			$result = $loader->render_payload('test.component', $context);
			return $result;
		});

		// Test component rendering through ComponentManifest
		$component_result = $this->manifest->render('test.component', array('value' => 'test-value'));
		$this->assertStringContainsString('test-value', $component_result->markup);
		$this->assertTrue($component_result->submits_data());
		$this->assertEquals('input', $component_result->component_type);

		// Test template rendering through ComponentLoader
		$wrapper_result = $loader->render('test.wrapper', array('inner_html' => $component_result->markup));
		$this->assertInstanceOf(ComponentRenderResult::class, $wrapper_result);
		$this->assertEquals('<div class="wrapper"><input type="text" name="test" value="test-value"></div>', $wrapper_result->markup);
		$this->assertEquals('layout_wrapper', $wrapper_result->component_type);
	}

	// Helper methods

	private function get_component_loader_from_manifest(): ComponentLoader {
		$reflection = new \ReflectionClass($this->manifest);
		$property   = $reflection->getProperty('views');
		$property->setAccessible(true);
		return $property->getValue($this->manifest);
	}
}
