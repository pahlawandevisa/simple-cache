<?php

namespace voku\cache;

/**
 * Cache: global-cache class
 *
 * can use different cache-adapter:
 * - Redis
 * - Memcache / Memcached
 * - APC / APCu
 * - Xcache
 * - Array
 *
 * @package   voku\cache
 */
class Cache implements iCache
{

  /**
   * @var iAdapter
   */
  private $adapter;

  /**
   * @var iSerializer
   */
  private $serializer;

  /**
   * @var string
   */
  private $prefix = '';

  /**
   * @var Boolean
   */
  private $cacheIsReady = false;

  /**
   * @var bool
   */
  private $active = true;

  /**
   * __construct
   *
   * @param iAdapter    $adapter
   * @param iSerializer $serializer
   * @param boolean     $checkForUser | check for dev-ip or if cms user is logged-in ...
   */
  public function __construct($adapter = null, $serializer = null, $checkForUser = true)
  {
    static $adapterCache;

    // test the cache also for dev
    $testCache = isset($_GET['testCache']) ? (int) $_GET['testCache'] : 0;

    // check for user-session / dev / ip / config && no testCache is set
    if ($checkForUser === true && $testCache != 1) {
      if (
        // cache isn't active
          $this->active !== true ||
          // $_SERVER == client
          $_SERVER['SERVER_ADDR'] == $this->get_client_ip() ||
          // user is loggedIn
          $_SESSION['userID'] ||
          // user is a dev
          $this->checkForDev() === true
      ) {
        return false;
      }
    }

    // add default prefix
    $this->setPrefix($_SERVER['SERVER_NAME'] . '_' . (isset($_SESSION['language']) ? $_SESSION['language'] : '') . '_' . (isset($_SESSION['language_extra']) ? $_SESSION['language_extra'] : ''));

    if ($adapter === null || !is_object($adapter)) {

      if (is_object($adapterCache)) {
        $adapter = $adapterCache;
      } else {


        $redis = null;
        $isRedisAvailable = false;
        if (extension_loaded('redis')) {
          if (class_exists('\Predis\Client')) {
            $redis = new \Predis\Client(
                array(
                    'scheme'  => 'tcp',
                    'host'    => '127.0.0.1',
                    'port'    => 6379,
                    'timeout' => '2.0'
                )
            );
            try {
              $redis->connect();
              $isRedisAvailable = $redis->getConnection()->isConnected();
            }
            catch (\Exception $e) {
              // nothing
            }
          }
        }

        if ($isRedisAvailable === false) {
          $redis = null;
        }

        $adapterRedis = new AdapterPredis($redis);
        if ($adapterRedis->installed() === true) {

          // fallback to Redis
          $adapter = $adapterRedis;

        } else {

          $memcached = null;
          $isMemcachedAvailable = false;
          if (extension_loaded('memcached')) {
            $memcached = new \Memcached();
            $isMemcachedAvailable = $memcached->addServer('127.0.0.1', '11211');
          }

          if ($isMemcachedAvailable === false) {
            $memcache = null;
          }

          $adapterMemcached = new AdapterMemcached($memcached);
          if ($adapterMemcached->installed() === true) {

            // fallback to Memcached
            $adapter = $adapterMemcached;

          } else {

            $memcache = null;
            $isMemcacheAvailable = false;
            if (class_exists('\Memcache')) {
              $memcache = new \Memcache;
              $isMemcacheAvailable = @$memcache->connect('localhost', 11211);
            }

            if ($isMemcacheAvailable === false) {
              $memcache = null;
            }

            $adapterMemcache = new AdapterMemcache($memcache);
            if ($adapterMemcache->installed() === true) {

              // fallback to Memcache
              $adapter = $adapterMemcache;

            } else {
              $adapterXcache = new AdapterXcache();
              if ($adapterXcache->installed() === true) {

                // fallback to Xcache
                $adapter = $adapterXcache;

              } else {

                $adapterApc = new AdapterApc();
                if ($adapterApc->installed() === true) {

                  // fallback to APC || APCu
                  $adapter = $adapterApc;

                } else {
                  // no cache-adapter available
                  return false;
                }
              }
            }
          }
        }

        $adapterCache = $adapter;
      }

    }

    // set serializer for memcached
    if (!is_object($serializer) && $serializer === null) {
      if (
          $adapter instanceof AdapterMemcached
          ||
          $adapter instanceof AdapterMemcache
      ) {
        $serializer = new SerializerNo();
      }
      // set serializer as default
      else {
        $serializer = new SerializerIgbinary();
      }
    }

    // check if we will use the cache
    if (
        !$serializer instanceof iSerializer
        ||
        !$adapter instanceof iAdapter
    ) {
      return false;
    }

    $this->setCacheIsReady(true);

    $this->adapter = $adapter;
    $this->serializer = $serializer;

    return true;
  }

