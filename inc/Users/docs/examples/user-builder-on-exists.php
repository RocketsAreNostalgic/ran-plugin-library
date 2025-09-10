<?php
declare(strict_types=1);

use Ran\PluginLib\Users\User;
use Ran\PluginLib\Config\Config;

/** @var Config $config */
$config  = /* obtain Config instance */ null;
$builder = new User($config, null);

// Attach to existing user if present; otherwise creates a new user
$attach = $builder
	->email('grace@example.com')
	->on_exists('attach')
	->create();

// Fail if user exists
try {
	$builder
		->email('grace@example.com')
		->on_exists('fail')
		->create();
} catch (\Exception $e) {
	// handle
}

// Update allowlisted profile fields if user exists
$update = $builder
	->email('grace@example.com')
	->name('Grace', 'Hopper')
	->role('editor')
	->on_exists('update-profile')
	->create();
