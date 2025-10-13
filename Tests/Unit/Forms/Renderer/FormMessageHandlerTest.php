<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Renderer;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for FormMessageHandler functionality.
 *
 * Tests universal message handling logic including message storage and classification,
 * field-specific message retrieval, pending values management, and edge cases.
 */
final class FormMessageHandlerTest extends PluginLibTestCase {
	protected ?CollectingLogger $logger_mock = null;
	private FormMessageHandler $handler;

	public function setUp(): void {
		parent::setUp();

		// Create logger mock using parent method
		$this->logger_mock = new CollectingLogger(array());

		// Create FormMessageHandler instance
		$this->handler = new FormMessageHandler($this->logger_mock);
	}

	/**
	 * Test message storage and classification with structured data.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_all_messages
	 */
	public function test_set_messages_with_structured_data(): void {
		$messages = array(
			'field1' => array(
				'warnings' => array('Field1 warning 1', 'Field1 warning 2'),
				'notices'  => array('Field1 notice 1')
			),
			'field2' => array(
				'warnings' => array(),
				'notices'  => array('Field2 notice 1', 'Field2 notice 2')
			),
			'field3' => array(
				'warnings' => array('Field3 warning 1'),
				'notices'  => array()
			)
		);

		$this->handler->set_messages($messages);

		$all_messages = $this->handler->get_all_messages();
		$this->assertEquals($messages, $all_messages);
	}

	/**
	 * Test message storage with malformed data.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 */
	public function test_set_messages_with_malformed_data(): void {
		$messages = array(
			'field1' => array(
				'warnings' => array('Warning 1'),
				// Missing notices
			),
			'field2' => array(
				// Missing warnings
				'notices' => array('Notice 1')
			),
			'field3' => array(
				'warnings'  => array('Warning 1'),
				'notices'   => array('Notice 1'),
				'extra_key' => array('Should be ignored')
			)
		);

		$this->handler->set_messages($messages);

		$all_messages = $this->handler->get_all_messages();

		// Should normalize the structure
		$expected = array(
			'field1' => array(
				'warnings' => array('Warning 1'),
				'notices'  => array()
			),
			'field2' => array(
				'warnings' => array(),
				'notices'  => array('Notice 1')
			),
			'field3' => array(
				'warnings' => array('Warning 1'),
				'notices'  => array('Notice 1')
			)
		);

		$this->assertEquals($expected, $all_messages);
	}

	/**
	 * Test field-specific message retrieval.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_messages_for_field
	 */
	public function test_get_messages_for_field(): void {
		$messages = array(
			'test_field' => array(
				'warnings' => array('Test warning'),
				'notices'  => array('Test notice')
			),
			'other_field' => array(
				'warnings' => array('Other warning'),
				'notices'  => array()
			)
		);

		$this->handler->set_messages($messages);

		// Test existing field
		$field_messages = $this->handler->get_messages_for_field('test_field');
		$expected       = array(
			'warnings' => array('Test warning'),
			'notices'  => array('Test notice')
		);
		$this->assertEquals($expected, $field_messages);

		// Test non-existent field
		$empty_messages = $this->handler->get_messages_for_field('nonexistent_field');
		$expected_empty = array(
			'warnings' => array(),
			'notices'  => array()
		);
		$this->assertEquals($expected_empty, $empty_messages);
	}

	/**
	 * Test field ID sanitization.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_messages_for_field
	 */
	public function test_field_id_sanitization(): void {
		$messages = array(
			'valid_field_123' => array(
				'warnings' => array('Valid field warning'),
				'notices'  => array()
			)
		);

		$this->handler->set_messages($messages);

		// Test that sanitized field IDs work
		$field_messages = $this->handler->get_messages_for_field('valid_field_123');
		$this->assertEquals(array('Valid field warning'), $field_messages['warnings']);

		// Test that field IDs with special characters get sanitized
		$this->handler->set_messages(array(
			'field@with#special$chars' => array(
				'warnings' => array('Special chars warning'),
				'notices'  => array()
			)
		));

		// The sanitized version should be accessible
		$all_messages = $this->handler->get_all_messages();
		$this->assertArrayHasKey('fieldwithspecialchars', $all_messages);
	}

