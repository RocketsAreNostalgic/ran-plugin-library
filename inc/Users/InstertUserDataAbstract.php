<?php
/**
 * An abstract helper class for inserting user data into the database.
 *
 *  @package  RanPlugin
 */

declare(strict_types=1);
namespace Ran\PluginLib\Users;

/**
 * A helper class for inserting users into the database, which doesn't fail silently with an WP_Error failure.
 */
abstract class UserInsertData {

	/**
	 * Wrapper for wp_insert_user that doesn't fail silently with WP_Error.
	 *
	 * @param  string $email User's email.
	 * @param  string $first_name User's first name.
	 * @param  string $last_name User's last name.
	 *
	 * @return int
	 * @throws \Exception Throws if a WP_Error is encountered.
	 */
	public static function insert_user( string $email, string $first_name, string $last_name ) : int {
		if ( ! function_exists( 'wp_insert_user' ) ) {
			return -1;
		}

		$wp_user_args = array(
			'user_email' => strtolower( $email ),
			'user_login' => strtolower( $email ),
			'user_pass' => null,
			'first_name' => $first_name,
			'last_name' => $last_name,
		);

		$results = wp_insert_user( $wp_user_args, true );

		if ( is_a( $results, 'WP_Error' ) ) {
			throw new \Exception( \sprintf( 'Could not insert user %s', $email ) );
		}

		return $results;
	}

	/**
	 * Wrapper for update_user_meta which doesn't fail silently with WP_Error.
	 *
	 * @param  @param int    $user_id — User ID.
	 * @param  @param string $meta_key — Metadata key.
	 * @param  mixed         $meta_value — Metadata value. Must be serializable if non-scalar.
	 *
	 * @return int
	 * @throws \Exception Throws if 'update_user_meta' is unavailable, or if the result is a WP_Error object.
	 */
	public static function insert_user_metta( int $user_id, string $meta_key, mixed $meta_value ) : int|false {
		if ( ! function_exists( 'update_user_meta' ) ) {
			throw new \Exception( \sprintf( 'Error updating user meta as the function update_user_meta is not available. User ID $i', $user_id ) );
		}

		$results = update_user_meta( $user_id, $meta_key, $meta_value );

		if ( is_a( $results, 'WP_Error' ) ) {
			throw new \Exception( $results );
		}
		return $results;
	}
}
