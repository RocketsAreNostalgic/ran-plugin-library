<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;

final class ScriptDefinitionTest extends TestCase {
	public function test_from_array_populates_all_fields(): void {
		$definition = array(
			'handle'     => 'example-script',
			'src'        => 'https://cdn.example.com/script.js',
			'deps'       => array('wp-i18n', 'wp-element'),
			'version'    => '1.2.3',
			'condition'  => static fn(): bool => true,
			'attributes' => array('async' => true),
			'data'       => array('foo' => 'bar'),
			'inline'     => array(array('content' => 'console.log("inline");', 'position' => 'before')),
			'hook'       => 'admin_enqueue_scripts',
			'priority'   => 15,
			'replace'    => true,
			'cache_bust' => true,
			'in_footer'  => true,
			'localize'   => array(
				'object_name' => 'ExampleObject',
				'data'        => array('answer' => 42),
			),
		);

		$script = ScriptDefinition::from_array($definition);

		$this->assertSame('example-script', $script->handle);
		$this->assertSame('https://cdn.example.com/script.js', $script->src);
		$this->assertSame(array('wp-i18n', 'wp-element'), $script->deps);
		$this->assertSame('1.2.3', $script->version);
		$this->assertTrue($script->replace);
		$this->assertTrue($script->cache_bust);
		$this->assertTrue($script->in_footer);
		$this->assertSame(array('object_name' => 'ExampleObject', 'data' => array('answer' => 42)), $script->localize);

		$export = $script->to_array();
		$this->assertSame('example-script', $export['handle']);
		$this->assertSame(array('wp-i18n', 'wp-element'), $export['deps']);
		$this->assertSame(true, $export['in_footer']);
		$this->assertSame($definition['localize'], $export['localize']);
	}

	public function test_from_array_localize_defaults_to_empty_array(): void {
		$definition = array(
			'handle' => 'no-localize',
			'src'    => 'script.js',
		);

		$script = ScriptDefinition::from_array($definition);
		$this->assertSame(array(), $script->localize);
	}

	public function test_from_array_rejects_non_array_localize(): void {
		$definition = array(
			'handle'   => 'invalid-localize',
			'src'      => 'script.js',
			'localize' => 'not-an-array',
		);

		$this->expectException(\InvalidArgumentException::class);
		ScriptDefinition::from_array($definition);
	}

	public function test_from_array_rejects_localize_missing_keys(): void {
		$definition = array(
			'handle'   => 'missing-localize',
			'src'      => 'script.js',
			'localize' => array(
				'object_name' => 'MissingData',
			),
		);

		$this->expectException(\InvalidArgumentException::class);
		ScriptDefinition::from_array($definition);
	}
}