	/**
	 * Test pending values management.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_pending_values
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_effective_values
	 */
	public function test_pending_values_management(): void {
		$stored_values = array(
			'field1' => 'stored_value_1',
			'field2' => 'stored_value_2'
		);

		$pending_values = array(
			'field1' => 'pending_value_1',
			'field2' => 'pending_value_2'
		);

		// Initially, should return stored values
		$effective_values = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($stored_values, $effective_values);

		// After setting pending values, should return pending values
		$this->handler->set_pending_values($pending_values);
		$effective_values = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($pending_values, $effective_values);

		// After clearing pending values, should return stored values again
		$this->handler->set_pending_values(null);
		$effective_values = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($stored_values, $effective_values);
	}

	/**
	 * Test validation failure detection.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::has_validation_failures
	 */
	public function test_has_validation_failures(): void {
		// Initially no failures
		$this->assertFalse($this->handler->has_validation_failures());

		// Set messages with only notices - no failures
		$this->handler->set_messages(array(
			'field1' => array(
				'warnings' => array(),
				'notices'  => array('Just a notice')
			)
		));
		$this->assertFalse($this->handler->has_validation_failures());

		// Set messages with warnings - has failures
		$this->handler->set_messages(array(
			'field1' => array(
				'warnings' => array('Validation failed'),
				'notices'  => array('Also a notice')
			)
		));
		$this->assertTrue($this->handler->has_validation_failures());

		// Clear messages - no failures again
		$this->handler->clear();
		$this->assertFalse($this->handler->has_validation_failures());
	}

	/**
	 * Test message count methods.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_warning_count
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_notice_count
	 */
	public function test_message_count_methods(): void {
		$messages = array(
			'field1' => array(
				'warnings' => array('Warning 1', 'Warning 2'),
				'notices'  => array('Notice 1')
			),
			'field2' => array(
				'warnings' => array('Warning 3'),
				'notices'  => array('Notice 2', 'Notice 3', 'Notice 4')
			),
			'field3' => array(
				'warnings' => array(),
				'notices'  => array()
			)
		);

		$this->handler->set_messages($messages);

		$this->assertEquals(3, $this->handler->get_warning_count()); // 2 + 1 + 0
		$this->assertEquals(4, $this->handler->get_notice_count());  // 1 + 3 + 0
	}

	/**
	 * Test adding individual messages.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::add_message
	 */
	public function test_add_message(): void {
		// Add warning message
		$this->handler->add_message('test_field', 'Test warning', 'warning');

		$messages = $this->handler->get_messages_for_field('test_field');
		$this->assertEquals(array('Test warning'), $messages['warnings']);
		$this->assertEquals(array(), $messages['notices']);

		// Add notice message to same field
		$this->handler->add_message('test_field', 'Test notice', 'notice');

		$messages = $this->handler->get_messages_for_field('test_field');
		$this->assertEquals(array('Test warning'), $messages['warnings']);
		$this->assertEquals(array('Test notice'), $messages['notices']);

		// Add another warning
		$this->handler->add_message('test_field', 'Another warning', 'warning');

		$messages = $this->handler->get_messages_for_field('test_field');
		$this->assertEquals(array('Test warning', 'Another warning'), $messages['warnings']);
		$this->assertEquals(array('Test notice'), $messages['notices']);
	}

	/**
	 * Test adding message with invalid type.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::add_message
	 */
	public function test_add_message_invalid_type(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid message type 'invalid'");

		$this->handler->add_message('test_field', 'Test message', 'invalid');
	}

