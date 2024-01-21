<?php
/**
 * Abstract implementation of a Singleton class.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Singleton;
use Exception;
/**
 * Our Singleton class defines the alternative constructor method `GetInstance`
 * which will always return the same instance of the class.
 */
abstract class SingletonAbstract {
    /**
     * The Singleton's instance is stored in a static field on an array
	 * as we'll allow our Singleton to have subclasses.
	 * Each item in this array will be an instance of a specific Singleton's subclass.
     */
    private static $instances = [];

    /** Protected to prevent direct construction with `new` operator. */
    protected function __construct() { }

    /** Not clone-able. */
    protected function __clone() { }

    /** Cannot be restored from strings. */
    public function __wakeup() {
        throw new Exception("RanPluginLib: Cannot unserialize a singleton.");
    }

    /**
     * This is the static method controls the access to the singleton instance.
	 * On first run, it creates a singleton object and places it into the static field.
	 * Every subsequent run, it will return the existing object stored in the static field.
     *
     * This implementation allows sub-class of a Singleton class
	 * while storing only one instance of each subclass.
     */
    public static function get_instance()
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    /**
     * Define some business logic to execute.
     */
    // public function doBusinessLogic() {}

}
