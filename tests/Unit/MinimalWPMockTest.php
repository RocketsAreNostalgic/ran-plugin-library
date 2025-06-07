<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit;

use Mockery;
use WP_Mock;
// RanTestCase is in the global namespace, defined in test_bootstrap.php
// It extends WP_Mock\Tools\TestCase

class MinimalWPMockTest extends \RanTestCase { // Extend global RanTestCase
	// setUp is inherited from \RanTestCase (via WP_Mock\Tools\TestCase)
	// and will call WP_Mock::setUp() automatically.
	// We can override it if we need to do more, but call parent::setUp() then.
	/*
	public function setUp(): void {
	    parent::setUp(); // Important: Call parent's setUp method
	    // Additional test-specific setup
	}
	*/

	// tearDown is also inherited and will call WP_Mock::tearDown()
	// and Mockery::close() automatically.
	/*
	public function tearDown(): void {
	    // Additional test-specific teardown
	    parent::tearDown(); // Important: Call parent's tearDown method
	}
	*/

	public function tearDown(): void {
		Mockery::close(); // Clean up Mockery if used directly for other mocks
		parent::tearDown(); // Call parent tearDown from RanTestCase (which calls WP_Mock::tearDown())
	}

	/**
	 * Test that add_action is mocked.
	 * @test
	 * @covers WP_Mock::expectActionAdded
	 */
	public function test_direct_add_action_is_mocked(): void {
		// Use expectActionAdded to verify the action hook registration.
		WP_Mock::expectActionAdded(
			'minimal_test_hook',
			Mockery::type('callable'),
			20, // priority
			1,  // accepted_args (default for add_action, callback takes 0)
			1   // times (equivalent to once())
		);

		// Directly call the function we are trying to mock
		add_action('minimal_test_hook', function() {
		}, 20);

		// Add a dummy assertion to satisfy PHPUnit, as WP_Mock handles the real assertion.
		$this->assertTrue(true);

		// WP_Mock::assertActionsCalled() could also be used here if preferred,
		// but the Mockery expectation itself will fail if not met.
		// For this diagnostic, the Mockery expectation is sufficient.
	}
}