	/**
	 * Test adding empty message.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::add_message
	 */
	public function test_add_empty_message(): void {
		// Empty messages should be ignored
		$this->handler->add_message('test_field', '', 'warning');
		$this->handler->add_message('test_field', '   ', 'notice');

		$messages = $this->handler->get_messages_for_field('test_field');
		$this->assertEquals(array(), $messages['warnings']);
		$this->assertEquals(array(), $messages['notices']);
	}

	/**
	 * Test clear functionality.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::clear
	 */
	public function test_clear(): void {
		// Set up some data
		$this->handler->set_messages(array(
			'field1' => array(
				'warnings' => array('Warning'),
				'notices'  => array('Notice')
			)
		));
		$this->handler->set_pending_values(array('field1' => 'pending_value'));

		// Verify data is there
		$this->assertTrue($this->handler->has_validation_failures());
		$this->assertEquals(1, $this->handler->get_warning_count());
		$this->assertEquals(array('field1' => 'pending_value'), $this->handler->get_effective_values(array()));

		// Clear everything
		$this->handler->clear();

		// Verify everything is cleared
		$this->assertFalse($this->handler->has_validation_failures());
		$this->assertEquals(0, $this->handler->get_warning_count());
		$this->assertEquals(0, $this->handler->get_notice_count());
		$this->assertEquals(array(), $this->handler->get_all_messages());
		$this->assertEquals(array(), $this->handler->get_effective_values(array()));
	}
	/**
	 * Test edge case with empty messages array.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 */
	public function test_set_empty_messages(): void {
		// First set some messages
		$this->handler->set_messages(array(
			'field1' => array(
				'warnings' => array('Warning'),
				'notices'  => array('Notice')
			)
		));

		$this->assertEquals(1, $this->handler->get_warning_count());

		// Then set empty messages
		$this->handler->set_messages(array());

		$this->assertEquals(0, $this->handler->get_warning_count());
		$this->assertEquals(0, $this->handler->get_notice_count());
		$this->assertEquals(array(), $this->handler->get_all_messages());
	}

	/**
	 * Test message handling with complex field scenarios.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_messages_for_field
	 */
	public function test_complex_field_scenarios(): void {
		$messages = array(
			'simple_field' => array(
				'warnings' => array('Simple warning'),
				'notices'  => array()
			),
			'field_with_dots.and.more' => array(
				'warnings' => array(),
				'notices'  => array('Dotted field notice')
			),
			'field-with-hyphens' => array(
				'warnings' => array('Hyphenated warning'),
				'notices'  => array('Hyphenated notice')
			),
			'field_with_123_numbers' => array(
				'warnings' => array(),
				'notices'  => array('Numbered field notice')
			)
		);

		$this->handler->set_messages($messages);

		// Test each field type
		$simple_messages = $this->handler->get_messages_for_field('simple_field');
		$this->assertEquals(array('Simple warning'), $simple_messages['warnings']);

		$dotted_messages = $this->handler->get_messages_for_field('field_with_dots.and.more');
		$this->assertEquals(array('Dotted field notice'), $dotted_messages['notices']);

		$hyphenated_messages = $this->handler->get_messages_for_field('field-with-hyphens');
		$this->assertEquals(array('Hyphenated warning'), $hyphenated_messages['warnings']);
		$this->assertEquals(array('Hyphenated notice'), $hyphenated_messages['notices']);

		$numbered_messages = $this->handler->get_messages_for_field('field_with_123_numbers');
		$this->assertEquals(array('Numbered field notice'), $numbered_messages['notices']);
	}

