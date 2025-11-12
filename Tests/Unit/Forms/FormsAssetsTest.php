<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\FormsAssets;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\FormsAssets
 */
final class FormsAssetsTest extends TestCase {
	/**
	 * @covers ::ingest
	 * @covers ::scripts
	 * @covers ::styles
	 * @covers ::requires_media
	 * @covers ::has_assets
	 */
	public function test_ingest_collects_assets_and_flags_media_requirement(): void {
		$assets = new FormsAssets();

		$scriptDefinition = ScriptDefinition::from_array(array(
			'handle'    => 'test-script',
			'src'       => 'https://example.com/script.js',
			'deps'      => array('jquery'),
			'version'   => '1.0.0',
			'in_footer' => true,
		));
		$styleDefinition = StyleDefinition::from_array(array(
			'handle'  => 'test-style',
			'src'     => 'https://example.com/style.css',
			'deps'    => array('wp-components'),
			'version' => '1.0.0',
			'media'   => 'all',
		));

		$result = new ComponentRenderResult(
			markup: '<div>Test Component</div>',
			script: $scriptDefinition,
			style: $styleDefinition,
			requires_media: true,
			repeatable: false,
			component_type: 'input'
		);

		$assets->ingest($result);

		$this->assertTrue($assets->requires_media());
		$this->assertTrue($assets->has_assets());

		$this->assertSame(
			array('test-script' => $scriptDefinition),
			$assets->scripts()
		);
		$this->assertSame(
			array('test-style' => $styleDefinition),
			$assets->styles()
		);
	}

	/**
	 * @covers ::has_assets
	 */
	public function test_has_assets_false_when_no_assets_or_media(): void {
		$assets = new FormsAssets();

		$this->assertFalse($assets->has_assets());
		$this->assertSame(array(), $assets->scripts());
		$this->assertSame(array(), $assets->styles());
		$this->assertFalse($assets->requires_media());
	}

	/**
	 * @covers ::ingest
	 * @covers ::scripts
	 * @covers ::styles
	 * @covers ::has_assets
	 */
	public function test_ingest_merges_multiple_components(): void {
		$assets = new FormsAssets();

		$firstScript = ScriptDefinition::from_array(array(
			'handle' => 'first-script',
			'src'    => 'https://example.com/first.js',
		));
		$first = new ComponentRenderResult(
			markup: '<div>First</div>',
			script: $firstScript,
			repeatable: false,
			component_type: 'input'
		);

		$secondStyle = StyleDefinition::from_array(array(
			'handle' => 'second-style',
			'src'    => 'https://example.com/second.css',
		));
		$second = new ComponentRenderResult(
			markup: '<div>Second</div>',
			style: $secondStyle,
			requires_media: true,
			repeatable: false,
			component_type: 'input'
		);

		$assets->ingest($first);
		$assets->ingest($second);

		$this->assertSame(
			array('first-script' => $firstScript),
			$assets->scripts()
		);
		$this->assertSame(
			array('second-style' => $secondStyle),
			$assets->styles()
		);
		$this->assertTrue($assets->requires_media());
		$this->assertTrue($assets->has_assets());
	}
}
