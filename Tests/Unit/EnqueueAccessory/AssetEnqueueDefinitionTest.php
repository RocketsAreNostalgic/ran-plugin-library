<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition;

/**
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition::__construct
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition::to_array
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition::export_base_fields
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition::get_specific_fields
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueDefinition::parse_base_fields
 */
final class AssetEnqueueDefinitionTest extends TestCase {
	public function test_get_specific_fields_default_returns_empty_array(): void {
		$definition = new BareDefinition(
			'bare-handle',
			'asset.js',
			array(),
			null,
			null,
			array(),
			array(),
			array(),
			null,
			10,
			false,
			false
		);

		$this->assertSame(
			array(
				'handle'     => 'bare-handle',
				'src'        => 'asset.js',
				'deps'       => array(),
				'version'    => null,
				'condition'  => null,
				'attributes' => array(),
				'data'       => array(),
				'inline'     => array(),
				'hook'       => null,
				'priority'   => 10,
				'replace'    => false,
				'cache_bust' => false,
			),
			$definition->to_array()
		);
	}

	public function test_to_array_merges_base_and_specific_fields(): void {
		$definition = TestableDefinition::from_array(array(
			'handle'     => 'example-handle',
			'src'        => 'script.js',
			'deps'       => array('jquery'),
			'version'    => '1.0.0',
			'condition'  => static fn(): bool => true,
			'attributes' => array('async' => true),
			'data'       => array('foo' => 'bar'),
			'inline'     => array(array('content' => 'console.log("hi");')),
			'hook'       => 'wp_footer',
			'priority'   => 5,
			'replace'    => true,
			'cache_bust' => true,
			'path'       => '/path/to/file',
			'flag'       => true,
		));

		$result = $definition->to_array();

		$this->assertSame('example-handle', $result['handle']);
		$this->assertSame('script.js', $result['src']);
		$this->assertSame(array('jquery'), $result['deps']);
		$this->assertSame('1.0.0', $result['version']);
		$this->assertSame('wp_footer', $result['hook']);
		$this->assertSame(5, $result['priority']);
		$this->assertTrue($result['replace']);
		$this->assertTrue($result['cache_bust']);
		$this->assertSame('/path/to/file', $result['path']);
		$this->assertTrue($result['flag']);
	}
	/**
	 * @dataProvider provideInvalidConstructorInputs
	 */
	public function test_constructor_validation_errors(callable $factory, string $expectedMessage): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);

		$factory();
	}

	/**
	 * @return array<string, array{callable, string}>
	 */
	public function provideInvalidConstructorInputs(): array {
		return array(
			'empty handle' => array(
				static function(): void {
					new TestableDefinition(
						'',
						'script.js',
						array(),
						null,
						null,
						array(),
						array(),
						array(),
						null,
						10,
						false,
						false,
						'/path',
						true
					);
				},
				'Asset handle must be a non-empty string.',
			),
			'invalid src' => array(
				static function(): void {
					new TestableDefinition(
						'bad-asset',
						array('good', 123),
						array(),
						null,
						null,
						array(),
						array(),
						array(),
						null,
						10,
						false,
						false,
						'/path',
						true
					);
				},
				"Asset 'bad-asset' must define 'src' as string, array of strings, or false.",
			),
			'invalid deps' => array(
				static function(): void {
					new TestableDefinition(
						'bad-asset',
						'script.js',
						array('dep', 123),
						null,
						null,
						array(),
						array(),
						array(),
						null,
						10,
						false,
						false,
						'/path',
						true
					);
				},
				"Asset 'bad-asset' dependencies must be an array of strings.",
			),
			'invalid condition' => array(
				static function(): void {
					new TestableDefinition(
						'bad-asset',
						'script.js',
						array(),
						null,
						'not-callable',
						array(),
						array(),
						array(),
						null,
						10,
						false,
						false,
						'/path',
						true
					);
				},
				"Asset 'bad-asset' condition must be a callable or null.",
			),
			'invalid inline' => array(
				static function(): void {
					new TestableDefinition(
						'bad-asset',
						'script.js',
						array(),
						null,
						null,
						array(),
						array(),
						array('string'),
						null,
						10,
						false,
						false,
						'/path',
						true
					);
				},
				"Asset 'bad-asset' inline entries must be arrays.",
			),
		);
	}

	/**
	 * @dataProvider provideInvalidParseInputs
	 */
	public function test_parse_base_fields_validation(array $input, string $expectedMessage): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);

		TestableDefinition::invokeParseBaseFields($input);
	}

	/**
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public function provideInvalidParseInputs(): array {
		return array(
			'missing handle' => array(
				array(),
				"Asset definition must include a non-empty 'handle'.",
			),
			'missing src' => array(
				array('handle' => 'needs-src'),
				"Asset definition for 'needs-src' must include a 'src' key (string, array, or false).",
			),
			'invalid src type' => array(
				array('handle' => 'bad-src', 'src' => 123),
				"Asset definition for 'bad-src' must provide 'src' as string, array of strings, or false.",
			),
			'invalid deps' => array(
				array('handle' => 'bad-deps', 'src' => 'file.js', 'deps' => array('ok', 123)),
				"Asset definition for 'bad-deps' must provide 'deps' as an array of strings.",
			),
			'invalid version' => array(
				array('handle' => 'bad-version', 'src' => 'file.js', 'version' => array()),
				"Asset definition for 'bad-version' must provide 'version' as string, false, or null.",
			),
			'invalid condition' => array(
				array('handle' => 'bad-condition', 'src' => 'file.js', 'condition' => 'nope'),
				"Asset definition for 'bad-condition' must provide 'condition' as a callable or null.",
			),
			'invalid attributes' => array(
				array('handle' => 'bad-attributes', 'src' => 'file.js', 'attributes' => 'nope'),
				"Asset definition for 'bad-attributes' must provide 'attributes' as an array.",
			),
			'invalid data' => array(
				array('handle' => 'bad-data', 'src' => 'file.js', 'data' => 'nope'),
				"Asset definition for 'bad-data' must provide 'data' as an array.",
			),
			'invalid hook' => array(
				array('handle' => 'bad-hook', 'src' => 'file.js', 'hook' => array()),
				"Asset definition for 'bad-hook' must provide 'hook' as a string or null.",
			),
			'invalid inline entry' => array(
				array('handle' => 'bad-inline', 'src' => 'file.js', 'inline' => array(array('content' => 'ok'), 123)),
				"Inline definition for asset 'bad-inline' must be an array, string, or callable.",
			),
		);
	}

	public function test_parse_base_fields_returns_normalized_tuple(): void {
		$result = TestableDefinition::invokeParseBaseFields(array(
			'handle'     => 'normalized',
			'src'        => array('dev' => 'dev.js', 'prod' => 'prod.js'),
			'deps'       => array('a', 'b'),
			'version'    => '1.2.3',
			'condition'  => null,
			'attributes' => array('async' => true),
			'data'       => array('foo' => 'bar'),
			'inline'     => 'console.log("inline")',
			'hook'       => 'enqueue',
			'priority'   => '7',
			'replace'    => 1,
			'cache_bust' => 0,
		));

		$this->assertSame('normalized', $result[0]);
		$this->assertSame(array('dev' => 'dev.js', 'prod' => 'prod.js'), $result[1]);
		$this->assertSame(array('a', 'b'), $result[2]);
		$this->assertSame('1.2.3', $result[3]);
		$this->assertNull($result[4]);
		$this->assertSame(array('async' => true), $result[5]);
		$this->assertSame(array('foo' => 'bar'), $result[6]);
		$this->assertSame(array(array('content' => 'console.log("inline")')), $result[7]);
		$this->assertSame('enqueue', $result[8]);
		$this->assertSame(7, $result[9]);
		$this->assertTrue($result[10]);
		$this->assertFalse($result[11]);
	}

	public function test_parse_base_fields_normalizes_non_list_inline_array(): void {
		$result = TestableDefinition::invokeParseBaseFields(array(
			'handle' => 'assoc-inline',
			'src'    => 'file.js',
			'inline' => array('content' => 'first', 'position' => 'after'),
		));

		$inline = $result[7];
		$this->assertCount(1, $inline);
		$this->assertSame(array('content' => 'first', 'position' => 'after'), $inline[0]);
	}

	public function test_parse_base_fields_converts_scalar_inline_entries(): void {
		$callable = static fn(): string => 'inline';

		$result = TestableDefinition::invokeParseBaseFields(array(
			'handle' => 'scalar-inline',
			'src'    => 'file.js',
			'inline' => array('console.log("inline");', $callable),
		));

		$inline = $result[7];
		$this->assertCount(2, $inline);
		$this->assertSame('console.log("inline");', $inline[0]['content']);
		$this->assertSame($callable, $inline[1]['content']);
	}
}

final readonly class BareDefinition extends AssetEnqueueDefinition {
	public function __construct(
		string $handle,
		string|array|false $src,
		array $deps,
		string|false|null $version,
		mixed $condition,
		array $attributes,
		array $data,
		array $inline,
		?string $hook,
		int $priority,
		bool $replace,
		bool $cache_bust
	) {
		parent::__construct($handle, $src, $deps, $version, $condition, $attributes, $data, $inline, $hook, $priority, $replace, $cache_bust);
	}

	public static function from_array(array $definition): static {
		list(
			$handle,
			$src,
			$deps,
			$version,
			$condition,
			$attributes,
			$data,
			$inline,
			$hook,
			$priority,
			$replace,
			$cacheBust
		) = self::parse_base_fields($definition);

		return new static(
			$handle,
			$src,
			$deps,
			$version,
			$condition,
			$attributes,
			$data,
			$inline,
			$hook,
			$priority,
			$replace,
			$cacheBust
		);
	}
}

final readonly class TestableDefinition extends AssetEnqueueDefinition {
	public function __construct(
		string $handle,
		string|array|false $src,
		array $deps,
		string|false|null $version,
		mixed $condition,
		array $attributes,
		array $data,
		array $inline,
		?string $hook,
		int $priority,
		bool $replace,
		bool $cache_bust,
		public string $path,
		public bool $flag
	) {
		parent::__construct($handle, $src, $deps, $version, $condition, $attributes, $data, $inline, $hook, $priority, $replace, $cache_bust);
	}

	public static function from_array(array $definition): static {
		list(
			$handle,
			$src,
			$deps,
			$version,
			$condition,
			$attributes,
			$data,
			$inline,
			$hook,
			$priority,
			$replace,
			$cacheBust
		) = self::parse_base_fields($definition);

		return new static(
			$handle,
			$src,
			$deps,
			$version,
			$condition,
			$attributes,
			$data,
			$inline,
			$hook,
			$priority,
			$replace,
			$cacheBust,
			$definition['path'] ?? '',
			(bool) ($definition['flag'] ?? false)
		);
	}

	public static function invokeParseBaseFields(array $definition): array {
		return self::parse_base_fields($definition);
	}

	protected function get_specific_fields(): array {
		return array(
			'path' => $this->path,
			'flag' => $this->flag,
		);
	}
}
