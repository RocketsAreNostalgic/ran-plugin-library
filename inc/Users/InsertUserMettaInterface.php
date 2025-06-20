<?php
/**
 * Interface for the InsertUserMeta Accessory.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Users;

use Exception;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

interface InsertUserMetaInterface extends RegistrableFeatureInterface {
	/**
	 * Accepts an array of user metadata.
	 * TODO: Document shape of user data.
	 *
	 * @param  array<string, mixed> $user_meta An array of user data.
	 */
	public function insertUserMeta( array $user_meta ): bool|Exception;
}