	/**
	 * Test pending values with different data types.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_pending_values
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_effective_values
	 */
	public function test_pending_values_with_different_types(): void {
		$stored_values = array(
			'string_field' => 'stored_string',
			'int_field'    => 42,
			'bool_field'   => true,
			'array_field'  => array('stored', 'array'),
			'null_field'   => null
		);

		$pending_values = array(
			'string_field' => 'pending_string',
			'int_field'    => 99,
			'bool_field'   => false,
			'array_field'  => array('pending', 'array'),
			'null_field'   => 'not_null_anymore'
		);

		// Test with stored values
		$effective = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($stored_values, $effective);

		// Test with pending values
		$this->handler->set_pending_values($pending_values);
		$effective = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($pending_values, $effective);

		// Verify specific types are preserved
		$this->assertIsString($effective['string_field']);
		$this->assertIsInt($effective['int_field']);
		$this->assertIsBool($effective['bool_field']);
		$this->assertIsArray($effective['array_field']);
		$this->assertIsString($effective['null_field']);
	}

	/**
	 * Test message handler with no logger.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::__construct
	 */
	public function test_handler_without_logger(): void {
		$handler = new FormMessageHandler();

		// Should work without logger
		$handler->add_message('test_field', 'Test message', 'warning');
		$messages = $handler->get_messages_for_field('test_field');

		$this->assertEquals(array('Test message'), $messages['warnings']);
	}

	/**
	 * Test integration scenario with validation failure workflow.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_pending_values
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::has_validation_failures
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_effective_values
	 */
	public function test_validation_failure_workflow(): void {
		// Simulate a form submission with validation failures
		$submitted_values = array(
			'email' => 'invalid-email',
			'age'   => 'not-a-number',
			'name'  => ''
		);

		$stored_values = array(
			'email' => 'old@example.com',
			'age'   => 25,
			'name'  => 'John Doe'
		);

		$validation_messages = array(
			'email' => array(
				'warnings' => array('Invalid email format'),
				'notices'  => array()
			),
			'age' => array(
				'warnings' => array('Age must be a number'),
				'notices'  => array()
			),
			'name' => array(
				'warnings' => array('Name is required'),
				'notices'  => array()
			)
		);

		// Set validation messages and pending values
		$this->handler->set_messages($validation_messages);
		$this->handler->set_pending_values($submitted_values);

		// Should detect validation failures
		$this->assertTrue($this->handler->has_validation_failures());
		$this->assertEquals(3, $this->handler->get_warning_count());

		// Should return pending values for display
		$effective_values = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($submitted_values, $effective_values);

		// Should have messages for each field
		$email_messages = $this->handler->get_messages_for_field('email');
		$this->assertEquals(array('Invalid email format'), $email_messages['warnings']);

		$age_messages = $this->handler->get_messages_for_field('age');
		$this->assertEquals(array('Age must be a number'), $age_messages['warnings']);

		$name_messages = $this->handler->get_messages_for_field('name');
		$this->assertEquals(array('Name is required'), $name_messages['warnings']);
	}

	/**
	 * Test successful form processing workflow.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::set_messages
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::has_validation_failures
	 * @covers \Ran\PluginLib\Forms\Renderer\FormMessageHandler::get_effective_values
	 */
	public function test_successful_processing_workflow(): void {
		$stored_values = array(
			'email' => 'user@example.com',
			'name'  => 'Jane Doe'
		);

		$success_messages = array(
			'email' => array(
				'warnings' => array(),
				'notices'  => array('Email format corrected')
			),
			'name' => array(
				'warnings' => array(),
				'notices'  => array('Name capitalized')
			)
		);

		// Set success messages (no pending values)
		$this->handler->set_messages($success_messages);

		// Should not detect validation failures
		$this->assertFalse($this->handler->has_validation_failures());
		$this->assertEquals(0, $this->handler->get_warning_count());
		$this->assertEquals(2, $this->handler->get_notice_count());

		// Should return stored values (no pending values)
		$effective_values = $this->handler->get_effective_values($stored_values);
		$this->assertEquals($stored_values, $effective_values);

		// Should have notices for each field
		$email_messages = $this->handler->get_messages_for_field('email');
		$this->assertEquals(array('Email format corrected'), $email_messages['notices']);

		$name_messages = $this->handler->get_messages_for_field('name');
		$this->assertEquals(array('Name capitalized'), $name_messages['notices']);
	}
}
