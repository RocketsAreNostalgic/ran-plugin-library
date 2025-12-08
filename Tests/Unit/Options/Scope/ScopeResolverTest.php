<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Scope;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Options\Scope\ScopeResolver;
use Ran\PluginLib\Options\Storage\StorageContext;

/**
 * Consolidated unit tests for ScopeResolver covering:
 * - Enum input normalization (site/network)
 * - String input normalization (site/network/blog/user)
 * - Entity requirements and mismatches for blog/user
 * - Unknown/empty scope fallbacks to site
 * - fromString() mapping behavior
 */
final class ScopeResolverTest extends TestCase {
	// --- Enum scope normalization -------------------------------------------------

	/**
	 * Branch: enum OptionScope::Site (ignore entity, return scope=null + empty args).
	 */
	public function test_enum_site_ignores_entity(): void {
		$ctx = ScopeResolver::resolve_to_context(OptionScope::Site, new BlogEntity(99));
		$this->assertSame(OptionScope::Site, $ctx->scope);
	}

	/**
	 * Branch: enum OptionScope::Network (ignore entity, return 'network' + empty args).
	 */
	public function test_enum_network_ignores_entity(): void {
		$ctx = ScopeResolver::resolve_to_context(OptionScope::Network, new UserEntity(10, true, 'option'));
		$this->assertSame(OptionScope::Network, $ctx->scope);
	}

	// --- String scope normalization (site/network) --------------------------------

	/**
	 * Branch: string 'site' (ignore entity, scope=null + empty args).
	 */
	public function test_string_site_ignores_entity(): void {
		$ctx = ScopeResolver::resolve_to_context('site', new UserEntity(2));
		$this->assertSame(OptionScope::Site, $ctx->scope);
	}

	/**
	 * Branch: string 'network' (ignore entity, return 'network' + empty args).
	 */
	public function test_string_network_ignores_entity(): void {
		$ctx = ScopeResolver::resolve_to_context('network', new BlogEntity(1));
		$this->assertSame(OptionScope::Network, $ctx->scope);
	}

	// --- Blog/user entity handling ------------------------------------------------

	/**
	 * Branch: 'blog' requires BlogEntity; returns 'blog' + blog_id.
	 */
	public function test_string_blog_with_entity(): void {
		$entity = new BlogEntity(123);
		$ctx    = ScopeResolver::resolve_to_context('blog', $entity);
		$this->assertSame(OptionScope::Blog, $ctx->scope);
		$this->assertSame(123, $ctx->blog_id);
	}

	/**
	 * Branch: 'user' requires UserEntity; returns 'user' + user args.
	 */
	public function test_string_user_with_entity(): void {
		$entity = new UserEntity(7, false, 'meta');
		$ctx    = ScopeResolver::resolve_to_context('user', $entity);
		$this->assertSame(OptionScope::User, $ctx->scope);
		$this->assertSame(7, $ctx->user_id);
		$this->assertSame('meta', $ctx->user_storage);
		$this->assertFalse($ctx->user_global);
	}

	/**
	 * Branch: 'user' without entity throws.
	 */
	public function test_user_without_entity_throws_typed(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('user', null);
	}

	/**
	 * Branch: 'blog' without entity throws.
	 */
	public function test_blog_without_entity_throws_typed(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('blog', null);
	}

	/**
	 * Branch: 'user' with BlogEntity throws mismatch.
	 */
	public function test_user_with_mismatched_entity_throws_typed(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('user', new BlogEntity(3));
	}

	/**
		* Branch: 'blog' with UserEntity throws mismatch.
		*/
	public function test_blog_with_mismatched_entity_throws_typed(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('blog', new UserEntity(4));
	}

	/**
		* Branch: 'blog' with invalid blog_id (<= 0) throws.
		*/
	public function test_blog_with_invalid_blog_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('blog', new BlogEntity(0));
	}

	/**
		* Branch: 'user' with invalid user_id (<= 0) throws at resolver (line 79).
		* We bypass the UserEntity constructor guard via Reflection to ensure the
		* exception is thrown within ScopeResolver rather than the entity ctor.
		*/
	/**
	* Note:
	* - We intentionally bypass the UserEntity constructor to avoid its own guard
	*   (which would throw for id <= 0) and instead assert that the resolver's
	*   validation (line 79) throws the InvalidArgumentException.
	* - This ensures coverage of the specific branch in ScopeResolver rather than
	*   the entity's constructor validation.
	*/
	public function test_user_with_invalid_user_id_throws_via_reflection(): void {
		// Build UserEntity without running its constructor
		$ref = new \ReflectionClass(UserEntity::class);
		/** @var UserEntity $entity */
		$entity = $ref->newInstanceWithoutConstructor();
		// Initialize readonly properties via Reflection (allowed when not constructed)
		foreach (array('id' => 0, 'global' => false, 'storage' => 'meta') as $prop => $val) {
			$p = $ref->getProperty($prop);
			$p->setAccessible(true);
			$p->setValue($entity, $val);
		}
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve_to_context('user', $entity);
	}

	// --- Unknown/empty scope fallbacks --------------------------------------------

	/**
	 * Branch: unknown scope string -> site fallback (scope=null), ignore entity.
	 */
	public function test_unknown_scope_falls_back_to_site(): void {
		$ctx = ScopeResolver::resolve_to_context('unknown-scope', new BlogEntity(42));
		$this->assertSame(OptionScope::Site, $ctx->scope);
	}

	/**
	 * Branch: empty scope string -> site fallback (scope=null).
	 */
	public function test_empty_scope_string_falls_back_to_site(): void {
		$ctx = ScopeResolver::resolve_to_context('', null);
		$this->assertSame(OptionScope::Site, $ctx->scope);
	}

	// --- fromString mapping --------------------------------------------------------

	/**
	 * Private helper coverage: map strings to OptionScope; unknown -> Site.
	 */
	public function test_from_string_mappings(): void {
		$ref = new \ReflectionClass(ScopeResolver::class);
		$m   = $ref->getMethod('fromString');
		$m->setAccessible(true);
		$this->assertSame(OptionScope::Site, $m->invoke(null, 'site'));
		$this->assertSame(OptionScope::Site, $m->invoke(null, ''));
		$this->assertSame(OptionScope::Site, $m->invoke(null, 'SITE'));
		$this->assertSame(OptionScope::Network, $m->invoke(null, 'network'));
		$this->assertSame(OptionScope::Blog, $m->invoke(null, 'blog'));
		$this->assertSame(OptionScope::User, $m->invoke(null, 'user'));
		$this->assertSame(OptionScope::Site, $m->invoke(null, 'unknown-scope'));
	}
}
