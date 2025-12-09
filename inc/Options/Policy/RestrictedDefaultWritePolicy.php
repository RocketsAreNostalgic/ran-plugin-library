<?php
/**
 * RestrictedDefaultWritePolicy
 *
 * Sensible, restricted default policy: allow persistence only when the current
 * user has appropriate capabilities for the storage scope. Non-filterable and
 * intended to be immutable once injected.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options\Policy;
use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\Policy\AbstractWritePolicy;

final class RestrictedDefaultWritePolicy extends AbstractWritePolicy implements WritePolicyInterface {
	/**
	 * Decide whether to allow the given operation.
	 * - Persistence-oriented ops require appropriate capability by scope when WP caps are available.
	 * - If WordPress capability APIs are unavailable (tests/CLI), _do_current_user_can ALLOWS by default to preserve existing behavior.
	 *
	 * @param string $op
	 * @param WriteContext $ctx Context describing the operation. At minimum includes 'scope' and 'main_option'.
	 * @return bool
	 */
	public function allow(string $op, WriteContext $ctx): bool {
		$scope = strtolower($ctx->scope());

		if ($scope === 'network') {
			return $this->canManageNetwork();
		} elseif ($scope === 'user') {
			$user_id = (int) ($ctx->user_id() ?? 0);
			return $this->canEditUser($user_id);
		} elseif ($scope === 'blog') {
			return $this->canManageOptions();
		} elseif ($scope === 'site') {
			return $this->canManageOptions();
		} else {
			return $this->canManageOptions();
		}
	}
}
