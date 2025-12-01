<?php
/**
 * Tests for global template registration patterns.
 * Validates all registration methods documented in GlobalTemplateRegistration.md
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
 * @covers \Ran\PluginLib\Forms\Component\ComponentLoader
 * @covers \Ran\PluginLib\Forms\Component\ComponentManifest
 * @covers \Ran\PluginLib\Forms\Component\ComponentRenderResult
 */
class GlobalTemplateRegistrationTest extends TestCase {
	private ComponentLoader $loader;
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
		$logger              = new Logger();
		$this->loader        = new ComponentLoader($this->fixtures_root, $logger);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Test single template registration pattern.
	 */
	public function test_single_template_registration(): void {
		$result = $this->loader->register('my.custom-page', 'custom-page.php');
		$this->assertSame($this->loader, $result);

		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('my.custom-page', $aliases);
		$this->assertEquals('custom-page.php', $aliases['my.custom-page']);

		$rendered = $this->loader->render('my.custom-page', array('title' => 'Test Page'));
		$this->assertInstanceOf(ComponentRenderResult::class, $rendered);
		$this->assertEquals('<div class="custom-page">Test Page</div>', $rendered->markup);
	}

	/**
	 * Test batch template registration using chained calls.
	 */
	public function test_batch_template_registration_chained(): void {
		$templates = array(
		    'mytheme.page'          => 'mytheme/page.php',
		    'mytheme.section'       => 'mytheme/section.php',
		    'mytheme.group'         => 'mytheme/group.php',
		    'mytheme.field-wrapper' => 'mytheme/field-wrapper.php',
		);

		$result = $this->loader
		    ->register('mytheme.page', $templates['mytheme.page'])
		    ->register('mytheme.section', $templates['mytheme.section'])
		    ->register('mytheme.group', $templates['mytheme.group'])
		    ->register('mytheme.field-wrapper', $templates['mytheme.field-wrapper']);

		$this->assertSame($this->loader, $result);

		$aliases = $this->loader->aliases();
		foreach ($templates as $alias => $path) {
			$this->assertArrayHasKey($alias, $aliases);
			$this->assertEquals($path, $aliases[$alias]);
		}

		$this->assertEquals('<div class="theme-page">Test Content</div>',
			$this->loader->render('mytheme.page', array('inner_html' => 'Test Content'))->markup);
		$this->assertEquals('<section class="theme-section">Test Section</section>',
			$this->loader->render('mytheme.section', array('inner_html' => 'Test Section'))->markup);
		$this->assertEquals('<fieldset class="theme-group">Test Group</fieldset>',
			$this->loader->render('mytheme.group', array('inner_html' => 'Test Group'))->markup);
		$this->assertEquals('<div class="theme-field"><input type="text"></div>',
			$this->loader->render('mytheme.field-wrapper', array('inner_html' => '<input type="text">'))->markup);
	}

	/**
	 * Test batch template registration using array mapping.
	 */
	public function test_batch_template_registration_array(): void {
		$templates = array(
		    'modern.page'          => 'themes/modern/page.php',
		    'modern.section'       => 'themes/modern/section.php',
		    'modern.group'         => 'themes/modern/group.php',
		    'modern.field-wrapper' => 'themes/modern/field-wrapper.php',
		);

		foreach ($templates as $alias => $path) {
			$this->loader->register($alias, $path);
		}

		$aliases = $this->loader->aliases();
		foreach ($templates as $alias => $path) {
			$this->assertArrayHasKey($alias, $aliases);
			$this->assertEquals($path, $aliases[$alias]);
		}

		$this->assertEquals('<main class="modern-page">Page Content</main>',
			$this->loader->render('modern.page', array('inner_html' => 'Page Content'))->markup);
	}

