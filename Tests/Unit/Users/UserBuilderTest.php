<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Users;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;
use Ran\PluginLib\Users\User;
use WP_Mock;

final class UserBuilderTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Default: treat any WP_Error checks as false unless explicitly mocked
		WP_Mock::userFunction('is_wp_error')->andReturn(false)->byDefault();
	}

	public function test_existing_user_fail_policy_throws(): void {
		$existing = (object) array('ID' => 444);
		WP_Mock::userFunction('get_user_by')->with('email', 'fail@example.com')->once()->andReturn($existing);

		$this->expectException(\Exception::class);
		$config  = new TestableConfig();
		$builder = new User($config, null);
		$builder
			->email('fail@example.com')
			->on_exists('fail')
			->create();
	}

	public function test_existing_user_update_profile_updates_role_and_names(): void {
		$existing = (object) array('ID' => 555);
		WP_Mock::userFunction('get_user_by')->with('email', 'update@example.com')->once()->andReturn($existing);
		// Expect role update
		WP_Mock::userFunction('wp_update_user')->once()->andReturn(true);
		// Expect name meta updates
		WP_Mock::userFunction('update_user_meta')->twice()->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);
		$result  = $builder
			->email('update@example.com')
			->name('Ada', 'Lovelace')
			->role('editor')
			->on_exists('update-profile')
			->create();

		$this->assertSame(555, $result->id);
		$this->assertFalse($result->created);
	}

	public function test_existing_user_option_storage_global_true(): void {
		$existing = (object) array('ID' => 777);
		WP_Mock::userFunction('get_user_by')->with('email', 'opt@example.com')->once()->andReturn($existing);
		// Guard per-user option APIs
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('update_user_option')->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);
		$result  = $builder
			->email('opt@example.com')
			->on_exists('attach')
			->user_scope(true, 'option')
			->options(array('theme' => 'dark'))
			->create();

		$this->assertSame(777, $result->id);
		$this->assertFalse($result->created);
	}

	public function test_existing_user_schema_seed_and_flush(): void {
		$existing = (object) array('ID' => 888);
		WP_Mock::userFunction('get_user_by')->with('email', 'seed@example.com')->once()->andReturn($existing);
		// Guard storage paths (meta as default)
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('add_user_meta')->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);
		$schema  = array('welcome' => array('default' => true));
		$result  = $builder
			->email('seed@example.com')
			->on_exists('attach')
			->schema($schema, true, true)
			->create();

		$this->assertSame(888, $result->id);
		$this->assertFalse($result->created);
	}

	public function test_existing_user_with_policy_integration_does_not_error(): void {
		$existing = (object) array('ID' => 889);
		WP_Mock::userFunction('get_user_by')->with('email', 'policy@example.com')->once()->andReturn($existing);
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('add_user_meta')->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);
		$policy  = new \Ran\PluginLib\Tests\Unit\Options\Stubs\AllowAllPolicy();

		$result = $builder
			->email('policy@example.com')
			->on_exists('attach')
			->with_policy($policy)
			->options(array('k' => 'v'))
			->create();

		$this->assertSame(889, $result->id);
		$this->assertFalse($result->created);
	}

	public function test_existing_user_attach_with_schema_and_options(): void {
		$existing = (object) array('ID' => 666);
		WP_Mock::userFunction('get_user_by')->with('email', 'schema@example.com')->once()->andReturn($existing);
		// Guard site option read during options setup
		WP_Mock::userFunction('get_option')->andReturn(array());
		// Guard user meta path reads/writes
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('add_user_meta')->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);
		$schema  = array('theme' => array('default' => 'light'));
		$result  = $builder
			->email('schema@example.com')
			->on_exists('attach')
			->user_scope(false, 'meta')
			->schema($schema, true, false)
			->options(array('theme' => 'dark'))
			->create();

		$this->assertSame(666, $result->id);
		$this->assertFalse($result->created);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function test_create_minimal_user_inserts_and_returns_result(): void {
		// Arrange
		WP_Mock::userFunction('wp_insert_user')->once()->andReturn(101);

		$config  = new TestableConfig();
		$builder = new User($config, null);

		// Act
		$result = $builder
			->email('ada@example.com')
			->create();

		// Assert
		$this->assertInstanceOf(\Ran\PluginLib\Users\UserResult::class, $result);
		$this->assertSame(101, $result->id);
		$this->assertTrue($result->created);
	}

	public function test_existing_user_attach_skips_insert(): void {
		// Arrange
		$existing = (object) array('ID' => 222);
		WP_Mock::userFunction('get_user_by')->with('email', 'grace@example.com')->once()->andReturn($existing);
		// Ensure no insert occurs
		WP_Mock::userFunction('wp_insert_user')->never();

		$config  = new TestableConfig();
		$builder = new User($config, null);

		// Act
		$result = $builder
			->email('grace@example.com')
			->on_exists('attach')
			->create();

		// Assert
		$this->assertInstanceOf(\Ran\PluginLib\Users\UserResult::class, $result);
		$this->assertSame(222, $result->id);
		$this->assertFalse($result->created);
	}

	public function test_existing_user_attach_with_meta_options_succeeds(): void {
		// Arrange existing user
		$existing = (object) array('ID' => 333);
		WP_Mock::userFunction('get_user_by')->with('email', 'linus@example.com')->once()->andReturn($existing);
		// Guard reads/writes for options path
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('add_user_meta')->andReturn(true);

		$config  = new TestableConfig();
		$builder = new User($config, null);

		// Act
		$result = $builder
			->email('linus@example.com')
			->on_exists('attach')
			->user_scope(false, 'meta')
			->options(array('theme' => 'dark'))
			->create();

		// Assert
		$this->assertInstanceOf(\Ran\PluginLib\Users\UserResult::class, $result);
		$this->assertSame(333, $result->id);
		$this->assertFalse($result->created);
	}
}
