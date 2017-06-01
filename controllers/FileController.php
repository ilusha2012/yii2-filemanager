<?php

namespace zozoh94\filemanager\controllers;

use zozoh94\filemanager\assets\FileGalleryAsset;
use zozoh94\filemanager\models\MediaFile;
use zozoh94\filemanager\models\MediaFileSearch;
use zozoh94\filemanager\models\forms\EditImageForm;
use zozoh94\filemanager\models\forms\UpdateFileForm;
use zozoh94\filemanager\models\forms\UploadFileForm;
use zozoh94\filemanager\Module;
use Yii;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

class FileController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'update' => ['post'],
                ],
            ],
        ];
    }
    
    protected function rbacCheck()
    {
		if (Module::getInstance()->rbac && (!Yii::$app->user->can('filemanagerManageFiles') && !Yii::$app->user->can('filemanagerManageOwnFiles'))) {
			throw new ForbiddenHttpException(Module::t('main', 'Permission denied.'));
		}
	}

    public function actionIndex()
    {
        $this->rbacCheck();
        
        return $this->render('index');
    }
    
    /**
     * Ajax responce for pagination update
     */
    protected function getPagination()
    {
		$dataProvider = (new MediaFileSearch())->search();
        $dataProvider->prepare();
        
        $begin = $dataProvider->pagination->page * $dataProvider->pagination->pageSize + 1;
        $end = $begin + $dataProvider->count - 1;
        
		if ($begin > $end) {
			$begin = $end;
		}
        
        return [
			'begin'      => $begin,
			'end'        => $end,
			'pageCount'  => $dataProvider->pagination->pageCount,
			'totalCount' => $dataProvider->totalCount,
		];
	}
    
    /**
     * 
     */
    public function actionPage($page = 1) {
		Yii::$app->response->format = Response::FORMAT_JSON;
		
		return [
			'items' => $this->renderAjax('page', [
				'dataProvider' => (new MediaFileSearch())->search(),
			]),
			'pagination' => $this->getPagination(),
		];
	}
    
    /**
     * Upload file from next page
     */
    public function actionNextPageFile()
    {
		$model = (new MediaFileSearch())->searchLastOnPage(Yii::$app->request->post('page'));
		
		Yii::$app->response->format = Response::FORMAT_JSON;
		
		if (isset($model)) {
			return [
				'success' => true,
				'html' => $this->renderPartial('media-file', [
					'model' => $model,
				]),
			];
		} else {
			return [
				'success' => false,
				'html' => '',
			];
		}
	}
    
    /**
     * Provides upload file
     * @return mixed
     */
    public function actionUpload()
    {
        $this->rbacCheck();
        
        $mediaFile = (new UploadFileForm())->getHandler();
        
        $response = [];
        
        try {
			if (!$mediaFile->save()) {
				throw new UserException(Module::t('main', 'This file already exists.'));
			}
			
			$bundle = FileGalleryAsset::register($this->view);
			
			$response['files'][] = [
				'id'           => $mediaFile->id,
				'thumbnailUrl' => $mediaFile->getIcon($bundle->baseUrl),
				'pagination'   => $this->getPagination(),
			];
		} catch (UserException $e) {
			$response['files'][] = [
				'name'  => Inflector::slug($mediaFile->file->baseName) . '.' . $mediaFile->file->extension,
				'size'  => $mediaFile->file->size,
				'error' => $e->getMessage(),
			];
		} finally {
			Yii::$app->response->format = Response::FORMAT_JSON;
		
			return $response;
		}
    }

    /**
     * Updated mediafile by id
     * 
     * @param $id
     * @return array
     */
    public function actionUpdate($id)
    {
        $this->rbacCheck();
        
        $model = new UpdateFileForm([
			'mediaFile' => MediaFile::findOne($id),
        ]);
        
        $message = Module::t('main', 'Changes not saved');
		
        if ($model->load(Yii::$app->request->post()) && $model->update()) {
            $message = Module::t('main', 'Changes saved');
        }

        Yii::$app->session->setFlash('mediaFileUpdateResult', $message);

        return $this->renderAjax('details', [
            'model' => $model,
        ]);
    }

    /**
     * Delete model with files
     * 
     * @param $id
     * @return array
     */
    public function actionDelete($id)
    {
        $this->rbacCheck();
        
        $model = MediaFile::findOne($id);
		
		$model->delete();
		
		Yii::$app->response->format = Response::FORMAT_JSON;
		
        return [
			'success' => 'true',
			'id' => $id,
			'pagination' => $this->getPagination(),
		];
    }

    /** 
     * Render file information
     * 
     * @param int $id
     * @return string
     */
    public function actionDetails($id)
    {
        $this->rbacCheck();
        
        $model = new UpdateFileForm([
			'mediaFile' => MediaFile::findOne($id)
        ]);
        
        return $this->renderAjax('details', [
            'model' => $model,
        ]);
    }
    
    /**
     * 
     */
    public function actionInsertFilesLoad()
    {
		$filesId = Json::decode(ArrayHelper::getValue(Yii::$app->request->post(), 'selectedFiles', '[]'));
		$imageOptions = ArrayHelper::getValue(Yii::$app->request->post(), 'imageOptions', []);
		
		return $this->renderAjax('insert-files-load', [
			'mediaFiles' => MediaFile::findAll($filesId),
			'imageOptions' => $imageOptions,
		]);
	}
	
	/**
	 * Loading edit image form
	 * 
	 * @param integer $id Media file identificator
	 */
	public function actionEdit($id)
	{
		$this->rbacCheck();
		
		$model = new EditImageForm([
			'mediaFile' => MediaFile::findOne($id),
		]);
		
		if ($model->load(Yii::$app->request->post()) && $model->edit()) {
            return '';
        }
		
		return $this->renderAjax('edit', [
			'model' => $model,
		]);
	}
	
	/**
	 * Get thumbnail's url by media file id
	 * 
	 * @return string
	 */
	public function actionThumbUrl()
	{
		$mediaFileId = Yii::$app->request->post('id', null);
		
		if (empty($mediaFileId)) {
			return '';
		}
		
		$mediaFile = MediaFile::findOne($mediaFileId);
		
		if (!isset($mediaFile)) {
			return '';
		}
		
		$bundle = FileGalleryAsset::register($this->view);
		
		return $mediaFile->getIcon($bundle->baseUrl) . '?' . $mediaFile->updated_at;
	}
}