	/**
	 * Test complete custom theme registration pattern.
	 */
	public function test_complete_custom_theme_registration(): void {
		$theme_templates = array(
		    'complete.page'                   => 'themes/complete/pages/page.php',
		    'complete.grid-page'              => 'themes/complete/pages/grid-page.php',
		    'complete.sidebar-page'           => 'themes/complete/pages/sidebar-page.php',
		    'complete.section'                => 'themes/complete/sections/section.php',
		    'complete.card-section'           => 'themes/complete/sections/card-section.php',
		    'complete.collapsible-section'    => 'themes/complete/sections/collapsible-section.php',
		    'complete.group'                  => 'themes/complete/groups/group.php',
		    'complete.inline-group'           => 'themes/complete/groups/inline-group.php',
		    'complete.fieldset-group'         => 'themes/complete/groups/fieldset-group.php',
		    'complete.field-wrapper'          => 'themes/complete/fields/field-wrapper.php',
		    'complete.compact-field-wrapper'  => 'themes/complete/fields/compact-field-wrapper.php',
		    'complete.floating-label-wrapper' => 'themes/complete/fields/floating-label-wrapper.php',
		);

		foreach ($theme_templates as $alias => $path) {
			$this->loader->register($alias, $path);
		}

		$aliases = $this->loader->aliases();
		foreach ($theme_templates as $alias => $path) {
			$this->assertArrayHasKey($alias, $aliases);
			$this->assertEquals($path, $aliases[$alias]);
		}

		$this->assertStringContainsString('complete-page',
			$this->loader->render('complete.page', array('inner_html' => 'Test'))->markup);
		$this->assertStringContainsString('complete-section',
			$this->loader->render('complete.section', array('inner_html' => 'Test'))->markup);
		$this->assertStringContainsString('complete-group',
			$this->loader->render('complete.group', array('inner_html' => 'Test'))->markup);
		$this->assertStringContainsString('complete-field-wrapper',
			$this->loader->render('complete.field-wrapper', array('inner_html' => '<input>'))->markup);
	}

	/**
	 * Test template registration with validation.
	 */
	public function test_template_registration_with_validation(): void {
		$this->loader->register('test.valid', 'valid.php');

		$aliases = $this->loader->aliases();
		$this->assertArrayHasKey('test.valid', $aliases);

		$this->loader->register('test.invalid', 'non-existent.php');

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('Template "test.invalid" not found');
		$this->loader->render('test.invalid');
	}

	/**
	 * Test conditional template registration.
	 */
	public function test_conditional_template_registration(): void {
		$default_templates = array(
		    'conditional.default-page'    => 'conditional/default/page.php',
		    'conditional.default-section' => 'conditional/default/section.php',
		);
		$premium_templates = array(
		    'conditional.premium-page'    => 'conditional/premium/page.php',
		    'conditional.premium-section' => 'conditional/premium/section.php',
		);

		$use_premium           = false;
		$templates_to_register = $use_premium ? $premium_templates : $default_templates;

		foreach ($templates_to_register as $alias => $path) {
			$this->loader->register($alias, $path);
		}

		$aliases = $this->loader->aliases();
		foreach ($templates_to_register as $alias => $path) {
			$this->assertArrayHasKey($alias, $aliases);
			$this->assertEquals($path, $aliases[$alias]);
		}

		$this->assertEquals('<div class="default-page">Test</div>',
			$this->loader->render('conditional.default-page', array('inner_html' => 'Test'))->markup);
	}

	/**
	 * Test template registration error handling.
	 */
	public function test_template_registration_error_handling(): void {
		$this->loader->register('test.path-traversal', '../../../etc/passwd');

		$this->expectException(\LogicException::class);
		$this->loader->render('test.path-traversal');
	}

	/**
	 * Test template override functionality.
	 */
	public function test_template_override_functionality(): void {
		$this->loader->register('test.template', 'overrides/original.php');
		$this->assertEquals('<div class="original">Test</div>',
			$this->loader->render('test.template', array('inner_html' => 'Test'))->markup);

		$this->loader->register('test.template', 'overrides/override.php');
		$this->assertEquals('<div class="override">Test</div>',
			$this->loader->render('test.template', array('inner_html' => 'Test'))->markup);
	}

	/**
	 * Test integration with ComponentManifest.
	 */
	public function test_integration_with_component_manifest(): void {
		$logger   = new Logger();
		$manifest = new ComponentManifest($this->loader, $logger);

		$this->loader->register('manifest.test', 'manifest/manifest-test.php');

		$this->assertEquals('<div class="manifest-template">Integration Test</div>',
			$this->loader->render('manifest.test', array('inner_html' => 'Integration Test'))->markup);
	}
}
