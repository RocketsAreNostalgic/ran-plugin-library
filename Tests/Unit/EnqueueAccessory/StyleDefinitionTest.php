<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;

final class StyleDefinitionTest extends TestCase {
	public function test_from_array_populates_all_fields(): void {
		$definition = array(
			'handle'     => 'example-style',
			'src'        => array('dev' => 'style.dev.css', 'prod' => 'style.min.css'),
			'deps'       => array('wp-edit-blocks'),
			'version'    => '2024-09-01',
			'condition'  => static fn(): bool => true,
			'attributes' => array('media' => 'screen'),
			'data'       => array('rtl' => 'replace'),
			'inline'     => array(array('content' => '.foo { color: red; }', 'position' => 'after')),
			'hook'       => 'enqueue_block_assets',
			'priority'   => 20,
			'replace'    => true,
			'cache_bust' => false,
			'media'      => 'print',
		);

		$style = StyleDefinition::from_array($definition);

		$this->assertSame('example-style', $style->handle);
		$this->assertSame(array('dev' => 'style.dev.css', 'prod' => 'style.min.css'), $style->src);
		$this->assertSame(array('wp-edit-blocks'), $style->deps);
		$this->assertSame('print', $style->media);
		$this->assertTrue($style->replace);
		$this->assertFalse($style->cache_bust);

		$export = $style->to_array();
		$this->assertSame('example-style', $export['handle']);
		$this->assertSame(array('wp-edit-blocks'), $export['deps']);
		$this->assertSame('print', $export['media']);
	}

	public function test_from_array_defaults_media_to_all(): void {
		$style = StyleDefinition::from_array(array(
			'handle' => 'default-media',
			'src'    => 'style.css',
		));

		$this->assertSame('all', $style->media);
	}

	public function test_from_array_rejects_empty_media(): void {
		$this->expectException(\InvalidArgumentException::class);

		StyleDefinition::from_array(array(
			'handle' => 'invalid-media',
			'src'    => 'style.css',
			'media'  => '',
		));
	}
}
