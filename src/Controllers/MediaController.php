<?php

namespace alvazz\MediaManager\Controllers;

use App\Http\Controllers\Controller;
use League\Flysystem\Plugin\ListWith;
use alvazz\MediaManager\Controllers\Moduels\Lock;
use alvazz\MediaManager\Controllers\Moduels\Move;
use alvazz\MediaManager\Controllers\Moduels\Utils;
use alvazz\MediaManager\Controllers\Moduels\Delete;
use alvazz\MediaManager\Controllers\Moduels\Rename;
use alvazz\MediaManager\Controllers\Moduels\Upload;
use alvazz\MediaManager\Controllers\Moduels\Download;
use alvazz\MediaManager\Controllers\Moduels\NewFolder;
use alvazz\MediaManager\Controllers\Moduels\GetContent;
use alvazz\MediaManager\Controllers\Moduels\Visibility;

class MediaController extends Controller
{
    use Utils,
        GetContent,
        Delete,
        Download,
        Lock,
        Move,
        Rename,
        Upload,
        NewFolder,
        Visibility;

    protected $baseUrl;
    protected $db;
    protected $fileChars;
    protected $fileSystem;
    protected $folderChars;
    protected $ignoreFiles;
    protected $LMF;
    protected $GFI;
    protected $sanitizedText;
    protected $storageDisk;
    protected $storageDiskInfo;
    protected $unallowedMimes;

    public function __construct()
    {
        $config                = app('config')->get('mediaManager');
        $this->fileSystem      = array_get($config, 'storage_disk');
        $this->ignoreFiles     = array_get($config, 'ignore_files');
        $this->fileChars       = array_get($config, 'allowed_fileNames_chars');
        $this->folderChars     = array_get($config, 'allowed_folderNames_chars');
        $this->sanitizedText   = array_get($config, 'sanitized_text');
        $this->unallowedMimes  = array_get($config, 'unallowed_mimes');
        $this->LMF             = array_get($config, 'last_modified_format');
        $this->GFI             = array_get($config, 'get_folder_info', true);

        $this->storageDisk     = app('filesystem')->disk($this->fileSystem);
        $this->storageDiskInfo = app('config')->get("filesystems.disks.{$this->fileSystem}");
        $this->baseUrl         = $this->storageDisk->url('/');
        $this->db              = app('db')->connection('mediamanager')->table('locked');

        $this->storageDisk->addPlugin(new ListWith());
    }

    /**
     * main view.
     *
     * @return [type] [description]
     */
    public function index()
    {
        return view('MediaManager::media');
    }

    public function globalSearch()
    {
        return collect($this->getFolderContent('/', true))->reject(function ($item) { // remove unwanted
            return preg_grep($this->ignoreFiles, [$item['path']]) || $item['type'] == 'dir';
        })->map(function ($file) {
            return $file = [
                'name'                   => $file['basename'],
                'type'                   => $file['mimetype'],
                'path'                   => $this->resolveUrl($file['path']),
                'dir'                    => $file['dirname'] != '' ? $file['dirname'] : '/',
                'last_modified_formated' => $this->getItemTime($file['timestamp']),
            ];
        })->values()->all();
    }
}
