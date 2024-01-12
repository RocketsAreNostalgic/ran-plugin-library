<?php
/**
 * Interface for the InsertUserData Accessory.
 */

declare(strict_types=1);
namespace Ran\PluginLib\Users;

use Exception;
use Ran\PluginLib\FeaturesAPI\RegistrableFeatureInterface;

interface InsertUserDataInterface extends RegistrableFeatureInterface {

	/**
	 * Accepts an array of user data.
	 * TODO: Document shape of user data.
	 *
	 * @param  array $user_data An array of user data.
	 *
	 * @return bool|Exception
	 */
	public function insertUserData( array $user_data):bool|Exception;


}
