<?php

namespace Eva\EvaEngine\Service;

use Eva\EvaEngine\Exception;
use Phalcon\Session\AdapterInterface as SessionInterface;
use Phalcon\DI\InjectionAwareInterface;

class TokenStorage implements SessionInterface, InjectionAwareInterface
{
    protected $storage;

    protected $tokenId;

    protected $options;

    protected $lifetime;

    public function getId()
    {
        if ($this->tokenId) {
            return $this->tokenId;
        }
        $request = $this->getDI()->getRequest();
        $token = $request->getQuery('api_key');
        //$token = $request->getHeader('Authorization');
        if ($token) {
            return $this->tokenId = $token;
        } else {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            return $this->tokenId = 'ip' . ip2long($ip);
        }
    }

    public function setId($id)
    {
        $this->tokenId = $id;
        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $defaultOptions = array(
            'uniqueId' => 'evaengine',
            'frontend' => array(
                'adapter' => 'Json',
                'options' => array(),
            ),
            'backend' => array(
                'adapter' => 'File',
                'options' => array(),
            ),
        );
        $this->options = $options = array_merge($defaultOptions, $options);

        $adapterMapping = array(
            'apc' => 'Phalcon\Cache\Backend\Apc',
            'file' => 'Phalcon\Cache\Backend\File',
            'libmemcached' => 'Phalcon\Cache\Backend\Libmemcached',
            'memcache' => 'Phalcon\Cache\Backend\Memcache',
            'memory' => 'Phalcon\Cache\Backend\Memory',
            'mongo' => 'Phalcon\Cache\Backend\Mongo',
            'xcache' => 'Phalcon\Cache\Backend\Xcache',
            'redis' => 'Phalcon\Cache\Backend\Redis',
            'wincache' => 'Phalcon\Cache\Backend\Wincache',
            'base64' => 'Phalcon\Cache\Frontend\Base64',
            'data' => 'Phalcon\Cache\Frontend\Data',
            'igbinary' => 'Phalcon\Cache\Frontend\Igbinary',
            'json' => 'Phalcon\Cache\Frontend\Json',
            'none' => 'Phalcon\Cache\Frontend\None',
            'output' => 'Phalcon\Cache\Frontend\Output',
        );

        $frontCacheClassName = strtolower($options['frontend']['adapter']);
        if (!isset($adapterMapping[$frontCacheClassName])) {
            throw new Exception\RuntimeException(sprintf('No frontend cache adapter found by %s', $frontCacheClassName));
        }
        $frontCacheClass = $adapterMapping[$frontCacheClassName];
        $frontCache = new $frontCacheClass($options['frontend']['options']);

        $backendCacheClassName = strtolower($options['backend']['adapter']);
        if (!isset($adapterMapping[$backendCacheClassName])) {
            throw new Exception\RuntimeException(sprintf('No backend cache adapter found by %s', $backendCacheClassName));
        }
        $backendCacheClass = $adapterMapping[$backendCacheClassName];
        $storage = new $backendCacheClass($frontCache, array_merge(
            array(
                'prefix' => $options['uniqueId'],
            ),
            $options['backend']['options']
        ));
        $this->storage = $storage;
        return $this;
    }

    public function get($key, $defaultValue = null)
    {
        return $this->storage->get($this->getId() . $key);
    }

    public function set($key, $value)
    {
        return $this->storage->save($this->getId() . $key, $value);
    }

    public function has($key)
    {
        return $this->storage->exists($this->getId() . $key);
    }

    public function remove($key)
    {
        return $this->storage->delete($this->getId(). $key);
    }

    public function destroy($id = null)
    {
        return $this->storage->flush();
    }

    public function start()
    {
        return $this;
    }

    public function isStarted()
    {
        return true;
    }

    public function getDI()
    {
        return $this->di;
    }

    public function setDI($di)
    {
        $this->di = $di;
        return $this;
    }

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }
}