<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use InvalidArgumentException;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler
 */
class FormMessageHandlerTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private CollectingLogger $logger;

	public function setUp(): void {
		parent::setUp();
		$this->logger      = new CollectingLogger();
		$this->logger_mock = $this->logger;
	}

	private function createHandler(?CollectingLogger $logger = null): FormMessageHandler {
		return new FormMessageHandler($logger);
	}

	public function test_set_messages_sanitizes_field_ids_and_provides_defaults(): void {
		$handler = $this->createHandler($this->logger);

		$handler->set_messages(array(
			' field-one! ' => array(
				'warnings' => array('First warning'),
			),
			'field-two' => array(
				'notices' => array('Only notice'),
			),
		));

		$this->assertSame(
			array('warnings' => array('First warning'), 'notices' => array()),
			$handler->get_messages_for_field('field-one')
		);

		$this->assertSame(
			array('warnings' => array(), 'notices' => array('Only notice')),
			$handler->get_messages_for_field('field-two')
		);

		$this->assertSame(
			array('warnings' => array(), 'notices' => array()),
			$handler->get_messages_for_field('missing-field')
		);

		$this->expectLog('debug', 'FormMessageHandler: Messages set');
	}

	public function test_remove_messages_clears_specific_fields_only(): void {
		$handler = $this->createHandler($this->logger);

		$handler->set_messages(array(
			'alpha' => array('warnings' => array('a'), 'notices' => array()),
			'beta'  => array('warnings' => array('b1', 'b2'), 'notices' => array()),
		));

		$handler->remove_messages(array('alpha'));

		$this->assertSame(array(), $handler->get_messages_for_field('alpha')['warnings']);
		$this->assertSame(array('b1', 'b2'), $handler->get_messages_for_field('beta')['warnings']);
		$this->expectLog('debug', 'FormMessageHandler: Messages removed for fields');
	}

	public function test_remove_messages_ignores_unknown_fields_and_empty_input(): void {
		$handler = $this->createHandler($this->logger);

		$handler->set_messages(array(
			'alpha' => array('warnings' => array('a'), 'notices' => array()),
		));

		$handler->remove_messages(array());
		$this->assertSame(array('a'), $handler->get_messages_for_field('alpha')['warnings']);

		$handler->remove_messages(array('missing'));
		$this->assertSame(array('a'), $handler->get_messages_for_field('alpha')['warnings']);
	}

	public function test_set_pending_values_and_get_effective_values(): void {
		$handler = $this->createHandler($this->logger);

		$stored  = array('setting' => 'stored-value');
		$pending = array('setting' => 'pending-value');

		$handler->set_pending_values($pending);
		$this->assertSame($pending, $handler->get_effective_values($stored));

		$handler->set_pending_values(null);
		$this->assertSame($stored, $handler->get_effective_values($stored));
		$this->expectLog('debug', 'FormMessageHandler: Pending values set', 2);
	}

	public function test_has_validation_failures_and_counts_respect_messages(): void {
		$handler = $this->createHandler($this->logger);

		$handler->set_messages(array(
			'alpha' => array(
				'warnings' => array('alpha warning'),
				'notices'  => array('alpha notice'),
			),
			'beta' => array(
				'warnings' => array('beta warning 1', 'beta warning 2'),
			),
		));

		$this->assertTrue($handler->has_validation_failures());
		$this->assertSame(3, $handler->get_warning_count());
		$this->assertSame(1, $handler->get_notice_count());
		$this->expectLog('debug', 'FormMessageHandler: Messages set');
	}

	public function test_add_message_handles_types_and_ignores_blank_values(): void {
		$handler = $this->createHandler($this->logger);

		$handler->add_message('test-field', 'Warning message');
		$handler->add_message('test-field', 'Notice message', 'notice');
		$handler->add_message('test-field', '   '); // ignored

		$this->assertSame(
			array(
				'warnings' => array('Warning message'),
				'notices'  => array('Notice message'),
			),
			$handler->get_messages_for_field('test-field')
		);
		$this->expectLog('debug', 'FormMessageHandler: Message added', 2);
	}

	public function test_add_message_rejects_invalid_type(): void {
		$handler = $this->createHandler($this->logger);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("FormMessageHandler: Invalid message type 'info'");

		$handler->add_message('field', 'message', 'info');
	}

	public function test_get_all_messages_and_clear(): void {
		$handler = $this->createHandler($this->logger);
		$handler->set_messages(array(
			'id' => array('warnings' => array('warn'), 'notices' => array('note')),
		));
		$handler->set_pending_values(array('demo' => 'value'));

		$this->assertSame(
			array(
				'id' => array(
					'warnings' => array('warn'),
					'notices'  => array('note'),
				),
			),
			$handler->get_all_messages()
		);

		$handler->clear();

		$this->assertSame(array(), $handler->get_all_messages());
		$this->assertFalse($handler->has_validation_failures());
		$this->assertSame(
			array('stored' => 'value'),
			$handler->get_effective_values(array('stored' => 'value'))
		);
		$this->expectLog('debug', 'FormMessageHandler: Messages set');
		$this->expectLog('debug', 'FormMessageHandler: Pending values set');
		$this->expectLog('debug', 'FormMessageHandler: All messages and pending values cleared');
	}
}
