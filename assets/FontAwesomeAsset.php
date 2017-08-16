<?php
namespace ilusha2012\filemanager\assets;

use yii\web\AssetBundle;

class FontAwesomeAsset extends AssetBundle
{
    public $sourcePath = '@vendor/fortawesome/font-awesome';
    
    public $css = [
        'css/font-awesome.min.css',
    ];
}