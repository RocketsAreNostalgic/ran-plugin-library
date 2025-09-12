<?php
declare(strict_types=1);

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\Storage\StorageContext;

// Bootstrap a Config (plugin example)
$config = Config::fromPluginFile(__FILE__);
$opts   = $config->options(StorageContext::forSite());

// Assume a nested structure stored under 'complex_map'
// Current DB value (for illustration):
// [ 'level1' => [ 'existing' => 'a', 'keep' => 'z' ] ]

// Patch to apply
$patch = array( 'level1' => array( 'existing' => 'b', 'added' => 'x' ) );

// 1) Replace without merge (deep replace via set_option + flush(false))
//    - Overwrites entire value for 'complex_map' with $patch merged into current in-memory value
$current = $opts->get_option('complex_map', array());
$merged  = array_replace_recursive(is_array($current) ? $current : array(), $patch);
$opts->set_option('complex_map', $merged);   // persists immediately
// If batching multiple keys before final write, prefer staging + flush(false) instead:
// $opts->stage_option('complex_map', $merged)->flush(false);

// 2) Shallow, top-level merge from DB (keeps unrelated DB keys)
//    - Stage multiple additions, then persist once with flush(true)
$opts
  ->stage_options(array(
    'feature_flag' => true,
    'timeout'      => 30,
  ))
  ->flush(true); // shallow merge at top-level keys only

/*
Notes:
- flush(true) merges top-level keys from DB and in-memory; nested structures are not deep-merged.
- For precise nested semantics, prefer read–modify–write on individual keys, then either rely on
  set_option() immediate persistence or stage with stage_option() + flush(false).
*/
