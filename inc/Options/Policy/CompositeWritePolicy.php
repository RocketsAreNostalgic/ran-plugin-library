<?php
declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;

use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * CompositeWritePolicy (AND semantics)
 *
 * Evaluates a sequence of policies with AND semantics: if any policy denies,
 * the composite denies. Only when all policies allow does it allow.
 */
final class CompositeWritePolicy implements WritePolicyInterface {
	/** @var WritePolicyInterface[] */
	private array $policies;

	public function __construct(WritePolicyInterface ...$policies) {
		$this->policies = $policies;
	}

	public function allow(string $op, WriteContext $wc): bool {
		foreach ($this->policies as $policy) {
			if (!$policy->allow($op, $wc)) {
				return false;
			}
		}
		return true;
	}
}
