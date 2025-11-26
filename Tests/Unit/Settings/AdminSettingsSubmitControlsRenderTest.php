<?php
/**
 * Submit controls render coverage for AdminSettings.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Tools\TestCase;
use WP_Mock;
use ReflectionProperty;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * @covers \Ran\PluginLib\Settings\AdminSettings
 */
final class AdminSettingsSubmitControlsRenderTest extends TestCase {
	private const OPTION_KEY = 'submit_render_options';

	private CollectingLogger $logger;
	private ComponentManifest $manifest;
	/** @var array<string,mixed> */
	private array $optionStore = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('settings_fields')->andReturnNull();
		WP_Mock::userFunction('submit_button')->andReturnNull();
		WP_Mock::userFunction('apply_filters')->andReturnUsing(static fn($hook, $value) => $value);
		WP_Mock::userFunction('get_option')->andReturnUsing(function (string $option, mixed $default = false) {
			return array_key_exists($option, $this->optionStore) ? $this->optionStore[$option] : $default;
		});

		$baseDir = __DIR__ . '/../../fixtures/templates';
		$loader  = new ComponentLoader($baseDir);
		$loader->register('section', 'admin/sections/test-section.php');
		$loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('section-wrapper', 'admin/section-wrapper.php');
		$this->logger   = new CollectingLogger();
		$this->manifest = new ComponentManifest($loader, $this->logger);

		$this->registerTemplateStubs();

		$this->optionStore = array(
		    self::OPTION_KEY => array(),
		);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_render_outputs_default_submit_controls(): void {
		$settings = $this->createAdminSettings();

		$settings->menu_group('defaults-group')
		    ->page('defaults-page')
		        ->heading('Default Submit Page')
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array('example_field' => 'value'));

		ob_start();
		$settings->render('defaults-page');
		$output = (string) ob_get_clean();

