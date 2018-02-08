<?php

namespace Phug\Renderer\Partial;

trait CacheTrait
{
    use AdapterTrait;

    /**
     * @return \Phug\Renderer\CacheInterface
     */
    private function getCacheAdapter()
    {
        $this->expectCacheAdapter();

        return $this->getAdapter();
    }

    /**
     * Cache a template file in the cache directory.
     * Returns true if the cache is up to date and cache not change,
     * else returns the bytes written in the cache file or false if a
     * failure occurred.
     *
     * @param string $path
     * @param bool   $forceSave save even if the cache is up to date.
     *
     * @return bool|int
     */
    public function cacheFile($path, $forceSave = false)
    {
        return $this->getCacheAdapter()->cacheFile($path, $forceSave);
    }

    /**
     * Cache all templates in a directory in the cache directory you specified with the cache_dir option.
     * You should call after deploying your application in production to avoid a slower page loading for the first
     * user.
     *
     * @param $directory
     *
     * @return array
     */
    public function cacheDirectory($directory)
    {
        return $this->getCacheAdapter()->cacheDirectory($directory);
    }
}
