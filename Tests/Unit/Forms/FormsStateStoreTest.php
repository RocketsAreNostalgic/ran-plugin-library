<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Forms\Services\FormsStateStore;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsStateStore
 */
final class FormsStateStoreTest extends TestCase {
	public function test_get_registered_field_metadata_includes_field_entries_and_group_entries(): void {
		$containers = array();
		$sections   = array();
		$fields     = array(
			'c1' => array(
				's1' => array(
					array(
						'id'                => 'f1',
						'label'             => 'Field 1',
						'component'         => 'components.text',
						'component_context' => array(),
						'order'             => 10,
						'index'             => 0,
						'before'            => null,
						'after'             => null,
					),
					'not-an-array',
				),
			),
		);
		$groups = array(
			'c1' => array(
				's1' => array(
					'g1' => array(
						'group_id' => 'g1',
						'fields'   => array(
							array(
								'id'                => 'gf1',
								'label'             => 'Group Field 1',
								'component'         => 'components.select',
								'component_context' => array(),
								'order'             => 20,
								'index'             => 1,
							),
						),
					),
				),
			),
		);
		$submit_controls = array();

		$store   = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);
		$entries = $store->get_registered_field_metadata();

		self::assertCount(3, $entries);

		self::assertSame('c1', $entries[0]['container_id']);
		self::assertSame('s1', $entries[0]['section_id']);
		self::assertNull($entries[0]['group_id']);
		self::assertSame('f1', $entries[0]['field']['id'] ?? null);

		self::assertSame('c1', $entries[1]['container_id']);
		self::assertSame('s1', $entries[1]['section_id']);
		self::assertNull($entries[1]['group_id']);
		self::assertSame(array(), $entries[1]['field']);

		self::assertSame('c1', $entries[2]['container_id']);
		self::assertSame('s1', $entries[2]['section_id']);
		self::assertSame('g1', $entries[2]['group_id']);
		self::assertSame('gf1', $entries[2]['field']['id'] ?? null);
		self::assertArrayHasKey('group', $entries[2]);
		self::assertSame('g1', $entries[2]['group']['group_id'] ?? null);
	}

	public function test_lookup_component_alias_returns_null_for_empty_id(): void {
		$containers      = array();
		$sections        = array();
		$fields          = array();
		$groups          = array();
		$submit_controls = array();

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertNull($store->lookup_component_alias(''));
	}

	public function test_lookup_component_alias_finds_component_in_fields_groups_and_submit_controls(): void {
		$containers = array();
		$sections   = array();
		$fields     = array(
			'c1' => array(
				's1' => array(
					array('id' => 'f1', 'component' => 'components.text'),
				),
			),
		);
		$groups = array(
			'c1' => array(
				's1' => array(
					'g1' => array(
						'group_id' => 'g1',
						'fields'   => array(
							array('id' => 'gf1', 'component' => 'components.select'),
						),
					),
				),
			),
		);
		$submit_controls = array(
			'c1' => array(
				'controls' => array(
					array('id' => 'sc1', 'component' => 'components.button'),
				),
			),
		);

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertSame('components.text', $store->lookup_component_alias('f1'));
		self::assertSame('components.select', $store->lookup_component_alias('gf1'));
		self::assertSame('components.button', $store->lookup_component_alias('sc1'));
	}

	public function test_lookup_component_alias_returns_null_for_empty_or_non_string_component_values(): void {
		$containers = array();
		$sections   = array();
		$fields     = array(
			'c1' => array(
				's1' => array(
					array('id' => 'f1', 'component' => ''),
					array('id' => 'f2', 'component' => array('not-a-string')),
				),
			),
		);
		$groups          = array();
		$submit_controls = array();

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertNull($store->lookup_component_alias('f1'));
		self::assertNull($store->lookup_component_alias('f2'));
	}

	public function test_store_holds_references_to_state_arrays(): void {
		$containers      = array();
		$sections        = array();
		$fields          = array();
		$groups          = array();
		$submit_controls = array();

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertNull($store->lookup_component_alias('f1'));

		$fields['c1'] = array(
			's1' => array(
				array('id' => 'f1', 'component' => 'components.text'),
			),
		);

		self::assertSame('components.text', $store->lookup_component_alias('f1'));
	}

	public function test_get_fields_map_returns_container_map_or_empty_array(): void {
		$containers      = array();
		$sections        = array();
		$fields          = array(
			'c1' => array(
				's1' => array(
					array('id' => 'f1'),
				),
			),
		);
		$groups          = array();
		$submit_controls = array();

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertSame($fields['c1'], $store->get_fields_map('c1'));
		self::assertSame(array(), $store->get_fields_map('missing'));

		$fields['broken'] = 'not-an-array';
		self::assertSame(array(), $store->get_fields_map('broken'));
	}

	public function test_get_groups_map_returns_container_map_or_empty_array(): void {
		$containers = array();
		$sections   = array();
		$fields     = array();
		$groups     = array(
			'c1' => array(
				's1' => array(
					'g1' => array('group_id' => 'g1', 'fields' => array()),
				),
			),
		);
		$submit_controls = array();

		$store = new FormsStateStore($containers, $sections, $fields, $groups, $submit_controls);

		self::assertSame($groups['c1'], $store->get_groups_map('c1'));
		self::assertSame(array(), $store->get_groups_map('missing'));

		$groups['broken'] = 'not-an-array';
		self::assertSame(array(), $store->get_groups_map('broken'));
	}
}
