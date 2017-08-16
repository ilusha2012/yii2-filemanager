<?php

namespace ilusha2012\filemanager\assets;

use yii\web\AssetBundle;

class CropImageAsset extends AssetBundle
{
    public $sourcePath = '@filemanager/assets/module.blocks/cropper';
    
    public $css = [
        //'cropper.css',
    ];
    
    public $js = [
		'cropper.js',
    ];
    
    public $depends = [
        'ilusha2012\filemanager\assets\CropperAsset',
    ];
}