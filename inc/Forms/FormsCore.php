<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\WPWrappersTrait;

abstract class FormsCore implements FormsInterface {
	use FormsBaseTrait;
	use WPWrappersTrait;
}