		self::assertStringContainsString('class="ran-zone-wrapper ran-zone-wrapper--submit-controls"', $output, 'Default submit wrapper missing.');
		self::assertStringContainsString('data-zone="primary-controls"', $output, 'Default zone id missing.');
		self::assertStringContainsString('<button type="submit">Save Changes</button>', $output, 'Default submit button markup missing.');
		self::assertStringNotContainsString('custom-submit-area', $output, 'Custom submit template should not render without override.');
	}

	public function test_render_custom_submit_controls_with_override_and_links(): void {
		$settings = $this->createAdminSettings();

		$settings->menu_group('custom-group')
            ->page('custom-page')
                ->heading('Custom Submit Page')
                ->submit_controls('admin.custom-submit-wrapper')
                    ->button('primary', 'Save Settings')
                        ->order(10)
                    ->field('divider', '', 'components.divider', array(
                        'context' => array('markup' => '<span class="divider">|</span>'),
                        'order'   => 15,
                    ))
                    ->button('reset', 'Reset Form')
                        ->type('reset')
                        ->variant('secondary')
                        ->order(20)
                    ->button('docs-link', 'Documentation')
                        ->type('button')
                        ->variant('link')
                        ->attributes(array(
                            'href'  => 'https://example.com/docs',
                            'class' => 'docs-link',
                        ))
                        ->order(30)
                    ->button('support-link', 'Support')
                        ->type('button')
                        ->variant('link')
                        ->attributes(array(
                            'href'  => 'mailto:support@example.com',
                            'class' => 'support-link',
                        ))
                        ->order(40)
                    ->button('divider-link', '|')
                        ->type('button')
                        ->variant('link')
                        ->attributes(array(
                            'href'  => '#',
                            'class' => 'pipe-link',
                        ))
                        ->order(50)
                ->end_submit_controls();

		$this->setOptionValues(array('example_field' => 'value'));

		ob_start();
		$settings->render('custom-page');
		$output = (string) ob_get_clean();

		self::assertStringContainsString('class="custom-submit-area"', $output, 'Custom submit wrapper not rendered.');
		self::assertStringContainsString('<span class="prefix">|</span>', $output, 'Custom prefix markup missing.');
		self::assertStringContainsString('<span class="divider">|</span>', $output, 'Custom divider component missing.');
		self::assertStringContainsString('<span class="pipe">|</span>', $output, 'Custom suffix markup missing.');
		self::assertStringContainsString('<button type="submit">Save Settings</button>', $output, 'Custom submit button missing.');
		self::assertStringContainsString('<button type="reset">Reset Form</button>', $output, 'Reset button missing.');
		self::assertStringContainsString('<a class="link-control docs-link" href="https://example.com/docs">Documentation</a>', $output, 'Documentation link missing.');
		self::assertStringContainsString('<a class="link-control support-link" href="mailto:support@example.com">Support</a>', $output, 'Support link missing.');
		self::assertStringContainsString('<a class="link-control pipe-link" href="#">|</a>', $output, 'Pipe link missing.');
		self::assertStringNotContainsString('Save Changes', $output, 'Default submit label should not appear when custom controls provided.');
	}

	public function test__render_default_submit_controls_preserves_builder_order(): void {
		$settings = $this->createAdminSettings();

		$settings->menu_group('order-group')
		    ->page('order-page')
		        ->heading('Order Test')
		        ->submit_controls()
		            ->button('later', 'Second Button')->order(20)
		            ->button('sooner', 'First Button')->order(10)
		        ->end_submit_controls()
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array('example_field' => 'value'));

		$property = new ReflectionProperty($settings, 'submit_controls');
		$property->setAccessible(true);
		$storedControls = $property->getValue($settings);
		$controls       = $storedControls['order-page']['controls'] ?? array();
		self::assertSame(array('sooner', 'later'), array_column($controls, 'id'), 'Submit controls storage should be normalized by order.');
		self::assertSame(array(10, 20), array_column($controls, 'order'), 'Submit controls storage should retain declared order values.');

		ob_start();
		$settings->render('order-page');
		$output = (string) ob_get_clean();

		preg_match_all('/<button[^>]*>([^<]+)<\/button>/', $output, $matches);
		self::assertNotEmpty($matches[1] ?? array(), 'Expected rendered button markup.');
		self::assertSame(array('First Button', 'Second Button'), $matches[1], 'Rendered button order should follow normalized storage.');
	}

	public function test__render_default_submit_controls_does_not_mutate_stored_controls(): void {
		$settings = $this->createAdminSettings();

		$settings->menu_group('mutate-group')
		    ->page('mutate-page')
		        ->heading('Mutation Test')
		        ->submit_controls()
		            ->button('alpha', 'Alpha')->order(5)
		            ->button('beta', 'Beta')->order(15)
		        ->end_submit_controls()
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array('example_field' => 'value'));

		$property = new ReflectionProperty($settings, 'submit_controls');
		$property->setAccessible(true);
		$before = $property->getValue($settings);

		ob_start();
		$settings->render('mutate-page');
		ob_end_clean();

		$after = $property->getValue($settings);
		self::assertSame($before, $after, 'Submit controls storage should remain unchanged after rendering.');
	}

	public function test__render_default_submit_controls_does_not_log_additional_normalizations(): void {
		$settings = $this->createAdminSettings();

		$this->resetLogger();

		$settings->menu_group('log-group')
		    ->page('log-page')
		        ->heading('Logging Test')
		        ->submit_controls()
		            ->button('first', 'First')->order(1)
		            ->button('second', 'Second')->order(2)
		        ->end_submit_controls()
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array('example_field' => 'value'));
		$beforeCount = $this->countLogsForMessage('forms.submit_controls.controls.updated');

		ob_start();
		$settings->render('log-page');
		ob_end_clean();

		$afterCount = $this->countLogsForMessage('forms.submit_controls.controls.updated');
		self::assertSame($beforeCount, $afterCount, 'Rendering submit controls should not trigger additional normalization logs.');
	}

	public function test_render_missing_submit_controls_triggers_fallback_once(): void {
		$settings = $this->createAdminSettings();

		$settings->menu_group('missing-fallback-group')
		    ->page('missing-fallback-page')
		        ->heading('Fallback Page')
		    ->end_page()
		->end_menu_group();

		// Simulate missing canonical definition.
		$property = new ReflectionProperty($settings, 'submit_controls');
		$property->setAccessible(true);
		$property->setValue($settings, array());

		$this->setOptionValues(array('example_field' => 'value'));
		$this->resetLogger();

		ob_start();
		$settings->render('missing-fallback-page');
		$output = (string) ob_get_clean();

		self::assertStringContainsString('<button type="submit">Save Changes</button>', $output, 'Fallback should render default submit button.');

		$fallbackLogs = $this->logger->find_logs(static fn(array $entry): bool => ($entry['message'] ?? '') === 'admin_settings.submit_controls.fallback_applied');
		self::assertCount(1, $fallbackLogs, 'Fallback should be logged exactly once.');
		self::assertSame('missing_definition', $fallbackLogs[0]['context']['reason'] ?? null);

		$storedControls = $property->getValue($settings);
		self::assertArrayHasKey('missing-fallback-page', $storedControls, 'Fallback should persist submit controls for subsequent renders.');
		self::assertNotEmpty($storedControls['missing-fallback-page']['controls'] ?? array(), 'Fallback controls should be stored.');
	}

	public function test_render_empty_submit_controls_triggers_fallback_once(): void {
		$settings = $this->createAdminSettings();

		$menuBuilder = $settings->menu_group('empty-fallback-group');
		$pageBuilder = $menuBuilder->page('empty-fallback-page')->heading('Empty Controls Page');
		$pageBuilder->submit_controls()->end_submit_controls();
		$pageBuilder->end_page();
		$menuBuilder->end_menu_group();

		$this->setOptionValues(array('example_field' => 'value'));
		$this->resetLogger();

		ob_start();
		$settings->render('empty-fallback-page');
		$output = (string) ob_get_clean();

		self::assertStringContainsString('<button type="submit">Save Changes</button>', $output, 'Fallback should render default submit button when controls list is empty.');

		$fallbackLogs = $this->logger->find_logs(static fn(array $entry): bool => ($entry['message'] ?? '') === 'admin_settings.submit_controls.fallback_applied');
		self::assertCount(1, $fallbackLogs, 'Fallback should be logged exactly once.');
		self::assertSame('empty_controls', $fallbackLogs[0]['context']['reason'] ?? null);

		$property = new ReflectionProperty($settings, 'submit_controls');
		$property->setAccessible(true);
		$storedControls = $property->getValue($settings);
		self::assertNotEmpty($storedControls['empty-fallback-page']['controls'] ?? array(), 'Fallback controls should be stored after render.');
	}

	private function createAdminSettings(): \Ran\PluginLib\Settings\AdminSettings {
		$options = RegisterOptions::site(self::OPTION_KEY, true, $this->logger);
		$options->register_schema(array(
		    'example_field' => array(
		        'default'  => '',
		        'sanitize' => array(static fn($value): string => is_scalar($value) ? (string) $value : ''),
		        'validate' => array(static fn($value, callable $emitWarning): bool => is_string($value)),
		    ),
		));

		return new \Ran\PluginLib\Settings\AdminSettings($options, $this->manifest, $this->logger);
	}

	private function registerTemplateStubs(): void {
		$this->manifest->register('section', static function (array $context): ComponentRenderResult {
			$title  = htmlspecialchars((string) ($context['title'] ?? ''), ENT_QUOTES);
			$items  = $context['items'] ?? array();
			$markup = '<section class="admin-section"><h2>' . $title . '</h2>';
			foreach ($items as $item) {
				$markup .= $item['rendered'] ?? '';
			}
			$markup .= '</section>';

			return new ComponentRenderResult($markup, component_type: 'layout_wrapper');
		});

		$this->manifest->register('field-wrapper', static function (array $context): ComponentRenderResult {
			$componentHtml = $context['component_html'] ?? '';
			return new ComponentRenderResult('<div class="field-wrapper">' . $componentHtml . '</div>', component_type: ComponentType::LayoutWrapper);
		});

		$this->manifest->register('layout.zone.submit-controls-wrapper', static function (array $context): ComponentRenderResult {
			$content = $context['content'] ?? '';
			$zoneId  = isset($context['zone_id']) ? (string) $context['zone_id'] : '';
			$before  = isset($context['before']) ? (string) $context['before'] : '';
			$after   = isset($context['after'])  ? (string) $context['after']  : '';
			$extra   = isset($context['class']) ? trim((string) $context['class']) : '';

			$classes = array('ran-zone-wrapper', 'ran-zone-wrapper--submit-controls');
			if ($extra !== '') {
				$classes[] = $extra;
			}

			$classAttr  = implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', implode(' ', $classes))));
			$attributes = sprintf(' class="%s"', esc_attr($classAttr));
			if ($zoneId !== '') {
				$attributes .= sprintf(' data-zone="%s"', esc_attr($zoneId));
			}

			$markup = '<div' . $attributes . '>';
			if ($content !== '') {
				$markup .= '<div class="ran-zone-wrapper__inner">';
				if ($before !== '') {
					$markup .= $before;
				}
				$markup .= $content;
				if ($after !== '') {
					$markup .= $after;
				}
				$markup .= '</div>';
			}
			$markup .= '</div>';

			return new ComponentRenderResult($markup, component_type: ComponentType::LayoutWrapper);
		});

		$this->manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars($context['field_id'] ?? 'field', ENT_QUOTES);
			$value   = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult('<input name="' . $fieldId . '" value="' . $value . '" />', component_type: 'input');
		});

		$rootWrapper = static function (array $context): ComponentRenderResult {
			$heading      = htmlspecialchars((string) ($context['heading'] ?? ''), ENT_QUOTES);
			$content      = $context['content'] ?? '';
			$renderSubmit = is_callable($context['render_submit'] ?? null) ? (string) ($context['render_submit']()) : '';

			$markup = '<div class="root-wrapper">'
			    . '<header><h1>' . $heading . '</h1></header>'
			    . '<main class="form-content">' . $content . '</main>'
			    . '<footer class="form-submit-area">' . $renderSubmit . '</footer>'
			    . '</div>';

			return new ComponentRenderResult($markup, component_type: 'layout_wrapper');
		};

		$this->manifest->register('root-wrapper', $rootWrapper);
		$this->manifest->register('admin.root-wrapper', $rootWrapper);

		$this->manifest->register('submit-controls-wrapper', static function (array $context): ComponentRenderResult {
			$zone    = htmlspecialchars((string) ($context['zone_id'] ?? ''), ENT_QUOTES);
			$content = $context['content'] ?? '';

			$markup = '<div class="submit-wrapper" data-zone="' . $zone . '">'
			    . $content
			    . '</div>';

			return new ComponentRenderResult($markup, component_type: 'layout_wrapper');
		});

		$this->manifest->register('admin.custom-submit-wrapper', static function (array $context): ComponentRenderResult {
			$zone    = htmlspecialchars((string) ($context['zone_id'] ?? ''), ENT_QUOTES);
			$content = $context['content'] ?? '';

			$markup = '<div class="custom-submit-area" data-zone="' . $zone . '">'
			    . '<span class="prefix">|</span>'
			    . '<div class="custom-submit-content">' . $content . '</div>'
			    . '<span class="suffix"><span class="pipe">|</span></span>'
			    . '</div>';

			return new ComponentRenderResult($markup, component_type: 'layout_wrapper');
		});

		$this->manifest->register('components.button', static function (array $context): ComponentRenderResult {
			$label      = htmlspecialchars((string) ($context['label'] ?? ''), ENT_QUOTES);
			$type       = htmlspecialchars((string) ($context['type'] ?? 'button'), ENT_QUOTES);
			$variant    = (string) ($context['variant'] ?? 'primary');
			$attributes = $context['attributes'] ?? array();

			if ($variant === 'link') {
				$href  = htmlspecialchars((string) ($attributes['href'] ?? '#'), ENT_QUOTES);
				$class = 'link-control' . (isset($attributes['class']) ? ' ' . htmlspecialchars($attributes['class'], ENT_QUOTES) : '');
				$extra = '';
				foreach ($attributes as $attr => $value) {
					if ($attr === 'href' || $attr === 'class') {
						continue;
					}
					$extra .= ' ' . htmlspecialchars((string) $attr, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
				}

				$markup = '<a class="' . $class . '" href="' . $href . '"' . $extra . '>' . $label . '</a>';
			} else {
				$extra = '';
				foreach ($attributes as $attr => $value) {
					if ($attr === 'class') {
						$extra .= ' class="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
						continue;
					}
					$extra .= ' ' . htmlspecialchars((string) $attr, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
				}

				$markup = '<button type="' . $type . '"' . $extra . '>' . $label . '</button>';
			}

			return new ComponentRenderResult($markup, component_type: 'input');
		});

		$this->manifest->register('components.divider', static function (array $context): ComponentRenderResult {
			$markup = (string) ($context['markup'] ?? '');
			return new ComponentRenderResult($markup, component_type: 'template');
		});
	}

	private function setOptionValues(array $values): void {
		$this->optionStore[self::OPTION_KEY] = $values;
	}

	private function resetLogger(): void {
		$this->logger->collected_logs = array();
	}

	/**
	 * Count logs emitted with a given message on the collecting logger.
	 */
	private function countLogsForMessage(string $message): int {
		return count($this->logger->find_logs(static fn(array $entry): bool => ($entry['message'] ?? '') === $message));
	}
}
