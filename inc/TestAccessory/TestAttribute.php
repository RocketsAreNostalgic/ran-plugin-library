<?php declare(strict_types=1);

namespace Ran\PluginLib\TestAccessory;

use Ran\PluginLib\AccessoriesAPI\AccessoryManagerBaseInterface;
/**
 * Our test Attribute
 */
final class TestAccessoryAttribute {

	/**
	 * Our constructor.
	 *
	 * @param  string $foo A string.
	 */
	public function __construct(
		public string $foo,
	) {
		echo "<h1>$foo</h1>";
		\wp_die();
	}

	// /**
	// * Undocumented function
	// *
	// * @return void
	// */
	// public function init():void {
	// echo "<h1>$this->foo</h1>";
	// \wp_die();
	// }
}
