<?php

namespace Apiato\Core\Loaders;

use Apiato\Core\Butlers\Facades\ShipButler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class RoutesLoaderTrait.
 *
 * @author  Mahmoud Zalt <mahmoud@zalt.me>
 */
trait RoutesLoaderTrait
{

    /**
     * Register all the containers routes files in the framework
     */
    public function runRoutesAutoLoader()
    {
        $containersPaths = ShipButler::getContainersPaths();
        $containersNamespace = ShipButler::getContainersNamespace();

        foreach ($containersPaths as $containerPath) {
            $this->loadApiContainerRoutes($containerPath, $containersNamespace);
            $this->loadWebContainerRoutes($containerPath, $containersNamespace);
        }
    }

    /**
     * Register the Containers API routes files
     *
     * @param $containerPath
     * @param $containersNamespace
     */
    private function loadApiContainerRoutes($containerPath, $containersNamespace)
    {
        // build the container api routes path
        $apiRoutesPath = $containerPath . '/UI/API/Routes';
        // build the namespace from the path
        $controllerNamespace = $containersNamespace . '\\Containers\\' . basename($containerPath) . '\\UI\API\Controllers';

        if (File::isDirectory($apiRoutesPath)) {
            $files = File::allFiles($apiRoutesPath);
            $files = array_sort($files, function ($file) {
                return $file->getFilename();
            });
            foreach ($files as $file) {
                $this->loadApiRoute($file, $controllerNamespace);
            }
        }
    }

    /**
     * Register the Containers WEB routes files
     *
     * @param $containerPath
     * @param $containersNamespace
     */
    private function loadWebContainerRoutes($containerPath, $containersNamespace)
    {
        // build the container web routes path
        $webRoutesPath = $containerPath . '/UI/WEB/Routes';
        // build the namespace from the path
        $controllerNamespace = $containersNamespace . '\\Containers\\' . basename($containerPath) . '\\UI\WEB\Controllers';

        if (File::isDirectory($webRoutesPath)) {
            $files = File::allFiles($webRoutesPath);
            foreach ($files as $file) {
                $this->loadWebRoute($file, $controllerNamespace);
            }
        }
    }

    /**
     * @param $file
     * @param $controllerNamespace
     */
    private function loadWebRoute($file, $controllerNamespace)
    {
        Route::group([
            'namespace'  => $controllerNamespace,
            'middleware' => ['web'],
        ], function ($router) use ($file) {
            require $file->getPathname();
        });
    }

    /**
     * @param $file
     * @param $controllerNamespace
     */
    private function loadApiRoute($file, $controllerNamespace)
    {
        Route::group([
            'namespace'  => $controllerNamespace,
            'middleware' => $this->getMiddlewares(),
            'domain'     => $this->getApiUrl(),
            'prefix'     => $this->getApiVersionPrefix($file),
        ], function ($router) use ($file) {
            require $file->getPathname();
        });
    }

    /**
     * @return  mixed
     */
    private function getApiUrl()
    {
        return Config::get('apiato.api.url');
    }

    /**
     * @param $file
     *
     * @return  string
     */
    private function getApiVersionPrefix($file)
    {
        return '/' . $this->getRouteFileVersionFromFileName($file);
    }

    /**
     * @return  array
     */
    private function getMiddlewares()
    {
        return array_filter([
            'api',
            $this->getRateLimitMiddleware(), // returns NULL if feature disabled. Null will be removed form the array.
        ]);
    }

    /**
     * @return  null|string
     */
    private function getRateLimitMiddleware()
    {
        $rateLimitMiddleware = null;

        if (Config::get('apiato.api.throttle.enabled')) {
            $rateLimitMiddleware = 'throttle:' . Config::get('apiato.api.throttle.attempts') . ',' . Config::get('apiato.api.throttle.expires');
        }

        return $rateLimitMiddleware;
    }

    /**
     * @param $file
     *
     * @return  mixed
     */
    private function getRouteFileVersionFromFileName($file)
    {
        $fileNameWithoutExtension = $this->getRouteFileNameWithoutExtension($file);

        $fileNameWithoutExtensionExploded = explode('.', $fileNameWithoutExtension);

        end($fileNameWithoutExtensionExploded);

        $apiVersion = prev($fileNameWithoutExtensionExploded); // get the array before the last one

        // Skip versioning the API's root route
        if ($apiVersion === 'ApisRoot') {
            $apiVersion = '';
        }

        return $apiVersion;
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo $file
     *
     * @return  mixed
     */
    private function getRouteFileNameWithoutExtension(SplFileInfo $file)
    {
        $fileInfo = pathinfo($file->getFileName());

        return $fileInfo['filename'];
    }

}
