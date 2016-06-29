<?php
use dosamigos\fileupload\FileUploadUI;
use vommuan\filemanager\Module;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel vommuan\filemanager\models\MediaFile */
?>

<header id="header">
	<span class="glyphicon glyphicon-upload"></span>
	<?= Module::t('main', 'Upload manager') ?>
</header>

<div id="uploadmanager">
    <p>
		<?= Html::a(
			Html::tag('i', '', [
				'class' => 'glyphicon glyphicon-arrow-left'
			]) . ' ' . Module::t('main', 'Back to file manager'), [
				'file/filemanager'
			]
		) ?>
	</p>
    <?= FileUploadUI::widget([
        'model' => $model,
        'attribute' => 'file',
        'clientOptions' => [
            'autoUpload'=> Module::getInstance()->autoUpload,
        ],
        'clientEvents' => [
            'fileuploadsubmit' => "function (e, data) {
				data.formData = [{
					name: 'tagIds',
					value: $('#filemanager-tagIds').val()
				}]; 
			}",
        ],
        'url' => ['upload'],
        'gallery' => false,
    ]) ?>
</div>
