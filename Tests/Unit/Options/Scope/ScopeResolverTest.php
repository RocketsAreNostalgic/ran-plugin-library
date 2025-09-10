<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Scope;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\Scope\ScopeResolver;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;

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
		$resolved = ScopeResolver::resolve(OptionScope::Site, new BlogEntity(99));
		$this->assertSame(null, $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
	}

	/**
	 * Branch: enum OptionScope::Network (ignore entity, return 'network' + empty args).
	 */
	public function test_enum_network_ignores_entity(): void {
		$resolved = ScopeResolver::resolve(OptionScope::Network, new UserEntity(10, true, 'option'));
		$this->assertSame('network', $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
	}

	// --- String scope normalization (site/network) --------------------------------

	/**
	 * Branch: string 'site' (ignore entity, scope=null + empty args).
	 */
	public function test_string_site_ignores_entity(): void {
		$resolved = ScopeResolver::resolve('site', new UserEntity(2));
		$this->assertSame(null, $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
	}

	/**
	 * Branch: string 'network' (ignore entity, return 'network' + empty args).
	 */
	public function test_string_network_ignores_entity(): void {
		$resolved = ScopeResolver::resolve('network', new BlogEntity(1));
		$this->assertSame('network', $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
	}

	// --- Blog/user entity handling ------------------------------------------------

	/**
	 * Branch: 'blog' requires BlogEntity; returns 'blog' + blog_id.
	 */
	public function test_string_blog_with_entity(): void {
		$entity   = new BlogEntity(123);
		$resolved = ScopeResolver::resolve('blog', $entity);
		$this->assertSame('blog', $resolved['scope']);
		$this->assertSame(array('blog_id' => 123), $resolved['storage_args']);
	}

	/**
	 * Branch: 'user' requires UserEntity; returns 'user' + user args.
	 */
	public function test_string_user_with_entity(): void {
		$entity   = new UserEntity(7, false, 'meta');
		$resolved = ScopeResolver::resolve('user', $entity);
		$this->assertSame('user', $resolved['scope']);
		$this->assertSame(array(
		    'user_id'      => 7,
		    'user_global'  => false,
		    'user_storage' => 'meta',
		), $resolved['storage_args']);
	}

	/**
	 * Branch: 'user' without entity throws.
	 */
	public function test_user_without_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve('user', null);
	}

	/**
	 * Branch: 'blog' without entity throws.
	 */
	public function test_blog_without_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve('blog', null);
	}

	/**
	 * Branch: 'user' with BlogEntity throws mismatch.
	 */
	public function test_user_with_mismatched_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve('user', new BlogEntity(3));
	}

	/**
	 * Branch: 'blog' with UserEntity throws mismatch.
	 */
	public function test_blog_with_mismatched_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		ScopeResolver::resolve('blog', new UserEntity(4));
	}

	// --- Unknown/empty scope fallbacks --------------------------------------------

	/**
	 * Branch: unknown scope string -> site fallback (scope=null), ignore entity.
	 */
	public function test_unknown_scope_falls_back_to_site(): void {
		$resolved = ScopeResolver::resolve('unknown-scope', new BlogEntity(42));
		$this->assertSame(null, $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
	}

	/**
	 * Branch: empty scope string -> site fallback (scope=null).
	 */
	public function test_empty_scope_string_falls_back_to_site(): void {
		$resolved = ScopeResolver::resolve('', null);
		$this->assertSame(null, $resolved['scope']);
		$this->assertSame(array(), $resolved['storage_args']);
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
