<?php
/**
 * An abstract helper class for inserting user data into the database.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Users;

/**
 * A helper class for inserting users into the database, which doesn't fail silently with an WP_Error failure.
 */
abstract class InsertUserDataAbstract {
	/**
	 * Wrapper for wp_insert_user that doesn't fail silently with WP_Error.
	 *
	 * @param  string $email User's email.
	 * @param  string $first_name User's first name.
	 * @param  string $last_name User's last name.
	 *
	 * @throws \Exception Throws if a WP_Error is encountered or function is missing.
	 * @return int The user ID on success.
	 */
	public static function insert_user( string $email, string $first_name, string $last_name ): int {
		$logger = null; // Optional logger; obtain from application context if available

		if ( $logger && $logger->is_active() ) {
			$logger->debug( "InsertUserDataAbstract: Attempting to insert user. Email: {$email}, First Name: {$first_name}, Last Name: {$last_name}" );
		}

		if ( ! function_exists( 'wp_insert_user' ) ) {
			$error_message = 'InsertUserDataAbstract: wp_insert_user function does not exist. Cannot insert user.';
			if ( $logger && $logger->is_active() ) {
				$logger->error( $error_message );
			}
			throw new \Exception( $error_message );
		}

		$wp_user_args = array(
			'user_email' => strtolower( $email ),
			'user_login' => strtolower( $email ),
			'user_pass'  => null, // WordPress will generate a password.
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		$results = wp_insert_user( $wp_user_args ); // Removed 'true' for WP_Error object on failure by default

		if ( is_wp_error( $results ) ) { // Changed is_a to is_wp_error for clarity
			$error_message = $results->get_error_message();
			if ( $logger && $logger->is_active() ) {
				$logger->error( "InsertUserDataAbstract: Failed to insert user {$email}. Error: {$error_message}" );
			}
			throw new \Exception( \sprintf( 'Could not insert user %s. WordPress Error: %s', $email, $error_message ) );
		}

		if ( $logger && $logger->is_active() ) {
			$logger->info( "InsertUserDataAbstract: Successfully inserted user. Email: {$email}, ID: {$results}" );
		}

		return $results;
	}

	/**
	 * Wrapper for update_user_meta which doesn't fail silently with WP_Error.
	 *
	 * @param  int    $user_id    User ID.
	 * @param  string $meta_key   Metadata key.
	 * @param  mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 *
	 * @return int|false Meta ID if the key didn't exist, true on successful update, false on failure or if the value is the same.
	 * @throws \Exception Throws if 'update_user_meta' is unavailable, or if the result is a WP_Error object.
	 */
	public static function insert_user_metta( int $user_id, string $meta_key, mixed $meta_value ): int|false {
		$logger = null; // Optional logger; obtain from application context if available

		if ( $logger && $logger->is_active() ) {
			$log_value = is_scalar($meta_value) ? (string) $meta_value : (is_array($meta_value) ? json_encode($meta_value) : gettype($meta_value));
			if (mb_strlen($log_value) > 100) { // Avoid overly long log messages for complex meta values
				$log_value = mb_substr($log_value, 0, 100) . '... (truncated)';
			}
			$logger->debug( "InsertUserDataAbstract: Attempting to update user meta. User ID: {$user_id}, Meta Key: {$meta_key}, Value: {$log_value}" );
		}

		if ( ! function_exists( 'update_user_meta' ) ) {
			$error_message = \sprintf( 'InsertUserDataAbstract: update_user_meta function is not available. Cannot update meta for User ID %d, Key: %s', $user_id, $meta_key );
			if ( $logger && $logger->is_active() ) {
				$logger->error( $error_message );
			}
			throw new \Exception( $error_message );
		}

		$results = update_user_meta( $user_id, $meta_key, $meta_value );

		if ( is_object($results) && function_exists('is_wp_error') && is_wp_error( $results ) ) { // Guarded check for WP_Error
			$error_message = $results->get_error_message();
			if ( $logger && $logger->is_active() ) {
				$logger->error( "InsertUserDataAbstract: Failed to update user meta for User ID {$user_id}, Key: {$meta_key}. Error: {$error_message}" );
			}
			throw new \Exception( $error_message );
		}

		// update_user_meta returns true on success, false on failure, or meta_id if key was new.
		if ( $results === false ) {
			if ( $logger && $logger->is_active() ) {
				$logger->warning( "InsertUserDataAbstract: update_user_meta returned false for User ID {$user_id}, Key: {$meta_key}. This might indicate a failure or the value was unchanged." );
			}
		} else {
			if ( $logger && $logger->is_active() ) {
				$logger->info( "InsertUserDataAbstract: Successfully processed user meta for User ID {$user_id}, Key: {$meta_key}. Result: " . ($results === true ? 'true (updated)' : "meta_id {$results} (added)") );
			}
		}
		return $results;
	}
}
