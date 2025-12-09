<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Util;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Util\TranslationServiceTrait;

/**
 * @coversDefaultClass \Ran\PluginLib\Util\TranslationServiceTrait
 */
final class TranslationServiceTraitTest extends PluginLibTestCase {
	/**
	 * Create an anonymous object using the trait under test.
	 */
	private function createSubject(): object {
		return new class {
			use TranslationServiceTrait;
		};
	}

	/**
	 * Create a subject that simulates missing core translation functions.
	 */
	private function createSubjectWithoutTranslations(): object {
		return new class {
			use TranslationServiceTrait {
				_do___ as private wrapper_do___;
			}

			protected function _do___(string $text, string $domain = 'default'): string {
				return $this->wrapper_do___($text, $domain);
			}

			private function wrapper_do___(string $text, string $domain = 'default'): string {
				return $text;
			}
		};
	}

	/**
	 * Provide a TranslationService stub with a fixed domain.
	 */
	private function createTranslatorStub(string $domain = 'custom-domain'): TranslationService {
		return new class($domain) extends TranslationService {
			private string $fixedDomain;

			public function __construct(string $fixedDomain) {
				$this->fixedDomain = $fixedDomain;
				parent::__construct($fixedDomain, 'ran/plugin_lib/testing');
			}

			public function get_effective_domain(): string {
				return $this->fixedDomain;
			}
		};
	}

	/**
	 * @covers ::__do__service
	 */
	public function test_do_service_falls_back_when_translation_function_missing(): void {
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubjectWithoutTranslations();

		$this->assertSame('Hello', $subject->__do__service('Hello', $translator));
	}

	/**
	 * @covers ::__do__service
	 */
	public function test_do_service_returns_translated_text_when_function_available(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('__(custom-domain) Hello', $subject->__do__service('Hello', $translator));
	}

	/**
	 * @covers ::__do_x_service
	 */
	public function test_do_x_service_with_context(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('_x(custom-domain|context) Hello', $subject->__do_x_service('Hello', 'context', $translator));
	}

	/**
	 * @covers ::__do_esc_html__service
	 */
	public function test_do_esc_html__service(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('esc_html__(custom-domain) Hello', $subject->__do_esc_html__service('Hello', $translator));
	}

	/**
	 * @covers ::__do_esc_html_x_service
	 */
	public function test_do_esc_html_x_service(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('esc_html_x(custom-domain|ctx) Hello', $subject->__do_esc_html_x_service('Hello', 'ctx', $translator));
	}

	/**
	 * @covers ::__do_esc_attr__service
	 */
	public function test_do_esc_attr__service(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();
		$this->assertTrue(function_exists('esc_attr__'));
		$this->assertSame('esc_attr__(custom-domain) Hello', esc_attr__('Hello', 'custom-domain'));

		$this->assertSame('esc_attr__(custom-domain) Hello', $subject->__do_esc_attr__service('Hello', $translator));
	}

	/**
	 * @covers ::__do_esc_attr_x_service
	 */
	public function test_do_esc_attr_x_service(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('esc_attr_x(custom-domain|ctx) Hello', $subject->__do_esc_attr_x_service('Hello', 'ctx', $translator));
	}

	/**
	 * @covers ::__do_e_service
	 */
	public function test_do_e_service_echoes_translation(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		ob_start();
		$subject->__do_e_service('Echoed', $translator);
		$output = ob_get_clean();

		$this->assertSame('_e(custom-domain) Echoed', $output);
	}

	/**
	 * @covers ::__do_esc_html_e_service
	 */
	public function test_do_esc_html_e_service_echoes(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		ob_start();
		$subject->__do_esc_html_e_service('Echoed', $translator);
		$output = ob_get_clean();

		$this->assertSame('esc_html_e(custom-domain) Echoed', $output);
	}

	/**
	 * @covers ::__do_esc_attr_e_service
	 */
	public function test_do_esc_attr_e_service_echoes(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		ob_start();
		$subject->__do_esc_attr_e_service('Echoed', $translator);
		$output = ob_get_clean();

		$this->assertSame('esc_attr_e(custom-domain) Echoed', $output);
	}

	/**
	 * @covers ::__do_translate_plural_service
	 */
	public function test_do_translate_plural_service(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('_n(custom-domain|5) %d Many', $subject->__do_translate_plural_service('One', '%d Many', 5, $translator));
	}

	/**
	 * @covers ::__do_nx_service
	 */
	public function test_do_nx_service_with_context(): void {
		$this->loadTranslationFunctionStubs();
		$translator = $this->createTranslatorStub();
		$subject    = $this->createSubject();

		$this->assertSame('_nx(custom-domain|3|ctx) %d Many', $subject->__do_nx_service('One', '%d Many', 3, 'ctx', $translator));
	}
}
