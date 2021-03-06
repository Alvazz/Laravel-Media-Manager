<?php

namespace alvazz\MediaManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
//use alvazz\PackageChangeLog\PackageChangeLogServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MediaManagerServiceProvider extends ServiceProvider
{
    protected $file;

    public function boot()
    {
         /**
         * Paginate a standard Laravel Collection.
         *
         * @param int $perPage
         * @param int $total
         * @param int $page
         * @param string $pageName
         * @return array
         */
        Collection::macro('paginate', function($perPage, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
            return new LengthAwarePaginator(
                $this->forPage($page, $perPage),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });
        
        
        $this->file = $this->app['files'];

        $this->packagePublish();
        $this->extraConfigs();
        $this->socketRoute();
        $this->viewComp();

        // append extra data
        if (!$this->app['cache']->store('file')->has('ct-mm')) {
            $this->autoReg();
        }
    }

    /**
     * publish package assets.
     *
     * @return [type] [description]
     */
    protected function packagePublish()
    {
        // config
        $this->publishes([
            __DIR__ . '/config' => config_path(),
        ], 'config');

        // database
        $this->publishes([
            __DIR__ . '/database' => storage_path('logs'),
        ], 'db');

        $this->publishes([
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ], 'migration');

        // resources
        $this->publishes([
            __DIR__ . '/resources/assets' => resource_path('assets/vendor/MediaManager'),
        ], 'assets');

        // trans
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'MediaManager');
        $this->publishes([
            __DIR__ . '/resources/lang' => resource_path('lang/vendor/MediaManager'),
        ], 'trans');

        // views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'MediaManager');
        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/MediaManager'),
        ], 'view');
    }

    protected function extraConfigs()
    {
        // database
        $db = storage_path('logs/MediaManager.sqlite');

        if ($this->file->exists($db)) {
            $this->app['config']->set('database.connections.mediamanager', [
                'driver'   => 'sqlite',
                'database' => $db,
            ]);
        }
    }

    protected function socketRoute()
    {
        Broadcast::channel('User.{id}.media', function ($user, $id) {
            return $user->id == $id;
        });
    }

    /**
     * share data with view.
     *
     * @return [type] [description]
     */
    protected function viewComp()
    {
        $data   = [];

        // base url
        $config = $this->app['config']->get('mediaManager');
        $url    = $this->app['filesystem']
            ->disk(array_get($config, 'storage_disk'))
            ->url('/');

        $data['base_url'] = preg_replace('/\/+$/', '/', $url);

        // upload panel bg patterns
        $pattern_path = public_path('assets/vendor/MediaManager/patterns');

        if ($this->file->exists($pattern_path)) {
            $patterns = collect(
                $this->file->allFiles($pattern_path)
            )->map(function ($item) {
                return preg_replace('/.*\/patterns/', '/assets/vendor/MediaManager/patterns', $item->getPathName());
            });

            $data['patterns'] = json_encode($patterns);
        }

        // share
        view()->composer('MediaManager::_manager', function ($view) use ($data) {
            $view->with($data);
        });
    }

    /**
     * autoReg package resources.
     *
     * @return [type] [description]
     */
    protected function autoReg()
    {
        // routes
        $route_file = base_path('routes/web.php');
        $search     = 'MediaManager';

        if ($this->checkExist($route_file, $search)) {
            $data = "\n// MediaManager\nalvazz\MediaManager\MediaRoutes::routes();";

            $this->file->append($route_file, $data);
        }

        // mix
        $mix_file = base_path('webpack.mix.js');
        $search   = 'MediaManager';

        if ($this->checkExist($mix_file, $search)) {
            $data =
<<<EOT

// MediaManager
mix.sass('resources/assets/vendor/MediaManager/sass/manager.scss', 'public/assets/vendor/MediaManager/style.css')
    .copyDirectory('resources/assets/vendor/MediaManager/dist', 'public/assets/vendor/MediaManager')
EOT;

            $this->file->append($mix_file, $data);
        }

        // run check once
        $this->app['cache']->store('file')->rememberForever('ct-mm', function () {
            return 'added';
        });
    }

    /**
     * [checkExist description].
     *
     * @param [type] $file   [description]
     * @param [type] $search [description]
     *
     * @return [type] [description]
     */
    protected function checkExist($file, $search)
    {
        return $this->file->exists($file) && !str_contains($this->file->get($file), $search);
    }

    /**
     * extra functionality.
     *
     * @return [type] [description]
     */
    public function register()
    {
        //$this->app->register(PackageChangeLogServiceProvider::class);
    }
}
