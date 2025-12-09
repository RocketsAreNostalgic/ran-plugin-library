<?php
declare(strict_types=1);

use Ran\PluginLib\Users\User;
use Ran\PluginLib\Config\Config;

// Bootstrap Config from your plugin/theme context
/** @var Config $config */
$config = /* obtain Config instance */ null;

$builder = new User($config, null);
$result  = $builder
	->email('ada@example.com')
	->name('Ada', 'Lovelace')
	->role('subscriber')
	->notify(true)
	->create();

// $result is a Ran\PluginLib\Users\UserResult
// $result->created === true for a new user; false when attached to an existing user.
