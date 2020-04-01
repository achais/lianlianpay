<?php

namespace Achais\LianLianPay;

use Achais\LianLianPay\Core\AbstractAPI;
use Achais\LianLianPay\Core\AccessToken;
use Achais\LianLianPay\Core\Http;
use Achais\LianLianPay\Support\Log;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\Cache as CacheInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Application
 *
 * @property \Achais\LianLianPay\Core\AccessToken $access_token
 * @property \Achais\LianLianPay\InstantPay\InstantPay $instantPay
 *
 * @package Achais\LianLianPay
 */
class LianLianPay extends Container
{
    protected $providers = [
        Foundation\ServiceProviders\InstantPayProvider::class,
    ];

    public function __construct(array $config = array())
    {
        parent::__construct($config);

        $this['config'] = function () use ($config) {
            return new Foundation\Config($config);
        };

        $this->registerBase();
        $this->registerProviders();
        $this->initializeLogger();

        Http::setDefaultOptions($this['config']->get('guzzle', ['timeout' => 5.0]));

        $this->logConfiguration($config);
    }

    public function logConfiguration($config)
    {
        $config = new Foundation\Config($config);

        $keys = ['instant_pay.oid_partner', 'instant_pay.private_key', 'instant_pay.public_key'];
        foreach ($keys as $key) {
            !$config->has($key) || $config[$key] = '***' . substr($config[$key], -5);
        }

        Log::debug('Current config:', $config->toArray());
    }

    public function addProvider($provider)
    {
        array_push($this->providers, $provider);
        return $this;
    }

    public function setProviders(array $providers)
    {
        $this->providers = [];

        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    public function getProviders()
    {
        return $this->providers;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    private function registerProviders()
    {
        foreach ($this->providers as $provider) {
            $this->register(new $provider());
        }
    }

    private function registerBase()
    {
        $this['request'] = function () {
            return Request::createFromGlobals();
        };

        if (!empty($this['config']['cache']) && $this['config']['cache'] instanceof CacheInterface) {
            $this['cache'] = $this['config']['cache'];
        } else {
            $this['cache'] = function () {
                return new FilesystemCache(sys_get_temp_dir());
            };
        }
    }

    private function initializeLogger()
    {
        if (Log::hasLogger()) {
            return;
        }

        $logger = new Logger('lianlianpay');

        if (!$this['config']['debug'] || defined('PHPUNIT_RUNNING')) {
            $logger->pushHandler(new NullHandler());
        } elseif ($this['config']['log.handler'] instanceof HandlerInterface) {
            $logger->pushHandler($this['config']['log.handler']);
        } elseif ($logFile = $this['config']['log.file']) {
            try {
                $logger->pushHandler(new StreamHandler(
                        $logFile,
                        $this['config']->get('log.level', Logger::WARNING),
                        true,
                        $this['config']->get('log.permission', null))
                );
            } catch (\Exception $e) {
            }
        }

        Log::setLogger($logger);
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (is_callable([$this['fundamental.api'], $method])) {
            return call_user_func_array([$this['fundamental.api'], $method], $args);
        }

        throw new \Exception("Call to undefined method {$method}()");
    }
}