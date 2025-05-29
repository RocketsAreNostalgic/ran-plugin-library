<?php
/**
 * PHP CS Fixer configuration for WordPress coding standards
 *
 * @package ran/plugin-lib
 * @author  Benjamin Rush
 * @license MIT
 */

declare(strict_types = 1);

// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.PHP.IniSet.display_errors_Disallowed
// WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.Security.NonceVerification,
// WordPress.Security.ValidatedSanitizedInput, WordPress.DB.DirectDatabaseQuery, WordPress.FileSystem.DirectSystemOperation
// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = PhpCsFixer\Finder::create()
	->exclude( 'vendor' )
	->exclude( 'node_modules' )
	->in( __DIR__ . '/../' );

$config = new PhpCsFixer\Config();

// Configure parallel processing - auto-detect number of CPU cores.
$parallel_config = ParallelConfigFactory::detect();

return $config
	// Set parallel configuration.
	->setParallelConfig( $parallel_config )
	->setRules(
		array(
			// Basic formatting.
			'line_ending' => true,
			'no_trailing_whitespace' => true,
			'no_trailing_whitespace_in_comment' => true,

			// WordPress string preferences.
			'single_quote' => true,

			// WordPress array formatting.
			'array_syntax' => array( 'syntax' => 'long' ),
			'no_whitespace_before_comma_in_array' => true,
			'whitespace_after_comma_in_array' => true,

			// WordPress spacing.
			'concat_space' => array( 'spacing' => 'one' ),

			// Align equals signs and array arrows like WordPress standards.
			'binary_operator_spaces' => array(
				'default' => 'align_single_space_minimal',
				'operators' => array(
					'=>' => 'align_single_space_minimal',
					'=' => 'align_single_space_minimal',
				),
			),

			// WordPress brace style.
			'braces' => array(
				'position_after_functions_and_oop_constructs' => 'same',
				'position_after_control_structures' => 'same',
				'position_after_anonymous_constructs' => 'same',
			),

			// IMPORTANT: Disable rules that conflict with WordPress standards for HTML/PHP mixed files.
			'method_chaining_indentation' => false,
			'statement_indentation' => false,
			'array_indentation' => false,
			'indentation_type' => false, // Let WordPress standards handle indentation.
		)
	)
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	// Enable caching for faster processing.
	->setUsingCache( true )
	->setCacheFile( '.php-cs-fixer.cache' )
	->setFinder( $finder );
