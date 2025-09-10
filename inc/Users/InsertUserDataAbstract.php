<?php
/**
 * An abstract helper class for inserting user data into the database.
 *
 *  @package  RanPluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Users;

use Ran\PluginLib\Util\Logger;

/**
 * A helper class for inserting users into the database, which doesn't fail silently with an WP_Error failure.
 */
abstract class InsertUserDataAbstract {
	/**
	 * Wrapper for wp_insert_user that doesn't fail silently with WP_Error.
	 *
	 * @param  string       $email      User's email.
	 * @param  string       $first_name User's first name.
	 * @param  string       $last_name  User's last name.
	 * @param  Logger|null  $logger     Optional logger for diagnostics.
	 *
	 * @throws \Exception Throws if a WP_Error is encountered or function is missing.
	 * @return int The user ID on success.
	 */
	public static function insert_user( string $email, string $first_name, string $last_name, ?Logger $logger = null ): int {
		if ( $logger && $logger->is_active() ) {
			$logger->debug( 'InsertUserDataAbstract: Attempting to insert user.', array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			) );
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
				$logger->error( 'InsertUserDataAbstract: Failed to insert user.', array(
					'email' => $email,
					'error' => $error_message,
				) );
			}
			throw new \Exception( \sprintf( 'Could not insert user %s. WordPress Error: %s', $email, $error_message ) );
		}

		if ( $logger && $logger->is_active() ) {
			$logger->info( 'InsertUserDataAbstract: Successfully inserted user.', array(
				'email'   => $email,
				'user_id' => $results,
			) );
		}

		return $results;
	}
}
