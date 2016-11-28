<?php

use vommuan\filemanager\assets\FileGalleryAsset;
use vommuan\filemanager\Module;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ListView;
use yii\widgets\Pjax;

$bundle = FileGalleryAsset::register($this);

$detailsUrl = Url::to([
	'/' . Module::getInstance()->uniqueId . '/file/details',
	'modal' => $modal,
]);

$detailsTarget = '#file-info_' . $modalId;

$insertFilesLoad = Url::to(['/' . Module::getInstance()->uniqueId . '/file/insert-files-load']);

$pagerParams = [
	'hideOnSinglePage' => false,
	'firstPageLabel' => '&#8676;',
	'prevPageLabel' => '&larr;',
	'nextPageLabel' => '&rarr;',
	'lastPageLabel' => '&#8677;',
];

?>

<div class="file-gallery gallery" data-details-url="<?= $detailsUrl;?>" data-details-target="<?= $detailsTarget;?>" data-insert-files-load="<?= $insertFilesLoad;?>" data-multiple="<?= $multiple;?>">
	<div class="row">
		<?php Pjax::begin([
			'linkSelector' => (!empty($modalId) ? '#' . $modalId . ' ' : '') . '.pagination a',
		]);?>
			<div class="col-xs-12 col-sm-8">
				<?= ListView::widget([
					'dataProvider' => $dataProvider,
					'emptyText' => $this->render('gallery__empty-text', [
						'modalId' => $modalId,
						'pagerParams' => $pagerParams,
					]),
					'layout' => $this->render('gallery__layout', ['modalId' => $modalId]),
					'pager' => $pagerParams,
					'itemOptions' => [
						'class' => 'col-xs-4 col-sm-2 gallery-items__item media-file',
					],
					'itemView' => function ($model, $key, $index, $widget) use ($bundle) {
						return $this->render('gallery-items__item', [
							'model' => $model,
							'bundle' => $bundle,
						]);
					},
				]);?>
			</div>
		<?php Pjax::end();?>
		<div class="col-xs-12 col-sm-4 file-info" id="<?= 'file-info_' . $modalId;?>"></div>
	</div>
</div>