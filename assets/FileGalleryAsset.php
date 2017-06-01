<?php
namespace zozoh94\filemanager\assets;

use yii\web\AssetBundle;

class FileGalleryAsset extends AssetBundle
{
    public $sourcePath = '@filemanager/assets/module.blocks/file-gallery';
    
    public $css = [
        'file-gallery.css',
    ];
    
    public $js = [
		'file-gallery.js',
    ];
    
    public $depends = [
        'zozoh94\filemanager\assets\GalleryPagerAsset',
        'zozoh94\filemanager\assets\GallerySummaryAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}