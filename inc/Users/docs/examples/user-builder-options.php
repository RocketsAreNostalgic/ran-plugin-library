<?php
declare(strict_types=1);

use Ran\PluginLib\Users\User;
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/** @var Config $config */
$config  = /* obtain Config instance */ null;
$builder = new User($config, null);

$schema = array(
	'theme' => array('default' => 'light'),
	'alpha' => array('default' => 0.5),
);

// Meta storage (default)
$resultMeta = $builder
	->email('linus@example.com')
	->on_exists('attach')
	->user_scope(false, 'meta')
	->schema($schema, true, false) // register schema, seed defaults
	->options(array('theme' => 'dark'))
	->create();

// Option storage (global per-user options)
$allowAll = new class implements WritePolicyInterface {
	public function allow(string $op, array $ctx): bool {
		return true;
	}
};

$resultOption = $builder
	->email('torvalds@example.com')
	->on_exists('attach')
	->user_scope(true, 'option')
	->with_policy($allowAll)
	->options(array('alpha' => 1.0))
	->create();
