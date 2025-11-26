<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Util\Logger;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\FormsService
 */
final class FormsServiceTest extends TestCase {
	public function test_start_session_creates_new_session_with_defaults(): void {
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		/** @var Logger&MockObject $logger */
		$logger = $this->createMock(Logger::class);

		$service = new FormsService($manifest, $logger);

		$formDefaults = array('field-wrapper' => 'custom.field-wrapper');
		$session      = $service->start_session(null, $formDefaults);

		$this->assertInstanceOf(FormsServiceSession::class, $session);
		$this->assertSame($manifest, $session->manifest());
		$this->assertEquals($formDefaults, $session->get_form_defaults());
	}

	public function test_start_session_uses_provided_assets_bucket(): void {
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		/** @var FormsAssets&MockObject $assets */
		$assets = $this->createMock(FormsAssets::class);
		/** @var Logger&MockObject $logger */
		$logger = $this->createMock(Logger::class);

		$service = new FormsService($manifest, $logger);

		$session = $service->start_session($assets);

		$this->assertSame($assets, $session->assets());
	}

	public function test_manifest_accessor_returns_manifest(): void {
		/** @var ComponentManifest&\PHPUnit\Framework\MockObject\MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		/** @var Logger&MockObject $logger */
		$logger = $this->createMock(Logger::class);

		$service = new FormsService($manifest, $logger);

		$this->assertSame($manifest, $service->manifest());
	}

	public function test_take_warnings_delegates_to_manifest(): void {
		/** @var ComponentManifest&\PHPUnit\Framework\MockObject\MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		$manifest->expects($this->once())
			->method('take_warnings')
			->willReturn(array('warning'));
		/** @var Logger&MockObject $logger */
		$logger = $this->createMock(Logger::class);

		$service = new FormsService($manifest, $logger);

		$this->assertSame(array('warning'), $service->take_warnings());
	}
}
