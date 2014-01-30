<?php
/**
 * Copyright 2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2014 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Autoloader
 */

/**
 * Provides a classmapper that implements prefix matching using a simple
 * string search within a base application directory.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Autoloader
 */
class Horde_Autoloader_ClassPathMapper_PrefixString
implements Horde_Autoloader_ClassPathMapper
{
    /**
     * Include path.
     *
     * @var string
     */
    private $_includePath;

    /**
     * Prefix to search for.
     *
     * @var string
     */
    private $_prefix;

    /**
     * Constructor
     *
     * @param string $prefix       Prefix.
     * @param string $includePath  Include path.
     */
    public function __construct($prefix, $includePath)
    {
        $this->_includePath = $includePath;
        $this->_prefix = $prefix;
    }

    /**
     */
    public function mapToPath($className)
    {
        if (stripos($className, $this->_prefix) === 0) {
            $len = strlen($this->_prefix);
            if ($len === strlen($className)) {
                return $this->_includePath . '/' . $className . '.php';
            } elseif (($c = $className[$len]) &&
                      ($c == '_') || ($c == '\\')) {
                return $this->_includePath . '/' .
                    str_replace($c, '/', substr($className, $len + 1)) . '.php';
            }
        }

        return false;
    }

}