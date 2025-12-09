<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Stubs;

use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\WriteContext;

final class AllowAllPolicy implements WritePolicyInterface {
	public function allow(string $op, WriteContext $ctx): bool {
		return true;
	}
}
