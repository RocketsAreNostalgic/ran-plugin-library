<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;

/**
 * Tests for RegisterOptions dual callback system functionality.
 *
 * Tests the $emitWarning and $emitNotice callback system that allows
 * validators and sanitizers to provide user feedback.
 */
final class RegisterOptionsDualCallbackTest extends PluginLibTestCase {
	protected ?CollectingLogger $logger_mock = null;

	public function setUp(): void {
		parent::setUp();

		// Create logger mock using parent method
		$this->logger_mock = new CollectingLogger(array());

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		// Mock storage to return success
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true)->byDefault();

		// Default allow_persist filters to true so other suites cannot leak vetoes into these tests.
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	/**
	 * Regression: ensure validation warnings raised during an initial stage_option() call
	 * persist even when a subsequent stage_option() (for a different key) is invoked before commit.
	 */
	public function test_validation_warnings_survive_subsequent_staging(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		$options->register_schema(array(
			'bad_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) {
						$emitWarning('Always invalid');
						return false;
					},
				),
			),
			'good_field' => array(
				'default'  => '',
				'validate' => array(
					function($value) {
						return true;
					},
				),
			),
		));

		// First stage invalid value to capture warning.
		$options->stage_option('bad_field', 'invalid');

		// Stage a separate field afterwards (clears message handler today).
		$options->stage_option('good_field', 'valid');

		// Warning should still block persistence; reproduces bug if this unexpectedly passes.
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Commit should fail when prior validation warnings were recorded.');
	}

	public function tearDown(): void {
		// Force garbage collection to clear any lingering object references
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}

		parent::tearDown();
	}

	/**
	 * Test $emitWarning callback creation and execution in validators.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_record_message
	 */
	public function test_emit_warning_callback_creation_and_execution(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with validator that uses $emitWarning callback
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) {
						if (strlen($value) < 3) {
							$emitWarning('Value must be at least 3 characters long');
							return false;
						}
						if (strpos($value, 'invalid') !== false) {
							$emitWarning('Value contains invalid content');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Test with invalid value that should trigger warning
		$options->stage_option('test_field', 'ab'); // Too short

		// Attempt to commit - should fail due to validation
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Commit should fail when validation fails');

		// Verify warning was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertContains('Value must be at least 3 characters long', $messages['test_field']['warnings']);
	}

	/**
	 * Test $emitNotice callback creation and execution in sanitizers.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_record_message
	 */
	public function test_emit_notice_callback_creation_and_execution(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with sanitizer that uses $emitNotice callback
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Value was trimmed of whitespace');
						}
						$uppercase = strtoupper($trimmed);
						if ($uppercase !== $trimmed) {
							$emitNotice('Value was converted to uppercase');
						}
						return $uppercase;
					}
				),
				'validate' => array(
					function($value) {
						return !empty($value); // Simple validation
					}
				)
			)
		));

		// Test with value that needs sanitization
		$options->stage_option('test_field', '  hello world  ');

		// Commit should succeed after sanitization
		$result = $options->commit_merge();
		$this->assertTrue($result, 'Commit should succeed after sanitization');

		// Verify the value was sanitized correctly
		$stored_value = $options->get_option('test_field');
		$this->assertEquals('HELLO WORLD', $stored_value, 'Value should be trimmed and uppercased');

		// Verify notices were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('notices', $messages['test_field']);
		$this->assertContains('Value was trimmed of whitespace', $messages['test_field']['notices']);
		$this->assertContains('Value was converted to uppercase', $messages['test_field']['notices']);
	}

	/**
	 * Test message type classification and storage.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_record_message
	 */
	public function test_message_type_classification_and_storage(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with both sanitizer (notices) and validator (warnings)
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $value);
						if ($cleaned !== $value) {
							$emitNotice('Special characters were removed');
						}
						return $cleaned;
					}
				),
				'validate' => array(
					function($value, callable $emitWarning) {
						if (strlen($value) < 2) {
							$emitWarning('Value is too short');
							return false;
						}
						if (strlen($value) > 50) {
							$emitWarning('Value is too long');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Test with value that triggers both sanitization and validation failure
		$options->stage_option('test_field', 'a!@#'); // Has special chars and will be short after cleaning

		// This should fail validation after sanitization
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Commit should fail due to validation after sanitization');

		// Verify both notices and warnings were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('notices', $messages['test_field']);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertContains('Special characters were removed', $messages['test_field']['notices']);
		$this->assertContains('Value is too short', $messages['test_field']['warnings']);

		// Test with value that only triggers sanitization
		$options->stage_option('test_field', 'hello world!@#'); // Has special chars but will be valid length

		// This should succeed after sanitization
		$result = $options->commit_merge();
		$this->assertTrue($result, 'Commit should succeed after sanitization');

		$stored_value = $options->get_option('test_field');
		$this->assertEquals('hello world', $stored_value, 'Special characters should be removed');

		// Verify only notices were captured this time
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('notices', $messages['test_field']);
		$this->assertContains('Special characters were removed', $messages['test_field']['notices']);
		// Warnings should be empty since validation passed
		$this->assertEmpty($messages['test_field']['warnings']);
	}

	/**
	 * Test dual callback system integration with RegisterOptions validation pipeline.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::stage_option
	 * @covers \Ran\PluginLib\Options\RegisterOptions::commit_replace
	 */
	public function test_dual_callback_integration_with_validation_pipeline(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with multiple validators and sanitizers
		$options->register_schema(array(
			'email_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim(strtolower($value));
						if ($trimmed !== $value) {
							$emitNotice('Email was trimmed and converted to lowercase');
						}
						return $trimmed;
					}
				),
				'validate' => array(
					function($value, callable $emitWarning) {
						if (empty($value)) {
							$emitWarning('Email is required');
							return false;
						}
						return true;
					},
					function($value, callable $emitWarning) {
						if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
							$emitWarning('Email format is invalid');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Test valid email with sanitization needed
		$options->stage_option('email_field', '  TEST@EXAMPLE.COM  ');
		$result = $options->commit_merge();
		$this->assertTrue($result, 'Valid email should commit successfully');
		$this->assertEquals('test@example.com', $options->get_option('email_field'));

		// Verify notice was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('email_field', $messages);
		$this->assertContains('Email was trimmed and converted to lowercase', $messages['email_field']['notices']);

		// Test invalid email format
		$options->stage_option('email_field', 'invalid-email');
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Invalid email should fail validation');

		// Verify warning was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('email_field', $messages);
		$this->assertContains('Email format is invalid', $messages['email_field']['warnings']);

		// Test empty email
		$options->stage_option('email_field', '');
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Empty email should fail validation');

		// Verify warning was captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('email_field', $messages);
		$this->assertContains('Email is required', $messages['email_field']['warnings']);
	}

	/**
	 * Test multiple validators with dual callback system.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_multiple_validators_with_dual_callbacks(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with multiple validators that each use $emitWarning
		$options->register_schema(array(
			'password_field' => array(
				'default'  => '',
				'validate' => array(
					function($value, callable $emitWarning) {
						if (strlen($value) < 8) {
							$emitWarning('Password must be at least 8 characters');
							return false;
						}
						return true;
					},
					function($value, callable $emitWarning) {
						if (!preg_match('/[A-Z]/', $value)) {
							$emitWarning('Password must contain uppercase letter');
							return false;
						}
						return true;
					},
					function($value, callable $emitWarning) {
						if (!preg_match('/[0-9]/', $value)) {
							$emitWarning('Password must contain number');
							return false;
						}
						return true;
					}
				)
			)
		));

		// Test password that fails multiple validators
		$options->stage_option('password_field', 'abc'); // Too short, no uppercase, no number
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Weak password should fail validation');

		// All validators run and accumulate messages (no fail-fast)
		$messages = $options->take_messages();
		$this->assertArrayHasKey('password_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['password_field']);
		$this->assertContains('Password must be at least 8 characters', $messages['password_field']['warnings']);
		$this->assertContains('Password must contain uppercase letter', $messages['password_field']['warnings']);
		$this->assertContains('Password must contain number', $messages['password_field']['warnings']);

		// Test password that passes first but fails second and third validators
		$options->stage_option('password_field', 'abcdefgh'); // Long enough, but no uppercase, no number
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Password without uppercase should fail validation');

		// All remaining validators run and accumulate messages
		$messages = $options->take_messages();
		$this->assertArrayHasKey('password_field', $messages);
		$this->assertContains('Password must contain uppercase letter', $messages['password_field']['warnings']);
		$this->assertContains('Password must contain number', $messages['password_field']['warnings']);

		// Test valid password
		$options->stage_option('password_field', 'Abcdefgh1'); // Long, uppercase, number
		$result = $options->commit_merge();
		$this->assertTrue($result, 'Strong password should pass validation');

		// For successful validation, there should be no messages at all
		$messages = $options->take_messages();
		// When validation succeeds, there might be no messages at all
		if (array_key_exists('password_field', $messages)) {
			$this->assertEmpty($messages['password_field']['warnings']);
		}
	}

	/**
	 * Test multiple sanitizers with dual callback system.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_multiple_sanitizers_with_dual_callbacks(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with multiple sanitizers that each use $emitNotice
		$options->register_schema(array(
			'text_field' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Whitespace was trimmed');
						}
						return $trimmed;
					},
					function($value, callable $emitNotice) {
						$lowercase = strtolower($value);
						if ($lowercase !== $value) {
							$emitNotice('Text was converted to lowercase');
						}
						return $lowercase;
					},
					function($value, callable $emitNotice) {
						$cleaned = preg_replace('/[^a-z0-9\s]/', '', $value);
						if ($cleaned !== $value) {
							$emitNotice('Special characters were removed');
						}
						return $cleaned;
					}
				),
				'validate' => array(
					function($value) {
						return !empty($value);
					}
				)
			)
		));

		// Test value that triggers all sanitizers
		$options->stage_option('text_field', '  HELLO WORLD!@#  ');
		$result = $options->commit_merge();
		$this->assertTrue($result, 'Should succeed after sanitization');

		// Verify all sanitizers ran and value was processed correctly
		$stored_value = $options->get_option('text_field');
		$this->assertEquals('hello world', $stored_value, 'Value should be trimmed, lowercased, and cleaned');

		// Verify all notices were captured
		$messages = $options->take_messages();
		$this->assertArrayHasKey('text_field', $messages);
		$this->assertArrayHasKey('notices', $messages['text_field']);
		$this->assertContains('Whitespace was trimmed', $messages['text_field']['notices']);
		$this->assertContains('Text was converted to lowercase', $messages['text_field']['notices']);
		$this->assertContains('Special characters were removed', $messages['text_field']['notices']);
	}

	/**
	 * Test backward compatibility with old validator signatures.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
	 */
	public function test_backward_compatibility_with_old_validator_signatures(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with old-style validator (no $emitWarning parameter)
		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'validate' => array(
					function($value) {
						return strlen($value) >= 3; // Old signature - just returns bool
					}
				)
			)
		));

		// Test with invalid value
		$options->stage_option('test_field', 'ab'); // Too short
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Should fail validation with old signature');

		// Verify default warning message was recorded
		$messages = $options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertNotEmpty($messages['test_field']['warnings']);
		// Should contain a default validation failure message
		$this->assertStringContainsString('Validation failed', $messages['test_field']['warnings'][0]);
	}

	/**
	 * Test take_messages() method returns structured data.
	 * @covers \Ran\PluginLib\Options\RegisterOptions::take_messages
	 */
	public function test_take_messages_returns_structured_data(): void {
		$options = new RegisterOptions('test_dual_callback', StorageContext::forSite(), true, $this->logger_mock);

		// Register schema with sanitizer and validator that will fail
		$options->register_schema(array(
			'field1' => array(
				'default'  => '',
				'sanitize' => array(
					function($value, callable $emitNotice) {
						$emitNotice('Field1 sanitized');
						return $value;
					}
				),
				'validate' => array(
					function($value, callable $emitWarning) {
						$emitWarning('Field1 validation warning');
						return false;
					}
				)
			)
		));

		// Stage option to trigger messages (only field1 which will fail)
		$options->stage_option('field1', 'test1');
		$result = $options->commit_merge();
		$this->assertFalse($result, 'Should fail validation');

		// Test structured message format
		$messages = $options->take_messages();

		// Should have proper structure
		$this->assertIsArray($messages);
		$this->assertArrayHasKey('field1', $messages);

		// Field should have warnings and notices arrays
		$this->assertArrayHasKey('warnings', $messages['field1']);
		$this->assertArrayHasKey('notices', $messages['field1']);

		// Verify message content
		$this->assertContains('Field1 sanitized', $messages['field1']['notices']);
		$this->assertContains('Field1 validation warning', $messages['field1']['warnings']);
	}
}
