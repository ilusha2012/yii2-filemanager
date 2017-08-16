<?php

use ilusha2012\filemanager\assets\FileGalleryAsset;
use yii\helpers\Html;

$bundle = FileGalleryAsset::register($this);

?>

<div class="gallery-items__item media-file" data-key="<?= $model->id;?>">
	<a href="#" class="media-file__link">
		<?= Html::img($model->getIcon($bundle->baseUrl) . '?' . $model->updated_at);?>
		<div class="checker">
			<span class="glyphicon glyphicon-ok"></span>
		</div>
	</a>
</div>