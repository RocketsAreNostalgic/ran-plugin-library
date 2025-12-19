<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Templates;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentLoader;

class RootWrapperRenderSubmitCallbackTest extends PluginLibTestCase {
	private ComponentLoader $loader;

	public function setUp(): void {
		parent::setUp();

		$this->define_constant('ABSPATH', __DIR__);

		WP_Mock::userFunction('esc_html')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);

		$this->loader = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger_mock);
	}

	public function test_render_submit_callback_supports_zero_arg_callable(): void {
		$result = $this->loader->render_payload('layout.container.root-wrapper', array(
			'form_id'       => 'form-1',
			'heading'       => 'Heading',
			'inner_html'    => '<div>Inner</div>',
			'render_submit' => static function (): string {
				return 'SUBMIT_ZERO';
			},
		));

		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('SUBMIT_ZERO', $result->markup);
	}

	public function test_render_submit_callback_receives_canonical_stored_only_ctx(): void {
		$result = $this->loader->render_payload('layout.container.root-wrapper', array(
			'form_id'       => 'form-1',
			'heading'       => 'Heading',
			'inner_html'    => '<div>Inner</div>',
			'field_id'      => 'field-1',
			'container_id'  => 'container-1',
			'root_id'       => 'root-1',
			'section_id'    => 'section-1',
			'group_id'      => 'group-1',
			'value'         => 'value-1',
			'values'        => array('field-1' => 'value-1'),
			'extra_key'     => 'should-not-be-in-ctx',
			'render_submit' => static function (array $ctx): string {
				return implode(',', array_keys($ctx));
			},
		));

		$this->assertInstanceOf(ComponentRenderResult::class, $result);
		$this->assertStringContainsString('field_id,container_id,root_id,section_id,group_id,value,values', $result->markup);
		$this->assertStringNotContainsString('extra_key', $result->markup);
		$this->assertStringNotContainsString('heading', $result->markup);
		$this->assertStringNotContainsString('form_id', $result->markup);
	}
}
