<?php
/**
 * RestrictedDefaultWritePolicy
 *
 * Sensible, restricted default policy: allow persistence only when the current
 * user has appropriate capabilities for the storage scope. Non-filterable and
 * intended to be immutable once injected.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;
use Ran\PluginLib\Util\WPWrappersTrait;

final class RestrictedDefaultWritePolicy implements WritePolicyInterface {
	use WPWrappersTrait;
	/**
	 * Decide whether to allow the given operation.
	 * - Persistence-oriented ops require appropriate capability by scope when WP caps are available.
	 * - If WordPress capability APIs are unavailable (tests/CLI), _do_current_user_can ALLOWS by default to preserve existing behavior.
	 *
	 * @param string $op
	 * @param array{
	 *   scope: string,
	 *   main_option: string,
	 *   options: array,
	 *   blog_id?: int,
	 *   user_id?: int,
	 *   merge_from_db?: bool
	 * } $ctx Context describing the operation. At minimum includes 'scope' and 'main_option'.
	 * @return bool
	 */
	public function allow(string $op, array $ctx): bool {
		$scope = isset($ctx['scope']) ? (string) $ctx['scope'] : '';
		$scope = strtolower($scope);

		if ($scope === 'network') {
			return $this->_do_current_user_can('manage_network_options');
		} elseif ($scope === 'user') {
			$user_id = isset($ctx['user_id']) ? (int) $ctx['user_id'] : 0;
			return $this->_do_current_user_can('edit_user', $user_id);
		} elseif ($scope === 'blog') {
			return $this->_do_current_user_can('manage_options');
		} elseif ($scope === 'site') {
			return $this->_do_current_user_can('manage_options');
		} else {
			return $this->_do_current_user_can('manage_options');
		}
	}
}
