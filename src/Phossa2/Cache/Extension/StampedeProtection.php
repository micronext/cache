<?php
/**
 * Phossa Project
 *
 * PHP version 5.4
 *
 * @category  Library
 * @package   Phossa2\Cache
 * @copyright Copyright (c) 2016 phossa.com
 * @license   http://mit-license.org/ MIT License
 * @link      http://www.phossa.com/
 */
/*# declare(strict_types=1); */

namespace Phossa2\Cache\Extension;

use Phossa2\Cache\CachePool;
use Phossa2\Cache\Message\Message;
use Phossa2\Event\Interfaces\EventInterface;
use Phossa2\Cache\Interfaces\CacheItemExtendedInterface;

/**
 * Stampede protection for the cache
 *
 * If item expires in 600 seconds (configurable), and by 5% (configurable)
 * chance, this extension will mark the $item as a miss to force regenerating
 * the item before it is truly expired.
 *
 * ```php
 * $stampede = new StampedeProtection([
 *     'probability' => 60, // probability 60/1000
 *     'time_left'   => 300 // change time left in seconds
 * ]);
 * $cachePool->addExtension($stampede);
 * ```
 *
 * @package Phossa2\Cache
 * @author  Hong Zhang <phossa@126.com>
 * @version 2.0.0
 * @since   2.0.0 added
 */
class StampedeProtection extends CacheExtensionAbstract
{
    /**
     * Probability, usually 1 - 100
     *
     * @var    int
     * @access protected
     */
    protected $probability = 50;

    /**
     * time left in seconds
     *
     * @var    int
     * @access protected
     */
    protected $time_left = 600;

    /**
     * {@inheritDoc}
     */
    public function methodsAvailable()/*# : array */
    {
        return ['stampedeProtect'];
    }

    /**
     * {@inheritDoc}
     */
    protected function cacheEvents()/*# : array */
    {
        // change hit status
        return [
            [
                'event'   => CachePool::EVENT_HAS_AFTER,
                'handler' => ['stampedeProtect', 80]
            ]
        ];
    }

    /**
     * Change hit status if ...
     *
     * @param  EventInterface $event
     * @return bool
     * @access public
     */
    public function stampedeProtect(EventInterface $event)/*# : bool */
    {
        /* @var CachePool $pool */
        $pool = $event->getTarget();

        $item = $event->getParam('item');

        if ($item instanceof CacheItemExtendedInterface) {
            // time left
            $left = $item->getExpiration()->getTimestamp() - time();

            if ($left < $this->time_left &&
                rand(1, 1000) <= $this->probability
            ) {
                return $pool->setError(
                    Message::get(Message::CACHE_EXT_STAMPEDE, $item->getKey()),
                    Message::CACHE_EXT_STAMPEDE
                );
            }
        }

        return true;
    }
}
