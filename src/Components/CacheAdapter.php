<?php declare(strict_types=1);

namespace Frosh\Tools\Components;

use Shopware\Storefront\Framework\Cache\CacheDecorator;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

class CacheAdapter
{
    /**
     * @var AdapterInterface
     */
    private $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $this->getCacheAdapter($adapter);
    }

    public function getSize(): int
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
                return $this->getRedis($this->adapter)->info()['used_memory'];
            case $this->adapter instanceof FilesystemAdapter:
                return CacheHelper::getSize($this->getPathFromFilesystemAdapter($this->adapter));
            case $this->adapter instanceof ArrayAdapter:
                return 0;
        }

        return 0;
    }

    public function getFreeSize(): int
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
                $info = $this->getRedis($this->adapter)->info();
                $totalMemory = $info['maxmemory'] ?? $info['total_system_memory'];

                return $totalMemory - $info['used_memory'];
            case $this->adapter instanceof FilesystemAdapter:
                return (int) disk_free_space($this->getPathFromFilesystemAdapter($this->adapter));
            case $this->adapter instanceof ArrayAdapter:
                return 0;
        }
    }

    public function clear(): void
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
                $this->getRedis($this->adapter)->flushDB();
                break;
            case $this->adapter instanceof FilesystemAdapter:
                CacheHelper::removeDir($this->getPathFromFilesystemAdapter($this->adapter));
                break;
            case $this->adapter instanceof ArrayAdapter:
                $this->adapter->clear();

                return;
        }
    }

    public function getType(): string
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
                return 'Redis ' . $this->getRedis($this->adapter)->info()['redis_version'];
            case $this->adapter instanceof FilesystemAdapter:
                return 'Filesystem';
            case $this->adapter instanceof ArrayAdapter:
                return 'Array';
        }

        return '';
    }

    private function getCacheAdapter(AdapterInterface $adapter): AdapterInterface
    {
        if ($adapter instanceof CacheDecorator || $adapter instanceof \Shopware\Core\Framework\Adapter\Cache\CacheDecorator) {
            // Do not declare function as static
            $func = \Closure::bind(function () use ($adapter) {
                return $adapter->decorated;
            }, $adapter, \get_class($adapter));

            return $this->getCacheAdapter($func());
        }

        if ($adapter instanceof TagAwareAdapter || $adapter instanceof TraceableAdapter) {
            // Do not declare function as static
            $func = \Closure::bind(function () use ($adapter) {
                return $adapter->pool;
            }, $adapter, \get_class($adapter));

            return $this->getCacheAdapter($func());
        }

        return $adapter;
    }

    private function getRedis(AdapterInterface $adapter): ?\Redis
    {
        $redisProxyGetter = \Closure::bind(function () use ($adapter) {
            return $adapter->redis;
        }, $adapter, \get_class($adapter));
        $redisProxy = $redisProxyGetter($adapter);

        $redisGetter = \Closure::bind(function () use ($redisProxy) {
            return $redisProxy->redis;
        }, $redisProxy, \get_class($redisProxy));

        return $redisGetter($adapter);
    }

    private function getPathFromFilesystemAdapter(FilesystemAdapter $adapter): string
    {
        $getter = \Closure::bind(function () use ($adapter) {
            return $adapter->directory;
        }, $adapter, \get_class($adapter));

        return $getter($adapter);
    }
}
