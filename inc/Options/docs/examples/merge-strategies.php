<?php
/**
 * RegisterOptions Examples: Read–Merge–Write strategies (no new API required)
 *
 * These patterns show how to read current values, merge with new input, and write back
 * through the existing sanitize + validate + policy gates provided by RegisterOptions.
 *
 * Patterns covered:
 * - Unique tag list merge (preserve order)
 * - List of objects merged by `id` (staged wins on collision)
 * - Nested associative replace-by-key (lists untouched)
 */

declare(strict_types=1);

use Ran\PluginLib\Util\Sanitize;
use Ran\PluginLib\Options\RegisterOptions;

/** @var RegisterOptions $options */

// ------------------------------------------------------------
// 1) Merge a list of tags uniquely, preserving order
// ------------------------------------------------------------
function merge_tags(RegisterOptions $options, mixed $incoming): void {
	$current = $options->get_option('tags'); // e.g. ['a','b']

	$ensureList = Sanitize::array()->ensureList();
	$current    = is_array($current) ? $ensureList($current) : array();
	$incoming   = is_array($incoming) ? $ensureList($incoming) : array();

	// DB order first, then staged; remove duplicates while preserving order
	$merged = array_merge($current, $incoming);
	$unique = Sanitize::array()->uniqueList();
	$merged = $unique($merged);

	// Optional: canonicalize when order is not semantically meaningful
	// $merged = (Sanitize::canonical()->orderInsensitiveShallow())($merged);

	$options->stage_option('tags', $merged)->commit_merge();
}

// ------------------------------------------------------------
// 2) Merge a list of objects uniquely by `id` (staged wins on collision)
// ------------------------------------------------------------
function merge_items_by_id(RegisterOptions $options, mixed $incoming): void {
	$current    = $options->get_option('items'); // e.g. [['id'=>1,'name'=>'A'], ['id'=>2,'name'=>'B']]
	$ensureList = Sanitize::array()->ensureList();

	$current  = is_array($current) ? $ensureList($current) : array();
	$incoming = is_array($incoming) ? $ensureList($incoming) : array();

	$byId = array();
	foreach ($current as $it) {
		if (is_array($it) && isset($it['id'])) {
			$byId[(string) $it['id']] = $it;
		}
	}
	foreach ($incoming as $it) {
		if (is_array($it) && isset($it['id'])) {
			$byId[(string) $it['id']] = $it; // staged wins
		}
	}

	// Deterministic order to reduce churn
	ksort($byId, SORT_NATURAL);
	$merged = array_values($byId);

	$options->stage_option('items', $merged)->commit_merge();
}

// ------------------------------------------------------------
// 3) Nested associative replace-by-key (lists untouched)
// ------------------------------------------------------------
function merge_settings_assoc(RegisterOptions $options, mixed $patch): void {
	$dbSettings = $options->get_option('settings');
	$dbArr      = is_array($dbSettings) ? $dbSettings : array();
	$stArr      = is_array($patch) ? $patch : array();

	// array_replace_recursive focuses on associative keys; numeric-keyed lists will be replaced wholesale.
	$merged = array_replace_recursive($dbArr, $stArr);

	$options->stage_option('settings', $merged)->commit_merge();
}
