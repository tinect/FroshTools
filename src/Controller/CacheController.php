<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheHelper;
use Frosh\Tools\Components\CacheRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools")
 */
class CacheController
{
    private string $cacheDir;

    private CacheRegistry $cacheRegistry;

    public function __construct(string $cacheDir, CacheRegistry $cacheRegistry)
    {
        $this->cacheDir = $cacheDir;
        $this->cacheRegistry = $cacheRegistry;
    }

    /**
     * @Route(path="/cache", methods={"GET"}, name="api.frosh.tools.cache.get")
     */
    public function cacheStatistics(): JsonResponse
    {
        $cacheFolder = dirname($this->cacheDir);
        $folders = scandir($cacheFolder, \SCANDIR_SORT_ASCENDING);

        $result = [];

        foreach ($folders as $folder) {
            if ($folder[0] === '.') {
                continue;
            }

            $cacheDir = $cacheFolder . '/' . $folder;
            $result[] = [
                'name' => $folder,
                'active' => $folder === basename($this->cacheDir),
                'size' => CacheHelper::getSize($cacheDir),
                'freeSpace' => disk_free_space($cacheDir),
                'type' => 'Filesystem',
            ];
        }

        foreach ($this->cacheRegistry->all() as $name => $adapter) {
            $result[] = [
                'name' => $name,
                'active' => true,
                'size' => $adapter->getSize(),
                'type' => $adapter->getType(),
                'freeSpace' => $adapter->getFreeSize(),
            ];
        }

        $this->calculateUsedPercentage($result);

        $activeColumns = array_column($result, 'active');
        $freeSpaceColumns = array_column($result, 'used');

        array_multisort($activeColumns, \SORT_DESC,
            $freeSpaceColumns, \SORT_DESC,
            $result);

        return new JsonResponse($result);
    }

    /**
     * @Route(path="/cache/{folder}", methods={"DELETE"}, name="api.frosh.tools.cache.clear")
     */
    public function clearCache(string $folder): JsonResponse
    {
        if ($this->cacheRegistry->has($folder)) {
            $this->cacheRegistry->get($folder)->clear();
        } else {
            CacheHelper::removeDir(dirname($this->cacheDir) . '/' . basename($folder));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function calculateUsedPercentage(array &$result): void
    {
        foreach ($result as &$cacheItem) {
            if ($cacheItem['freeSpace'] === null || $cacheItem['freeSpace'] <= 0 || $cacheItem['size'] < 0) {
                $cacheItem['used'] = 100;
                continue;
            }

            $cacheItem['used'] = 100 / $cacheItem['freeSpace'] * $cacheItem['size'];
        }

        unset($cacheItem);
    }
}