  /**
   * returns the IP address of the client
   *
   * @param   bool $trust_proxy_headers   Whether or not to trust the
   *                                      proxy headers HTTP_CLIENT_IP
   *                                      and HTTP_X_FORWARDED_FOR. ONLY
   *                                      use if your $_SERVER is behind a
   *                                      proxy that sets these values
   *
   * @return  string
   */
  private function get_client_ip($trust_proxy_headers = false)
  {
    if ($trust_proxy_headers) {
      return $_SERVER['REMOTE_ADDR'];
    }

    if ($_SERVER['HTTP_CLIENT_IP']) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
  }

  /**
   * set cacheIsReady state
   *
   * @param Boolean $cacheIsReady
   */
  private function setCacheIsReady($cacheIsReady)
  {
    $this->cacheIsReady = (boolean)$cacheIsReady;
  }

  /**
   * get the cacheIsReady state
   *
   * @return Boolean
   */
  public function getCacheIsReady()
  {
    return $this->cacheIsReady;
  }

  /**
   * get cached-item by key
   *
   * @param String $key
   *
   * @return mixed
   */
  public function getItem($key)
  {
    $storeKey = $this->calculateStoreKey($key);

    if ($this->adapter instanceof iAdapter) {
      $serialized = $this->adapter->get($storeKey);
      $value = $serialized ? $this->serializer->unserialize($serialized) : null;
    } else {
      return null;
    }

    return $value;
  }

  /**
   * calculate store-key (prefix + $rawKey)
   *
   * @param String $rawKey
   *
   * @return String
   */
  private function calculateStoreKey($rawKey)
  {
    return $this->getPrefix() . $rawKey;
  }

  /**
   * @return mixed
   */
  public function getPrefix()
  {
    return $this->prefix;
  }

  /**
   * set prefix [WARNING: do not use if you don't know what you do]
   *
   * @param string $prefix
   */
  public function setPrefix($prefix)
  {
    $this->prefix = (string)$prefix;
  }

  /**
   * set cache-item by key => value + date
   *
   * @param           $key
   * @param           $value
   * @param \DateTime $date
   *
   * @return mixed|void
   * @throws \Exception
   */
  public function setItemToDate($key, $value, \DateTime $date)
  {
    $ttl = $date->getTimestamp() - time();

    if ($ttl <= 0) {
      throw new \Exception('Date in the past.');
    }

    $storeKey = $this->calculateStoreKey($key);

    $this->setItem($storeKey, $value, $ttl);
  }

  /**
   * set cache-item by key => value + ttl
   *
   * @param string $key
   * @param mixed  $value
   * @param int    $ttl
   *
   * @return bool
   */
  public function setItem($key, $value, $ttl = 0)
  {
    $storeKey = $this->calculateStoreKey($key);
    $serialized = $this->serializer->serialize($value);

    if ($this->adapter instanceof iAdapter) {
      if ($ttl) {
        return $this->adapter->setExpired($storeKey, $serialized, $ttl);
      } else {
        return $this->adapter->set($storeKey, $serialized);
      }
    } else {
      return false;
    }
  }

  /**
   * remove cached-item
   *
   * @param String $key
   *
   * @return bool
   */
  public function removeItem($key)
  {
    if ($this->adapter instanceof iAdapter) {
      $storeKey = $this->calculateStoreKey($key);

      return $this->adapter->remove($storeKey);
    } else {
      return false;
    }
  }

  /**
   * check if cached-item exists
   *
   * @param String $key
   *
   * @return boolean
   */
  public function existsItem($key)
  {
    if ($this->adapter instanceof iAdapter) {
      $storeKey = $this->calculateStoreKey($key);

      return $this->adapter->exists($storeKey);
    } else {
      return false;
    }
  }

  /**
   * check for developer
   *
   * @return bool
   */
  private function checkForDev()
  {
    $return = false;

    if (function_exists('checkForDev')) {
      $return = checkForDev();
    } else {

      // for testing with dev-address
      $noDev = isset($_GET['noDev']) ? (int)$_GET['noDev'] : 0;
      $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;

      if
      (
          $noDev != 1
          &&
          (
              $remoteAddr == '127.0.0.1'
              || $remoteAddr == '::1'
              || PHP_SAPI == 'cli'
          )
      ) {
        $return = true;
      }
    }

    return $return;
  }

  /**
   * enable / disable the cache
   *
   * @param boolean $active
   */
  public function setActive($active)
  {
    $this->active = boolval($active);
  }



}