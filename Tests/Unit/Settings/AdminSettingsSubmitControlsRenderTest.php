<?php
/**
 * Submit controls render coverage for AdminSettings.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Forms\Builders\SubmitControlsBuilder;
use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock;
use WP_Mock\Tools\TestCase;

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
		$loader->register('field-wrapper', 'admin/field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/field-wrapper.php');
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

		self::assertStringContainsString('class="submit-wrapper"', $output, 'Default submit wrapper missing.');
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
                    ->layout('stacked')
                    ->button('primary', 'Save Settings', static function (ButtonBuilder $button): void {
                    	$button->order(10);
                    })
                    ->field('divider', '', 'components.divider', array(
                        'context' => array('markup' => '<span class="divider">|</span>'),
                        'order'   => 15,
                    ))
                    ->button('reset', 'Reset Form', static function (ButtonBuilder $button): void {
                    	$button->type('reset')->variant('secondary')->order(20);
                    })
                    ->button('docs-link', 'Documentation', static function (ButtonBuilder $button): void {
                    	$button->type('button')->variant('link')->attributes(array(
                    	    'href'  => 'https://example.com/docs',
                    	    'class' => 'docs-link',
                    	))->order(30);
                    })
                    ->button('support-link', 'Support', static function (ButtonBuilder $button): void {
                    	$button->type('button')->variant('link')->attributes(array(
                    	    'href'  => 'mailto:support@example.com',
                    	    'class' => 'support-link',
                    	))->order(40);
                    })
                    ->button('divider-link', '|', static function (ButtonBuilder $button): void {
                    	$button->type('button')->variant('link')->attributes(array(
                    	    'href'  => '#',
                    	    'class' => 'pipe-link',
                    	))->order(50);
                    })
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
			return new ComponentRenderResult('<div class="field-wrapper">' . $componentHtml . '</div>', component_type: 'field_wrapper');
		});

		$this->manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars($context['field_id'] ?? 'field', ENT_QUOTES);
			$value   = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult('<input name="' . $fieldId . '" value="' . $value . '" />', component_type: 'form_field');
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
			$zone      = htmlspecialchars((string) ($context['zone_id'] ?? ''), ENT_QUOTES);
			$content   = $context['content'] ?? '';
			$alignment = htmlspecialchars((string) ($context['alignment'] ?? 'right'), ENT_QUOTES);
			$layout    = htmlspecialchars((string) ($context['layout'] ?? 'inline'), ENT_QUOTES);

			$markup = '<div class="submit-wrapper" data-zone="' . $zone . '" data-alignment="' . $alignment . '" data-layout="' . $layout . '">'
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

			return new ComponentRenderResult($markup, component_type: 'form_field');
		});

		$this->manifest->register('components.divider', static function (array $context): ComponentRenderResult {
			$markup = (string) ($context['markup'] ?? '');
			return new ComponentRenderResult($markup, component_type: 'template');
		});
	}

	private function setOptionValues(array $values): void {
		$this->optionStore[self::OPTION_KEY] = $values;
	}
}
