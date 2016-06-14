<?php

namespace vommuan\filemanager\models;

use Yii;
use yii\web\UploadedFile;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\imagine\Image;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\helpers\Inflector;
use vommuan\filemanager\Module;
use vommuan\filemanager\models\Owners;
use Imagine\Image\ImageInterface;

/**
 * This is the model class for table "{{%filemanager_mediafile}}".
 *
 * @property integer $id
 * @property string $filename
 * @property string $type
 * @property string $url
 * @property string $alt
 * @property integer $size
 * @property string $description
 * @property string $thumbs
 * @property integer $created_at
 * @property integer $updated_at
 * @property Owners[] $owners
 */
class Mediafile extends ActiveRecord
{
    private $_routes;
    private $_absolutePath;
    private $_structure;
    
    /**
     * @var array $routesConfig See routes in module config
     */
    public $routesConfig;
    public $thumbsConfig;
    public $rename;
    public $file;

    public static $imageFileTypes = [
		'image/gif',
		'image/jpeg',
		//'image/pjpeg',
		'image/png',
		//'image/svg+xml',
		//'image/tiff',
		//'image/vnd.microsoft.icon',
		//'image/vnd.wap.wbmp',
	];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%filemanager_mediafile}}';
    }
    
    public function init()
    {
		if (isset($this->routesConfig)) {
			$this->_routes = new Routes(['routes' => $this->routesConfig]);
		}
	}

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filename', 'type', 'url', 'size'], 'required'],
            [['url', 'alt', 'description', 'thumbs'], 'string'],
            [['created_at', 'updated_at', 'size'], 'integer'],
            [['filename', 'type'], 'string', 'max' => 255],
            [['file'], 'file']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Module::t('main', 'ID'),
            'filename' => Module::t('main', 'filename'),
            'type' => Module::t('main', 'Type'),
            'url' => Module::t('main', 'Url'),
            'alt' => Module::t('main', 'Alt attribute'),
            'size' => Module::t('main', 'Size'),
            'description' => Module::t('main', 'Description'),
            'thumbs' => Module::t('main', 'Thumbnails'),
            'created_at' => Module::t('main', 'Created'),
            'updated_at' => Module::t('main', 'Updated'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
            ]
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwners()
    {
        return $this->hasMany(Owners::className(), ['mediafile_id' => 'id']);
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            foreach ($this->owners as $owner) {
                $owner->delete();
            }
            
            return true;
        } else {
            return false;
        }
    }
	
	/**
	 * Create actual structure directory for upload original files
	 * @return void
	 */
	protected function createUploadDirectory()
	{
        if (! file_exists($this->_routes->absolutePath)) {
            return mkdir($this->_routes->absolutePath, 0777, true);
        } else {
			return true;
		}
	}
	
	/**
	 * Check if current file name is exists
	 * @param string $filename
	 * @return bool 
	 */
	protected function fileNameExists($filename)
	{
		$url = implode('/', [
			$this->_routes->structure,
			$filename,
		]);
		
		return (self::findByUrl($url)) ? true : false; // checks for existing url in db
	}
	
	/**
	 * Get unique file name with index. Used when current file name is exists
	 * @return string
	 */
	protected function getUniqueFileName()
	{
		$counter = 0;
		
        do {
            $filename = Inflector::slug($this->file->baseName) . $counter++ . '.' . $this->file->extension;
        } while ($this->fileNameExists($filename));
        
        return $filename;
	}
	
    /**
     * Save just uploaded file
     * @return bool
     */
    public function saveUploadedFile()
    {
        $this->createUploadDirectory();
        
        // get file instance
        $this->file = UploadedFile::getInstance($this, 'file');
        
        //if a file with the same name already exist append a number
        $filename = Inflector::slug($this->file->baseName) . '.' . $this->file->extension;
		if ($this->fileNameExists($filename)) {
			if (false === $this->rename) {
				return false;
			} else {
				$filename = $this->getUniqueFileName();
			}
		}
		
		// save original uploaded file
        $this->file->saveAs(
			implode('/', [
				$this->_routes->absolutePath,
				$filename,
			])
		);
        $this->filename = $filename;
        $this->type = $this->file->type;
        $this->size = $this->file->size;
        $this->url = implode('/', [
			$this->_routes->structure,
			$filename,
		]);;

        return $this->save();
    }
    
    /**
     * Generates thumb file name
     * @param int $width
     * @param int $height
     * @return string
     */
    protected function generateThumbFileName($width, $height) {
		return pathinfo($this->url, PATHINFO_FILENAME)
			. '-' . $width . 'x' . $height . '.'
			. pathinfo($this->url, PATHINFO_EXTENSION);
	}

    /**
     * Create thumbs for this image
     *
     * @param array $presets thumbs presets. See in module config
     * @return bool
     */
    public function createThumbs(array $presets)
    {
        $thumbs = [];
        
        Image::$driver = [Image::DRIVER_GD2, Image::DRIVER_GMAGICK, Image::DRIVER_IMAGICK];
        
        $originalFileName = implode('/', [
			$this->_routes->absolutePath,
			pathinfo($this->url, PATHINFO_BASENAME),
		]);

        foreach ($presets as $alias => $preset) {
            list ($width, $height) = $preset['size'];
            $mode = isset($preset['mode']) ? $preset['mode'] : ImageInterface::THUMBNAIL_OUTBOUND;
			
            Image::thumbnail($originalFileName,	$width, $height, $mode)->save(
				implode('/', [
					$this->_routes->absolutePath,
					$this->generateThumbFileName($width, $height),
				])
			);

            $thumbs[$alias] = implode('/', [
				$this->_routes->structure,
				$this->generateThumbFileName($width, $height),
			]);
        }

        $this->thumbs = serialize($thumbs);
        $this->detachBehavior('timestamp');

        // create default thumbnail
        $this->createDefaultThumb($this->_routes->routes);

        return $this->save();
    }

    /**
     * Create default thumbnail
     *
     * @param array $routes see routes in module config
     */
    public function createDefaultThumb(array $routes)
    {
        Image::$driver = [Image::DRIVER_GD2, Image::DRIVER_GMAGICK, Image::DRIVER_IMAGICK];

		$originalFileName = implode('/', [
			$this->_routes->absolutePath,
			pathinfo($this->url, PATHINFO_BASENAME),
		]);
		
        list ($width, $height) = Module::getDefaultThumbSize();
        
        Image::thumbnail($originalFileName, $width, $height, ImageInterface::THUMBNAIL_OUTBOUND)->save(
			implode('/', [
				$this->_routes->absolutePath,
				$this->generateThumbFileName($width, $height),
			])
		);
    }

    /**
     * Add owner to mediafiles table
     *
     * @param int $owner_id owner id
     * @param string $owner owner identification name
     * @param string $owner_attribute owner identification attribute
     * @return bool save result
     */
    public function addOwner($owner_id, $owner, $owner_attribute)
    {
        $mediafiles = new Owners();
        $mediafiles->mediafile_id = $this->id;
        $mediafiles->owner = $owner;
        $mediafiles->owner_id = $owner_id;
        $mediafiles->owner_attribute = $owner_attribute;

        return $mediafiles->save();
    }

    /**
     * Remove this mediafile owner
     *
     * @param int $owner_id owner id
     * @param string $owner owner identification name
     * @param string $owner_attribute owner identification attribute
     * @return bool delete result
     */
    public static function removeOwner($owner_id, $owner, $owner_attribute)
    {
        $mediafiles = Owners::findOne([
            'owner_id' => $owner_id,
            'owner' => $owner,
            'owner_attribute' => $owner_attribute,
        ]);

        if ($mediafiles) {
            return $mediafiles->delete();
        }

        return false;
    }

    /**
     * @return bool if type of this media file is image, return true;
     */
    public function isImage()
    {
        return in_array($this->type, self::$imageFileTypes);
    }

    /**
     * @param $baseUrl
     * @return string default thumbnail for image
     */
    public function getDefaultThumbUrl($baseUrl = '')
    {
        if ($this->isImage()) {
			list ($width, $height) = Module::getDefaultThumbSize();
            return implode('/', [
				pathinfo($this->url, PATHINFO_DIRNAME),
				$this->generateThumbFileName($width, $height),
			]);
        }
        
        return "{$baseUrl}/images/file.png";
    }
    
    /**
     * @return array thumbnails
     */
    public function getThumbs()
    {
        return unserialize($this->thumbs);
    }

    /**
     * @param string $alias thumb alias
     * @return string thumb url
     */
    public function getThumbUrl($alias)
    {
        $thumbs = $this->getThumbs();

        if ('original' === $alias) {
            return $this->url;
        }

        return ! empty($thumbs[$alias]) ? $thumbs[$alias] : '';
    }

    /**
     * Thumbnail image html tag
     *
     * @param string $alias thumbnail alias
     * @param array $options html options
     * @return string Html image tag
     */
    public function getThumbImage($alias, $options=[])
    {
        $url = $this->getThumbUrl($alias);

        if (empty($url)) {
            return '';
        }

        if (empty($options['alt'])) {
            $options['alt'] = $this->alt;
        }

        return Html::img($url, $options);
    }

    /**
     * @param Module $module
     * @return array images list
     */
    public function getImagesList(Module $module)
    {
        $thumbs = $this->getThumbs();
        $list = [];
        $originalImageSize = $this->getOriginalImageSize($module->routes);
        $list[$this->url] = Module::t('main', 'Original') . ' ' . $originalImageSize;

        foreach ($thumbs as $alias => $url) {
            $preset = $module->thumbs[$alias];
            $list[$url] = $preset['name'] . ' ' . $preset['size'][0] . ' × ' . $preset['size'][1];
        }
        
        return $list;
    }

    /**
     * Delete thumbnails for current image
     * @param array $routes see routes in module config
     */
    public function deleteThumbs(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);

        foreach ($this->getThumbs() as $thumbUrl) {
            unlink("{$basePath}/{$thumbUrl}");
        }

        unlink("{$basePath}/{$this->getDefaultThumbUrl()}");
    }

    /**
     * Delete file
     * @param array $routes see routes in module config
     * @return bool
     */
    public function deleteFile(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);
        return unlink("{$basePath}/{$this->url}");
    }

    /**
     * Creates data provider instance with search query applied
     * @return ActiveDataProvider
     */
    public function search()
    {
        $query = self::find()->orderBy('created_at DESC');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $dataProvider;
    }

    /**
     * @return int last changes timestamp
     */
    public function getLastChanges()
    {
        return ! empty($this->updated_at) ? $this->updated_at : $this->created_at;
    }

    /**
     * This method wrap getimagesize() function
     * @param array $routes see routes in module config
     * @param string $delimiter delimiter between width and height
     * @return string image size like '1366x768'
     */
    public function getOriginalImageSize(array $routes, $delimiter = ' × ')
    {
        $imageSizes = $this->getOriginalImageSizes($routes);
        return "{$imageSizes[0]}{$delimiter}{$imageSizes[1]}";
    }

    /**
     * This method wrap getimagesize() function
     * @param array $routes see routes in module config
     * @return array
     */
    public function getOriginalImageSizes(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);
        return getimagesize("{$basePath}/{$this->url}");
    }

    /**
     * @return string file size
     */
    public function getFileSize()
    {
        Yii::$app->formatter->sizeFormatBase = 1000;
        return Yii::$app->formatter->asShortSize($this->size, 0);
    }

    /**
     * Find model by url
     *
     * @param $url
     * @return static
     */
    public static function findByUrl($url)
    {
        return self::findOne(['url' => $url]);
    }

    /**
     * Search models by file types
     * @param array $types file types
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function findByTypes(array $types)
    {
        return self::find()->filterWhere(['in', 'type', $types])->all();
    }

    public static function loadOneByOwner($owner, $owner_id, $owner_attribute)
    {
        $owner = Owners::findOne([
            'owner' => $owner,
            'owner_id' => $owner_id,
            'owner_attribute' => $owner_attribute,
        ]);

        if ($owner) {
            return $owner->mediafile;
        }

        return false;
    }
}
