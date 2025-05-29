<?php
/**
 * PHP_CodeSniffer Runner Script.
 *
 * This script processes a source PHPCS ruleset, modifies it for a specific run (e.g., making paths absolute),
 * generates a runner-specific ruleset, and then executes PHP_CodeSniffer with the generated ruleset.
 *
 * @package   Ran\PluginLib\Scripts
 * @author    Your Name or Company
 * @license   GPL-2.0-or-later
 * @link      https://example.com
 * @since     1.0.0
 */

declare(strict_types = 1);

error_reporting( E_ALL );
ini_set( 'display_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.PHP.IniSet.display_errors_Disallowed, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.DB.DirectDatabaseQuery, WordPress.FileSystem.DirectSystemOperation
// phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.UselessAnnotation
/**
 * Configuration class for the PhpcsRunner.
 *
 * Stores paths and settings used by the runner script.
 */
class PhpcsRunnerConfig {

	/**
	 * Path to the project root directory.
	 *
	 * @var string
	 */
	public string $project_root;


	/**
	 * Path to the vendor directory.
	 *
	 * @var string
	 */
	public string $vendor_dir;


	/**
	 * Path to the PHPCS executable.
	 *
	 * @var string
	 */
	public string $phpcs_executable;

	/**
	 * Path to the PHPCBF executable.
	 *
	 * @var string
	 */
	public string $phpcbf_executable;

	/**
	 * Path to the source PHPCS ruleset file.
	 *
	 * @var string
	 */
	public string $source_ruleset_path;

	/**
	 * Path to the generated runner ruleset file.
	 *
	 * @var string
	 */
	public string $generated_runner_ruleset_path;

	/**
	 * Constructor for PhpcsRunnerConfig.
	 *
	 * Initializes paths based on the script's location.
	 */
	public function __construct() {
		$this->project_root = dirname( __DIR__ ); // Assumes script is in a subdirectory like /scripts.
		$this->vendor_dir = $this->project_root . '/vendor';
		$this->phpcs_executable = $this->vendor_dir . '/bin/phpcs';
		$this->phpcbf_executable = $this->vendor_dir . '/bin/phpcbf';
		$this->source_ruleset_path = $this->project_root . '/.phpcs.xml';
		$this->generated_runner_ruleset_path = $this->project_root . '/.phpcs-runner.xml';
	}
}

/**
 * Main class to handle PHPCS runner generation and execution.
 */
class PhpcsRunner {

	/**
	 * Configuration object for the runner.
	 *
	 * @var PhpcsRunnerConfig
	 */
	private PhpcsRunnerConfig $config;

	/**
	 * Whether the installed paths have been processed.
	 *
	 * @var bool
	 */
	private bool $processed_installed_paths = false;


	/**
	 * Parsed command-line arguments.
	 *
	 * Structure:
	 * - 'is_fix_mode' (bool): Whether --fix mode is enabled.
	 * - 'phpcs_args' (list<string>): Other arguments to pass to PHPCS/PHPCBF.
	 *
	 * @var array{is_fix_mode: bool, phpcs_args: list<string>}
	 */
	private array $cli_args;

	/**
	 * Constructor for PhpcsRunner.
	 *
	 * Initializes configuration and parses CLI arguments.
	 */
	public function __construct() {
		$this->config = new PhpcsRunnerConfig();
		$this->cli_args = $this->parse_cli_arguments( $GLOBALS['argv'] ?? array() );
	}

	/**
	 * Main execution method for the PHPCS runner.
	 *
	 * Generates the runner ruleset, assembles, and executes the PHPCS command.
	 *
	 * @return int The exit code of the PHPCS command.
	 */
	public function run(): int {
		// Call XML generation. Errors within generate_runner_ruleset will exit.
		$this->generate_runner_ruleset();

		if ( $this->processed_installed_paths ) {
			$this->display_console_warning();
		}

		$this->check_runner_file_exists(); // Check after generation and potential warning.

		$resolved_executable = $this->resolve_phpcs_executable( $this->cli_args['is_fix_mode'] );
		$full_command = $this->assemble_command( $resolved_executable, $this->cli_args['phpcs_args'] );

		$this->output_debug_info( $full_command );

		return $this->execute_phpcs_command( $full_command );
	}

	// --- Helper Functions for Main Script Logic ---.

	/**
	 * Checks if the generated runner ruleset file exists.
	 */
	private function check_runner_file_exists(): void {
		if ( ! file_exists( $this->config->generated_runner_ruleset_path ) ) {
			fwrite( STDERR, "FATAL ERROR: runner-phpcs.xml was not created at {$this->config->generated_runner_ruleset_path}. PHPCS cannot run.\n" );
			exit( 1 );
		}
	}

	/**
	 * Displays a console warning about installed_paths.
	 */
	private function display_console_warning(): void {
		$warning_text = <<<WARNING

        ----------------------------------------------------------------------
        PHPCS RUNNER WARNING: Regarding 'installed_paths'
        ----------------------------------------------------------------------
        If you see "Referenced sniff ... does not exist" errors, it's likely
        due to how PHP_CodeSniffer resolves sniff paths, especially when run
        from a `vendor/bin` script or with complex project structures.

        This script attempts to set the PHPCS_INSTALLED_PATHS environment
        variable based on `phpcs --config-show installed_paths` or defaults.
        However, the most reliable way to ensure sniffs are found is to use
        ABSOLUTE paths within your main .phpcs.xml for any `<config name="installed_paths" .../>`
        directive, or for any ruleset paths in `<rule ref="..."/>`.

        Example for .phpcs.xml:
        <config name="installed_paths" value="/path/to/your/project/vendor/wp-coding-standards/wpcs,/path/to/your/project/vendor/slevomat/coding-standard"/>

        For more details, see PHPCS documentation on installed_paths and
        configuration.
        ----------------------------------------------------------------------

WARNING;
		fwrite( STDERR, $warning_text );
	}

	/**
	 * Parses command-line arguments for --fix and other PHPCS arguments.
	 *
	 * @param array<int, string> $argv Raw command-line arguments (typically from $GLOBALS['argv']).
	 * @return array{is_fix_mode: bool, phpcs_args: list<string>} Parsed arguments.
	 */
	private function parse_cli_arguments( array $argv ): array {
		$is_fix_mode = false;
		$phpcs_args = array();
		$script_name = array_shift( $argv ); // Remove script name.

		foreach ( $argv as $arg ) {
			if ( '--fix' === $arg ) {
				$is_fix_mode = true;
			} elseif ( '-s' === $arg ) { // PHPCS native show sniff codes.
				$phpcs_args[] = $arg;
			} elseif ( strpos( $arg, '--report=' ) === 0 ) {
				$phpcs_args[] = $arg; // Allow report type to be passed.
			} else {
				// For other arguments that might be file paths or other options.
				$phpcs_args[] = escapeshellarg( $arg );
			}
		}
		return array(
			'is_fix_mode' => $is_fix_mode,
			'phpcs_args' => $phpcs_args,
		);
	}

	/**
	 * Resolves the full path or command for the PHPCS/PHPCBF executable.
	 *
	 * @param bool $is_fix_mode True if PHPCBF (fix mode) is being used, false for PHPCS.
	 */
	private function resolve_phpcs_executable( bool $is_fix_mode ): string {
		$executable_to_use = $is_fix_mode ? $this->config->phpcbf_executable : $this->config->phpcs_executable;
		$resolved_command = '';

		if ( is_executable( $executable_to_use ) ) {
			$resolved_command = realpath( $executable_to_use );
		} else {
			// Fallback to assuming it's in PATH if not a direct executable path.
			// Use basename to be safe if $executable_to_use had path components.
			$resolved_command = basename( $executable_to_use );
		}

		if ( empty( $resolved_command ) ) {
			fwrite( STDERR, "Error: Could not resolve PHPCS/PHPCBF executable. Tried: {$executable_to_use}\n" );
			exit( 1 );
		} elseif ( ! is_executable( $resolved_command ) && ! $this->command_exists( $resolved_command ) ) {
			// If not an absolute path and not in PATH, then it's an error.
			fwrite( STDERR, "Warning: Resolved executable '{$resolved_command}' is not executable or not found in PATH. Attempted from '{$executable_to_use}'.\n" );
			// Depending on strictness, you might exit(1) here too.
			// For now, let it try and fail at passthru if it's truly not found.
		}

		return escapeshellarg( $resolved_command ); // Always escape the final command path.
	}

	/**
	 * Assembles the final command to be executed.
	 *
	 * @param string        $resolved_executable The fully resolved and escaped path to the PHPCS/PHPCBF executable.
	 * @param array<string> $phpcs_args Array of additional, already escaped arguments for PHPCS.
	 */
	private function assemble_command( string $resolved_executable, array $phpcs_args ): string {
		$base_command_parts = array(
			$resolved_executable,
			'--standard=' . escapeshellarg( $this->config->generated_runner_ruleset_path ),
		);

		$final_command_parts = $base_command_parts;
		if ( ! empty( $phpcs_args ) ) {
			$final_command_parts = array_merge( $final_command_parts, $phpcs_args );
		}
		return implode( ' ', $final_command_parts );
	}

	/**
	 * Outputs debug information if PHPCS_RUNNER_DEBUG is true.
	 *
	 * @param string $full_command The complete command string that will be/was executed.
	 */
	private function output_debug_info( string $full_command ): void {
		if ( getenv( 'PHPCS_RUNNER_DEBUG' ) === 'true' ) {
			fwrite( STDERR, "\nPHPCS Runner Script Debug Info:\n" );
			fwrite( STDERR, "---------------------------------\n" );
			fwrite( STDERR, "Project Root: {$this->config->project_root}\n" );
			fwrite( STDERR, "Source Ruleset (.phpcs.xml): {$this->config->source_ruleset_path}\n" );
			fwrite( STDERR, "Generated Runner Ruleset (used by PHPCS): {$this->config->generated_runner_ruleset_path}\n" );
			fwrite( STDERR, 'PHPCS_INSTALLED_PATHS (set): ' . ( getenv( 'PHPCS_INSTALLED_PATHS' ) ?: 'Not set or empty' ) . "\n" );
			fwrite( STDERR, "Final Command to Execute: {$full_command}\n" );
			fwrite( STDERR, 'Current Working Directory (for execution): ' . getcwd() . "\n" );
			fwrite( STDERR, "---------------------------------\n\n" );
		}
	}

	/**
	 * Checks if a command exists in the system's PATH.
	 *
	 * @param string $command_name The name of the command to check (e.g., 'phpcs', 'ls').
	 */
	private function command_exists( string $command_name ): bool {
		$test_command = escapeshellarg( $command_name ) . ' --version';
		$last_line = exec( $test_command, $output, $retval );
		return 0 === $retval;
	}

	/**
	 * Changes directory to project root and executes the PHPCS/PHPCBF command.
	 *
	 * @param string $full_command The complete command string to execute.
	 * @return int The exit code from the executed command.
	 */
	private function execute_phpcs_command( string $full_command ): int {
		$original_cwd = getcwd();
		if ( false !== $original_cwd && $this->config->project_root !== $original_cwd ) {
			if ( false === chdir( $this->config->project_root ) ) {
				fwrite( STDERR, "Error: Could not change directory to {$this->config->project_root}\n" );
				if ( getcwd() !== $original_cwd ) {
					 chdir( $original_cwd );
				}
				exit( 1 );
			}
		}

		passthru( $full_command, $return_code );

		if ( false !== $original_cwd && $this->config->project_root !== $original_cwd && getcwd() !== $original_cwd ) {
			chdir( $original_cwd );
		}
		return (int) $return_code;
	}

	// --- XML Generation Methods ---.

	/**
	 * Creates a well-formatted DOM comment node.
	 *
	 * Sanitizes input text, wraps lines, and indents them for readability
	 * within the XML structure.
	 *
	 * @param \DOMDocument $doc The DOM document to create the comment in.
	 * @param string       $raw_text The raw text content for the comment.
	 * @return \DOMComment The created DOM comment node.
	 */
	private function create_formatted_dom_comment( \DOMDocument $doc, string $raw_text ): \DOMComment {
		// 1. Sanitize input.
		$clean_text = str_replace( array( '<!--', '-->' ), '', $raw_text );
		$sanitized_text = str_replace( '--', '- -', $clean_text );

		$trimmed_overall_sanitized_text = trim( $sanitized_text );

		if ( '' === $trimmed_overall_sanitized_text ) {
			// Original comment was empty or only whitespace.
			// Create a structured empty comment block: <!--\n    \n    -->.
			$comment_content = "\n    \n  ";
		} else {
			// Process lines from the original (but sanitized) text to preserve blank line structure.
			$lines_from_source = explode( "\n", $sanitized_text );
			$final_indented_lines = array();

			foreach ( $lines_from_source as $original_line ) {
				// Trim each line before deciding to wordwrap, to handle lines that are just whitespace.
				$line_for_wrapping = trim( $original_line );

				if ( '' === $line_for_wrapping ) {
					// This was a blank line in the source (or became blank after trim).
					// Add an indented blank line to maintain the structure.
					$final_indented_lines[] = '    '; // Represents an empty line, but indented.
				} else {
					// This line has content. Wordwrap it to ~70 chars, then indent each resulting sub-line.
					// The `true` for cut ensures long words are broken.
					$wrapped_sub_lines = explode( "\n", wordwrap( $line_for_wrapping, 70, "\n", true ) );
					foreach ( $wrapped_sub_lines as $sub_line ) {
						// Each sub-line from wordwrap gets the 4-space indent.
						$final_indented_lines[] = '    ' . $sub_line;
					}
				}
			}
			// Construct the final comment string for DOMComment.
			// Start with a newline, join indented lines with newlines,
			// and end with a newline followed by 2 spaces for '-->' alignment.
			$comment_content = "\n" . implode( "\n", $final_indented_lines ) . "\n  ";
		}

		return $doc->createComment( $comment_content );
	}

	/**
	 * Loads the source .phpcs.xml file.
	 */
	private function load_source_ruleset(): ?\DOMDocument {
		if ( ! file_exists( $this->config->source_ruleset_path ) ) {
			fwrite( STDERR, "Error: Source ruleset file not found at {$this->config->source_ruleset_path}\n" );
			return null;
		}
		$doc = new \DOMDocument();
		if ( ! $doc->load( $this->config->source_ruleset_path ) ) {
			fwrite( STDERR, "Error: Could not load or parse source ruleset file at {$this->config->source_ruleset_path}\n" );
			return null;
		}
		return $doc;
	}

	/**
	 * Initializes the target DOMDocument and its root <ruleset> element for the runner.
	 *
	 * @param \DOMDocument $source_doc_for_context Provides context (like original ruleset name and URI) for the runner ruleset.
	 * @return \DOMDocument|null The new DOMDocument for the runner, or null on failure.
	 */
	private function modify_ruleset_for_runner( \DOMDocument $source_doc_for_context ): ?\DOMDocument {
		$target_doc = new \DOMDocument( '1.0', 'UTF-8' );
		$target_doc->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- For pretty printing.

		$source_ruleset_element_for_context = $source_doc_for_context->getElementsByTagName( 'ruleset' )->item( 0 );

		$runner_ruleset_name = 'runner-ruleset'; // Default name.
		if ( $source_ruleset_element_for_context && $source_ruleset_element_for_context->hasAttribute( 'name' ) ) {
			$runner_ruleset_name = $source_ruleset_element_for_context->getAttribute( 'name' ) . '-runner';
		}

		$runner_ruleset_node = $target_doc->createElement( 'ruleset' );
		$runner_ruleset_node->setAttribute( 'name', $runner_ruleset_name );
		$target_doc->appendChild( $runner_ruleset_node );

		$source_uri_for_comment = $source_doc_for_context->documentURI ?? $this->config->source_ruleset_path; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// Fallback for documentURI, especially if DOMDocument was created from string or not loaded from file.
		if ( strpos( $source_uri_for_comment, 'file://' ) === 0 ) {
			$source_uri_for_comment = substr( $source_uri_for_comment, 7 ); // Strip 'file://'.
		}

		$generator_comment_text = sprintf(
			" This is a generated file created by %s at %s. DO NOT EDIT DIRECTLY. \n" .
			" Source: %s \n" .
			' Runner Target: %s ',
			basename( __FILE__ ), // Use the current script's name.
			gmdate( 'Y-m-d H:i:s T' ),
			basename( $source_uri_for_comment ),
			basename( $this->config->generated_runner_ruleset_path )
		);
		$generator_comment = $this->create_formatted_dom_comment( $target_doc, $generator_comment_text );
		$runner_ruleset_node->appendChild( $generator_comment );

		return $target_doc;
	}

	/**
	 * Processes <file> nodes, making paths absolute.
	 *
	 * @param \DOMElement  $source_node The source <file> DOM element.
	 * @param \DOMDocument $doc_to_modify The runner DOM document to which new nodes will be added.
	 * @param string       $project_root The absolute path to the project root.
	 * @return \DOMElement|null The processed <file> node, or null if it could not be resolved.
	 */
	private function process_file_node(
		\DOMElement $source_node,
		\DOMDocument $doc_to_modify,
		string $project_root
	): ?\DOMElement {
		$path = $source_node->nodeValue; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Get node value.
		$absolute_path = realpath( $project_root . DIRECTORY_SEPARATOR . $path );

		if ( $absolute_path && ( is_file( $absolute_path ) || is_dir( $absolute_path ) ) ) {
			$file_node = $doc_to_modify->createElement( 'file', $absolute_path );
			return $file_node;
		} else {
			fwrite( STDERR, "Warning: Path '{$path}' in <file> node could not be resolved to an absolute path or does not exist. Skipping.\n" );
			return null;
		}
	}

	/**
	 * Processes <exclude-pattern> nodes, making paths absolute if possible.
	 *
	 * @param \DOMElement  $source_node The source <exclude-pattern> DOM element.
	 * @param \DOMDocument $doc_to_modify The runner DOM document.
	 * @param string       $project_root The project root directory path.
	 * @return \DOMElement The processed <exclude-pattern> node.
	 */
	private function process_exclude_pattern_node(
		\DOMElement $source_node,
		\DOMDocument $doc_to_modify,
		string $project_root
	): \DOMElement {
		$pattern = $source_node->nodeValue; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$target_node = $doc_to_modify->createElement( 'exclude-pattern' );

		$is_glob = strpbrk( $pattern, '*?[' ) !== false;
		$final_pattern = '';

		if ( $is_glob ) {
			// Globs are typically relative to the ruleset or basepath.
			// PHPCS should handle these. If issues arise, we might need to make them absolute too.
			// For now, pass them as-is, escaped.
			$final_pattern = $pattern;
		} else {
			// Not a glob. Determine if it's an absolute path or needs to be made absolute.
			$path_is_device_absolute = preg_match( '/^[a-zA-Z]:(\\|\/)/', $pattern ) || strpos( $pattern, '\\' ) === 0; // C:\foo or \\server\share.
			$path_is_unix_root_prefixed = strpos( $pattern, '/' ) === 0;

			if ( $path_is_device_absolute ) { // True absolute like C:\foo.
				$current_final_pattern = str_replace( DIRECTORY_SEPARATOR, '/', $pattern );
			} elseif ( $path_is_unix_root_prefixed ) { // Starts with /.
				// Check if it's a canonical, existing absolute path.
				$real_pattern_path = realpath( $pattern );
				if ( false !== $real_pattern_path && str_replace( DIRECTORY_SEPARATOR, '/', $real_pattern_path ) === str_replace( DIRECTORY_SEPARATOR, '/', $pattern ) ) {
					// It's a canonical, existing absolute path (e.g., /usr/bin).
					$current_final_pattern = str_replace( DIRECTORY_SEPARATOR, '/', $pattern );
				} else {
					// It's like /vendor/ (not a root dir) or /non/existent/absolute or a symlink needing resolution relative to project.
					// Treat as $project_root . $pattern (e.g., /path/to/project . /vendor/).
					// Ensure $project_root does not have a trailing slash and $pattern starts with one, or add one if needed..
					$resolved_pattern = rtrim( $project_root, DIRECTORY_SEPARATOR . '/' ) . '/' . ltrim( $pattern, DIRECTORY_SEPARATOR . '/' );
					$current_final_pattern = str_replace( DIRECTORY_SEPARATOR, '/', $resolved_pattern );
				}
			} else { // Relative path like 'src/foo' or 'vendor/' (without leading slash).
				$resolved_pattern = rtrim( $project_root, DIRECTORY_SEPARATOR . '/' ) . DIRECTORY_SEPARATOR . ltrim( $pattern, DIRECTORY_SEPARATOR . '/' );
				$current_final_pattern = str_replace( DIRECTORY_SEPARATOR, '/', $resolved_pattern );
			}
			$final_pattern = $current_final_pattern;
		}

		$target_node->nodeValue = htmlspecialchars( $final_pattern, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $target_node;
	}

	/**
	 * Processes <arg name="parallel"> node, commenting it out with an explanation.
	 *
	 * @param \DOMElement  $source_node The source <arg name="parallel"> DOM element.
	 * @param \DOMDocument $doc_to_modify The runner DOM document.
	 * @return array<\DOMComment|\DOMElement> Array of nodes (comments) to add.
	 */
	private function process_parallel_arg_node( \DOMElement $source_node, \DOMDocument $doc_to_modify ): array {
		$explanation_text = "Parallel processing has been disabled in this generated ruleset due to a\n" .
						"previous fatal error. This error (related to trim() and NULL paths with\n" .
						"PHP_CodeSniffer > 3.9 on PHP 8.x, particularly when paths contain spaces)\n" .
						"caused linting to fail.\n\n" .
						"As a workaround, linting will proceed sequentially.\n" .
						"The original parallel processing argument from .phpcs.xml is preserved as a\n" .
						'comment below for reference.';

		$nodes = array();
		$nodes[] = $this->create_formatted_dom_comment( $doc_to_modify, $explanation_text );
		$xml_string_of_source_node = $source_node->ownerDocument->saveXML( $source_node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$nodes[] = $this->create_formatted_dom_comment( $doc_to_modify, $xml_string_of_source_node );
		return $nodes;
	}

	/**
	 * Processes <config name="installed_paths"> node, adding a warning comment and copying the node.
	 *
	 * @param \DOMElement  $source_node The source <config name="installed_paths"> DOM element.
	 * @param \DOMDocument $doc_to_modify The runner DOM document.
	 * @return array<\DOMComment|\DOMElement> Array of nodes (comment and imported config node) to add.
	 */
	private function process_installed_paths_node( \DOMElement $source_node, \DOMDocument $doc_to_modify ): array {
		$nodes = array();
		$warning_text = <<<TEXT
		IMPORTANT: Regarding 'installed_paths' in this generated ruleset:
		1. The paths below are copied AS-IS from your source .phpcs.xml.
		2. For PHP_CodeSniffer to reliably find sniffs, especially when this runner script
		is executed from a vendor/bin context or if your project has complex dependencies,
		it is STRONGLY RECOMMENDED that these paths are ABSOLUTE in your source .phpcs.xml.
		3. If you encounter "Referenced sniff ... does not exist" errors, ensure the paths
		are absolute and correct in your source .phpcs.xml.
		TEXT;
		$nodes[] = $this->create_formatted_dom_comment( $doc_to_modify, $warning_text );
		$imported_node = $doc_to_modify->importNode( $source_node, true );
		$nodes[] = $imported_node;
		$this->processed_installed_paths = true; // Indicate that we found and will process an installed_paths node.

		return $nodes;
	}

	/**
	 * Imports a generic XML element node into the target document.
	 *
	 * @param \DOMElement  $source_node The source DOM element to import.
	 * @param \DOMDocument $doc_to_modify The runner DOM document to import the node into.
	 */
	private function process_generic_element_node( \DOMElement $source_node, \DOMDocument $doc_to_modify ): \DOMElement {
		return $doc_to_modify->importNode( $source_node, true );
	}

	/**
	 * Imports an XML comment node into the target document, unless it's a generator comment.
	 *
	 * @param \DOMComment  $source_node The source DOM comment to process.
	 * @param \DOMDocument $doc_to_modify The runner DOM document.
	 */
	private function process_comment_node( \DOMComment $source_node, \DOMDocument $doc_to_modify ): ?\DOMComment {
		if ( strpos( $source_node->nodeValue, 'This is a generated file' ) === false && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			strpos( $source_node->nodeValue, 'created by php-codesniffer.php' ) === false ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $doc_to_modify->importNode( $source_node, true );
		}
		return null;
	}

	/**
	 * Main function to generate .phpcs-runner.xml from the source .phpcs.xml.
	 */
	private function generate_runner_ruleset(): bool {
		$source_doc = $this->load_source_ruleset();
		if ( ! $source_doc->documentURI ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return false;
		}

		// Create the new runner document structure, passing source_doc for context.
		$runner_doc = $this->modify_ruleset_for_runner( $source_doc );
		if ( ! $runner_doc ) {
			fwrite( STDERR, "Error: Could not initialize runner document structure.\n" );
			return false;
		}
		// Get the <ruleset> node from the NEW runner document, to append children to it.
		$runner_ruleset_node = $runner_doc->getElementsByTagName( 'ruleset' )->item( 0 );
		if ( ! $runner_ruleset_node ) {
			// This should not happen if modify_ruleset_for_runner succeeded.
			fwrite( STDERR, "Critical Error: <ruleset> node not found in newly created runner document.\n" );
			return false;
		}

		// Get the <ruleset> node from the SOURCE document to iterate its children.
		$source_ruleset_node_to_iterate = $source_doc->getElementsByTagName( 'ruleset' )->item( 0 );
		if ( ! $source_ruleset_node_to_iterate ) {
			// Should have been caught by load_source_ruleset if it was critical there.
			fwrite( STDERR, "Error: <ruleset> element not found in source .phpcs.xml for iteration.\n" );
			return false;
		}

		foreach ( $source_ruleset_node_to_iterate->childNodes as $source_child_node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			// fwrite(STDERR, "DEBUG: Processing source child node: " . $source_child_node->nodeName . "\n"); // Can be noisy with #text.
			$nodes_to_append_to_runner = array(); // Nodes to be added to the RUNNER ruleset.

			if ( XML_ELEMENT_NODE === $source_child_node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$source_child_element = $source_child_node;
				$node_name = $source_child_element->nodeName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

				// All process_* methods now use $runner_doc to create new nodes.
				switch ( $node_name ) {
					case 'file':
						$processed_node = $this->process_file_node( $source_child_element, $runner_doc, $this->config->project_root );
						if ( $processed_node ) {
							$nodes_to_append_to_runner[] = $processed_node;
						}
						break;
					case 'exclude-pattern':
						$processed_node = $this->process_exclude_pattern_node( $source_child_element, $runner_doc, $this->config->project_root );
						if ( $processed_node ) {
							$nodes_to_append_to_runner[] = $processed_node;
						}
						break;
					case 'arg':
						if ( $source_child_element->getAttribute( 'name' ) === 'parallel' ) {
							$parallel_nodes = $this->process_parallel_arg_node( $source_child_element, $runner_doc );
							$nodes_to_append_to_runner = array_merge( $nodes_to_append_to_runner, $parallel_nodes );
						} else {
							$generic_node = $this->process_generic_element_node( $source_child_element, $runner_doc );
							if ( $generic_node ) {
								$nodes_to_append_to_runner[] = $generic_node;
							}
						}
						break;
					case 'config':
						if ( $source_child_element->getAttribute( 'name' ) === 'installed_paths' ) {
							$path_nodes = $this->process_installed_paths_node( $source_child_element, $runner_doc );
							$nodes_to_append_to_runner = array_merge( $nodes_to_append_to_runner, $path_nodes );
						} else {
							$generic_node = $this->process_generic_element_node( $source_child_element, $runner_doc );
							if ( $generic_node ) {
								$nodes_to_append_to_runner[] = $generic_node;
							}
						}
						break;
					default:
						$generic_node = $this->process_generic_element_node( $source_child_element, $runner_doc );
						if ( $generic_node ) {
							$nodes_to_append_to_runner[] = $generic_node;
						}
						break;
				}
			} elseif ( XML_COMMENT_NODE === $source_child_node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$source_child_comment = $source_child_node;
				// Avoid logging every comment if too verbose, or shorten it.
				// fwrite(STDERR, "DEBUG: Processing XML_COMMENT_NODE from source. Value: " . substr($source_child_comment->nodeValue, 0, 50) . "...\n");.
				if ( strpos( $source_child_comment->nodeValue, 'This is a generated file' ) === false && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Check comment content.
					 strpos( $source_child_comment->nodeValue, 'created by ' . basename( __FILE__ ) ) === false && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Match new comment format.
					 strpos( $source_child_comment->nodeValue, 'created by .php-codesniffer.php' ) === false // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Match old comment format.
					) {
					$comment_node = $this->process_comment_node( $source_child_comment, $runner_doc );
					if ( $comment_node ) {
						$nodes_to_append_to_runner[] = $comment_node;
					}
				}
			}
			// Other node types like #text from source are implicitly ignored by not having specific handling.

			foreach ( $nodes_to_append_to_runner as $node_to_append ) {
				$runner_ruleset_node->appendChild( $node_to_append ); // Append to RUNNER's ruleset.
			}
		}

		if ( $runner_doc->save( $this->config->generated_runner_ruleset_path ) === false ) {
			fwrite( STDERR, "Error: Could not save generated ruleset to {$this->config->generated_runner_ruleset_path}\n" );
			return false; // Indicate failure.
		}
		return true; // Indicate success.
	}
}

// --- Main Script Execution ---
$runner = new PhpcsRunner();
$exit_code = $runner->run();
exit( $exit_code );
