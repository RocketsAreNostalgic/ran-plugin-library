<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\HooksAccessory\HookDefinition;

final class HookDefinitionTest extends TestCase {
	public function test_create_from_string(): void {
		$def = HookDefinition::create('wp_init', 'boot', 'action');
		$this->assertSame('wp_init', $def->hook_name);
		$this->assertSame('boot', $def->callback);
		$this->assertSame(10, $def->priority);
		$this->assertSame(1, $def->accepted_args);
		$this->assertSame('action', $def->hook_type);
	}

	public function test_create_from_array_two(): void {
		$def = HookDefinition::create('wp_init', array('boot', 5), 'action');
		$this->assertSame(5, $def->priority);
		$this->assertSame(1, $def->accepted_args);
	}

	public function test_create_from_array_three(): void {
		$def = HookDefinition::create('the_content', array('filter_content', 12, 2), 'filter');
		$this->assertSame('filter', $def->hook_type);
		$this->assertSame(12, $def->priority);
		$this->assertSame(2, $def->accepted_args);
	}

	public function test_create_multiple(): void {
		$defs = HookDefinition::create_multiple(array(
		    'wp_init'    => 'boot',
		    'admin_init' => array('admin', 20, 0),
		), 'action');
		$this->assertCount(2, $defs);
		$this->assertSame('wp_init', $defs[0]->hook_name);
		$this->assertSame('admin_init', $defs[1]->hook_name);
	}

	public function test_validation_errors(): void {
		$this->expectException(\InvalidArgumentException::class);
		HookDefinition::create('wp_init', array('boot', -1, 1), 'action');
		// invalid hook type
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'boot', 10, 1, 'invalid');
		// invalid method name regex
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'bad-method', 10, 1, 'action');
	}

	public function test_is_valid_for_object_and_equals_conflicts(): void {
		$obj = new class() {
			public function boot(): void {
			}
			public function other(): void {
			}
		};
		$a = new HookDefinition('wp_init', 'boot', 10, 1, 'action');
		$b = new HookDefinition('wp_init', 'boot', 10, 1, 'action');
		$c = new HookDefinition('wp_init', 'other', 10, 1, 'action');

		$this->assertTrue($a->is_valid_for_object($obj));
		$this->assertTrue($a->equals($b));
		$this->assertTrue($a->conflicts_with($c));
	}

	public function test_to_string_contains_expected_fields(): void {
		$a = new HookDefinition('wp_init', 'boot', 3, 2, 'action');
		$s = $a->to_string();
		$this->assertStringContainsString('wp_init', $s);
		$this->assertStringContainsString('priority', $s);
		$this->assertStringContainsString('args', $s);
	}

	public function test_to_array_from_array_and_unique_id(): void {
		$a   = new HookDefinition('wp_init', 'boot', 1, 0, 'action');
		$arr = $a->to_array();
		$b   = HookDefinition::from_array($arr);
		$this->assertTrue($a->equals($b));
		$this->assertNotEmpty($a->get_unique_id());
	}

	public function test_create_invalid_definition_type_throws(): void {
		$this->expectException(\TypeError::class);
		// invalid: non-string/array definition
		/** @phpstan-ignore-next-line */
		HookDefinition::create('wp_init', 123, 'action');
	}

	public function test_create_array_callback_not_string_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		/** @phpstan-ignore-next-line */
		HookDefinition::create('wp_init', array(123, 10, 1, 'x'), 'action');
	}

	public function test_create_array_priority_not_int_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		/** @phpstan-ignore-next-line */
		HookDefinition::create('wp_init', array('boot', 'hi', 1, 'x'), 'action');
	}

	public function test_create_array_accepted_args_not_int_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		/** @phpstan-ignore-next-line */
		HookDefinition::create('wp_init', array('boot', 10, 'no', 'x'), 'action');
	}

	public function test_validate_negative_accepted_args(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'boot', 10, -1, 'action');
	}

	public function test_validate_empty_callback(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', '', 10, 1, 'action');
	}

	public function test_validate_empty_hook_name(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('', 'boot', 10, 1, 'action');
	}

	public function test_validate_negative_priority(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'boot', -1, 1, 'action');
	}

	public function test_validate_invalid_hook_type(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'boot', 10, 1, 'invalid');
	}

	public function test_validate_invalid_callback_regex(): void {
		$this->expectException(\InvalidArgumentException::class);
		new HookDefinition('wp_init', 'bad-method', 10, 1, 'action');
	}

	public function test_create_array_fallback_valid(): void {
		// array with more than 3 elements, valid types → fallback branch returns new self
		$def = HookDefinition::create('wp_init', array('boot', 9, 2, 'extra'), 'action');
		$this->assertSame('boot', $def->callback);
		$this->assertSame(9, $def->priority);
		$this->assertSame(2, $def->accepted_args);
		$this->assertSame('action', $def->hook_type);
	}

	public function test_from_array_missing_key(): void {
		$this->expectException(\InvalidArgumentException::class);
		HookDefinition::from_array(array(
		    'hook_name' => 'wp_init',
		    // 'callback' missing
		    'priority'      => 10,
		    'accepted_args' => 1,
		));
	}

	public function test_create_multiple_invalid_key_type(): void {
		$this->expectException(\InvalidArgumentException::class);
		/** @phpstan-ignore-next-line */
		HookDefinition::create_multiple(array(
		    123 => 'boot',
		), 'action');
	}
}
