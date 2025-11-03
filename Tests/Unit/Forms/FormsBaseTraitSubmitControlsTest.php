<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Tests\Unit\Forms\TestHelpers\TestHarness;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock;

/**
 * @covers \Ran\PluginLib\Forms\FormsBaseTrait
 */
final class FormsBaseTraitSubmitControlsTest extends TestCase {
	use ExpectLogTrait;
	private CollectingLogger $logger;
	private CollectingLogger $logger_mock;
	private TestHarness $subject;

	protected function setUp(): void {
		parent::setUp();

		WP_Mock::setUp();
		WP_Mock::userFunction('get_option')->andReturn(false);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('apply_filters')->andReturnUsing(static fn(string $hook, $value) => $value);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);

		$this->logger      = new CollectingLogger(array('channel' => 'tests'));
		$this->logger_mock = $this->logger;
		$this->subject     = new TestHarness($this->logger);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_submit_controls_zone_update_records_metadata(): void {
		$update = $this->subject->makeUpdateFunction();

		$update('submit_controls_zone', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'primary-controls',
			'before'       => static fn (): string => '<p>before</p>',
			'after'        => static fn (): string => '<p>after</p>',
		));

		$payload = $this->subject->getSubmitControlsForPage('settings-page');

		self::assertSame('primary-controls', $payload['zone_id'] ?? null);
		self::assertIsCallable($payload['before']);
		self::assertIsCallable($payload['after']);
		self::assertSame(array(), $payload['controls']);
	}

	public function test_submit_controls_set_registers_controls(): void {
		$update = $this->subject->makeUpdateFunction();

		$update('submit_controls_zone', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'primary-controls',
		));

		$update('submit_controls_set', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'primary-controls',
			'controls'     => array(
				array(
					'id'                => 'primary',
					'label'             => 'Save',
					'component'         => 'components.button',
					'component_context' => array('type' => 'submit'),
					'order'             => 10,
				),
				array(
					'id'                => 'secondary',
					'label'             => 'Cancel',
					'component'         => 'components.button',
					'component_context' => array('type' => 'button'),
					'order'             => 20,
				),
			),
		));

		$payload  = $this->subject->getSubmitControlsForPage('settings-page');
		$controls = $payload['controls'] ?? array();

		self::assertCount(2, $controls);
		self::assertSame('primary', $controls[0]['id']);
		self::assertSame('secondary', $controls[1]['id']);
	}

	public function test_submit_controls_set_without_zone_logs_warning(): void {
		$update = $this->subject->makeUpdateFunction();

		$update('submit_controls_set', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'missing-zone',
			'controls'     => array(),
		));

		$this->expectLog('warning', 'Submit controls update received without matching zone');
	}

	public function test_submit_controls_set_with_invalid_payload_logs_warning(): void {
		$update = $this->subject->makeUpdateFunction();

		$update('submit_controls_zone', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'primary-controls',
		));

		$update('submit_controls_set', array(
			'container_id' => 'settings-page',
			'zone_id'      => 'primary-controls',
			'controls'     => array(
				array('id' => '', 'component' => 'components.button', 'component_context' => array()),
			),
		));

		$this->expectLog('warning', 'Submit control entry missing required metadata');
	}

	public function test_get_submit_controls_for_page_defaults_to_empty_array(): void {
		self::assertSame(array(), $this->subject->getSubmitControlsForPage('unknown'));
	}
}
