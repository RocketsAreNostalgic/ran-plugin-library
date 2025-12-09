<?php
/**
 * Interface for the InsertUserData Accessory.
 *
 * @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Users;

use Exception;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

interface InsertUserDataInterface extends RegistrableFeatureInterface {
	/**
	 * Accepts an array of user data.
	 *
	 * @todo Document shape of user data.
	 *
	 * @param  array<string, mixed> $user_data An array of user data.
	 */
	public function insert_user_data( array $user_data ): bool|Exception;
}
