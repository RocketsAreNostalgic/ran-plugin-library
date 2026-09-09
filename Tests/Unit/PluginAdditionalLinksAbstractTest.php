<?php

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use LogicException;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\PluginAdditionalLinksAbstract;
use WP_Mock;

final class ConcretePluginAdditionalLinksForTesting extends PluginAdditionalLinksAbstract {
}

final class PluginAdditionalLinksAbstractTest extends PluginLibTestCase {
	public function test_init_registers_hooks_from_normalized_basename(): void {
		$subject = new ConcretePluginAdditionalLinksForTesting( $this->config_mock );

		WP_Mock::expectFilterAdded(
			'plugin_action_links_' . $this->mock_plugin_basename,
			array( $subject, 'plugin_action_links_callback' )
		);
		WP_Mock::expectFilterAdded(
			'plugin_row_meta',
			array( $subject, 'plugin_meta_links_callback' ),
			10,
			4
		);

		$this->assertSame( $subject, $subject->init() );
	}

	public function test_init_rejects_config_without_plugin_basename(): void {
		$config = $this->createMock( ConfigInterface::class );
		$config->method( 'get_config' )->willReturn(
			array(
				'Name' => 'Theme-like config without plugin identity',
			)
		);

		$subject = new ConcretePluginAdditionalLinksForTesting( $config );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'requires plugin configuration with a non-empty Basename' );

		$subject->init();
	}

	public function test_meta_callback_uses_normalized_basename_for_plugin_identity(): void {
		$subject = new ConcretePluginAdditionalLinksForTesting( $this->config_mock );
		$meta    = array( '<a href="#">Existing</a>' );

		$this->assertSame(
			$meta,
			$subject->plugin_meta_links_callback( $meta, 'another-plugin/another-plugin.php', array(), 'active' )
		);
		$this->assertSame(
			$meta,
			$subject->plugin_meta_links_callback( $meta, $this->mock_plugin_basename, array(), 'active' )
		);
	}
}
