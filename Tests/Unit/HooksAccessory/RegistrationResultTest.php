<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\HooksAccessory\HookDefinition;
use Ran\PluginLib\HooksAccessory\RegistrationError;
use Ran\PluginLib\HooksAccessory\RegistrationResult;
use Ran\PluginLib\HooksAccessory\RegistrationStatus;

final class RegistrationResultTest extends TestCase {
	public function test_success_and_counts(): void {
		$h = new HookDefinition('wp_init', 'boot');
		$r = RegistrationResult::success(array($h), array('ctx' => 'x'));
		$this->assertTrue($r->is_success());
		$this->assertFalse($r->is_failure());
		$this->assertSame(1, $r->get_success_count());
		$this->assertSame(0, $r->get_error_count());
		$this->assertSame(1, $r->get_total_count());
		$this->assertGreaterThan(0.0, $r->get_success_rate());
		$this->assertNotEmpty($r->get_summary());
		$arr = $r->to_array();
		$this->assertSame('success', $arr['status']);
		$this->assertSame(1, $arr['success_count']);
		$this->assertSame('success', $r->status->value);
		$this->assertNotEmpty($r->get_summary());
		$this->assertIsArray($r->to_array());
	}

	public function test_partial_success_and_grouping(): void {
		$h1 = new HookDefinition('wp_init', 'boot');
		$e1 = new RegistrationError('wp_init', 'boot', 'fail', RegistrationStatus::WORDPRESS_ERROR);
		$r  = RegistrationResult::partial_success(array($h1), array($e1));
		$this->assertSame(1, $r->get_success_count());
		$this->assertSame(1, $r->get_error_count());
		$grouped = $r->get_errors_by_type();
		$this->assertArrayHasKey('wordpress_error', $grouped);
		$this->assertNotEmpty($r->get_error_messages());
	}

	public function test_failure_merge_and_filters(): void {
		// Ensure file defining RegistrationError is loaded via RegistrationResult autoload
		RegistrationResult::success(array());
		$e1     = new RegistrationError('wp_init', 'boot', 'oops', RegistrationStatus::METHOD_NOT_FOUND);
		$e2     = RegistrationError::from_exception('admin_init', 'admin', new \RuntimeException('x'));
		$r1     = RegistrationResult::failure(RegistrationStatus::FAILED_VALIDATION, array($e1));
		$h2     = new HookDefinition('admin_init', 'admin');
		$r2     = RegistrationResult::partial_success(array($h2), array($e2));
		$merged = $r1->merge($r2);
		$this->assertSame('partial_success', $merged->status->value);
		$this->assertFalse($merged->is_failure());
		$this->assertSame(2, $merged->get_error_count());
		$this->assertCount(1, $merged->get_errors_for('wp_init'));
		$this->assertFalse($merged->was_hook_registered('wp_init', 'boot'));
		$this->assertNotNull($merged->get_first_error());
	}

	public function test_status_helpers(): void {
		$this->assertTrue(RegistrationStatus::SUCCESS->is_success());
		$this->assertFalse(RegistrationStatus::SUCCESS->is_failure());
		$this->assertNotEmpty(RegistrationStatus::SUCCESS->get_description());
	}

	public function test_error_to_array(): void {
		$e   = new RegistrationError('wp_init', 'boot', 'oops', RegistrationStatus::WORDPRESS_ERROR, new \RuntimeException('x'));
		$arr = $e->to_array();
		$this->assertSame('wp_init', $arr['hook_name']);
		$this->assertSame('boot', $arr['callback']);
		$this->assertSame('oops', $arr['error_message']);
		$this->assertSame('wordpress_error', $arr['error_type']);
		$this->assertTrue($arr['has_exception']);
	}

	public function test_success_rate_zero_total_and_summary(): void {
		$r = RegistrationResult::success(array()); // total = 0
		$this->assertSame(100.0, $r->get_success_rate());
		$this->assertNotEmpty($r->get_summary());
	}

	public function test_get_successful_hooks_for_and_was_hook_registered(): void {
		$h1     = new HookDefinition('wp_init', 'boot');
		$h2     = new HookDefinition('admin_init', 'admin');
		$r      = RegistrationResult::success(array($h1, $h2));
		$wpInit = $r->get_successful_hooks_for('wp_init');
		$this->assertCount(1, $wpInit);
		$this->assertTrue($r->was_hook_registered('wp_init', 'boot'));
		$this->assertFalse($r->was_hook_registered('wp_init', 'nonexistent'));
	}
}
