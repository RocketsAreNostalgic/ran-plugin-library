<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\TranslationService;
use ReflectionClass;
use WP_Mock;

/**
 * @coversDefaultClass \Ran\PluginLib\Util\TranslationService
 */
final class TranslationServiceTest extends PluginLibTestCase {
	public function test_translate_returns_specific_override_when_available(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array('Hello' => 'Overridden Hello'));

		$service = new TranslationService();

		$this->assertSame('Overridden Hello', $service->translate('Hello'));
	}

	public function test_translate_with_context_uses_domain_override_and_x(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array());

		WP_Mock::onFilter('ran/plugin_lib/translation_domain')
			->with('ran-plugin-lib')
			->reply('custom-domain');

		$this->loadTranslationFunctionStubs();
		$service = new TranslationService();

		$this->assertSame('_x(custom-domain|button label) Submit', $service->translate('Submit', 'button label'));
	}

	public function test_translate_without_context_uses_domain_override_and_default_function(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array());

		WP_Mock::onFilter('ran/plugin_lib/translation_domain')
			->with('ran-plugin-lib')
			->reply('custom-domain');

		$this->loadTranslationFunctionStubs();
		$service = new TranslationService();

		$this->assertSame('__(custom-domain) Save', $service->translate('Save'));
	}

	public function test_translat_plural_prefers_override(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array('One apple' => 'Always Apple'));

		$service = new TranslationService();

		$this->assertSame('Always Apple', $service->translat_plural('One apple', '%d apples', 5));
	}

	public function test_translat_plural_with_context_uses_domain_override(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array());

		WP_Mock::onFilter('ran/plugin_lib/translation_domain')
			->with('ran-plugin-lib')
			->reply('custom-domain');

		$this->loadTranslationFunctionStubs();
		$service = new TranslationService();

		$this->assertSame('_nx(custom-domain|3|fruit count) %d apples', $service->translat_plural('One apple', '%d apples', 3, 'fruit count'));
	}

	public function test_translat_plural_without_context_uses_domain_override(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_overrides')
			->with(array())
			->reply(array());

		WP_Mock::onFilter('ran/plugin_lib/translation_domain')
			->with('ran-plugin-lib')
			->reply('custom-domain');

		$this->loadTranslationFunctionStubs();

		$service = new TranslationService();

		$this->assertSame('_n(custom-domain|2) %d apples', $service->translat_plural('One apple', '%d apples', 2));
	}

	public function test_get_effective_domain_uses_filter(): void {
		WP_Mock::onFilter('ran/plugin_lib/translation_domain')
			->with('ran-plugin-lib')
			->reply('override-domain');

		$service = new TranslationService();

		$this->assertSame('override-domain', $service->get_effective_domain());
	}

	public function test_get_hook_prefix_returns_configured_value(): void {
		$service = new TranslationService('domain', 'custom/prefix');

		$this->assertSame('custom/prefix', $service->getHookPrefix());
	}

	public function test_for_domain_constructs_service_with_valid_domain(): void {
		$service = TranslationService::for_domain('Forms/Validator', 'client-domain');

		$this->assertSame('client-domain', $service->get_effective_domain());
		$this->assertSame('ran/plugin_lib/forms/validator', $service->getHookPrefix());
	}

	/**
	 * @covers ::for_domain
	 */
	public function test_for_domain_rejects_empty_domain(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain cannot be empty.');
		TranslationService::for_domain('');
	}

	/**
	 * @covers ::for_domain
	 */
	public function test_for_domain_rejects_consecutive_slashes(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain cannot contain consecutive slashes.');
		TranslationService::for_domain('foo//bar');
	}

	/**
	 * @covers ::for_domain
	 */
	public function test_for_domain_rejects_invalid_characters(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain can only contain letters, numbers, hyphens, underscores, and forward slashes.');
		TranslationService::for_domain('bad*domain');
	}

	/**
	 * @covers ::for_domain
	 */
	public function test_for_domain_trims_slashes_and_lowercases(): void {
		$service = TranslationService::for_domain('/Forms/Validator/');

		$this->assertSame('ran/plugin_lib/forms/validator', $service->getHookPrefix());
	}

	/**
	 * @covers ::validate_domain
	 */
	public function test_validate_domain_sanitizes_whitespace_and_slashes(): void {
		$this->assertSame('forms/validator', $this->invokeValidateDomain('  /Forms/Validator/  '));
	}

	/**
	 * @covers ::validate_domain
	 */
	public function test_validate_domain_rejects_whitespace_only_domain(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain cannot be empty.');
		$this->invokeValidateDomain("\t  \n");
	}

	/**
	 * @covers ::validate_domain
	 */
	public function test_validate_domain_rejects_invalid_characters_via_reflection(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain can only contain letters, numbers, hyphens, underscores, and forward slashes.');
		$this->invokeValidateDomain('bad*domain');
	}

	/**
	 * @covers ::validate_domain
	 */
	public function test_validate_domain_rejects_consecutive_slashes_via_reflection(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Translation domain cannot contain consecutive slashes.');
		$this->invokeValidateDomain('foo//bar');
	}

	/**
	 * Invoke the private validate_domain helper.
	 */
	private function invokeValidateDomain(string $domain): string {
		$reflection = new ReflectionClass(TranslationService::class);
		$method     = $reflection->getMethod('validate_domain');
		$method->setAccessible(true);

		/** @var string $result */
		$result = $method->invoke(null, $domain);

		return $result;
	}
}
