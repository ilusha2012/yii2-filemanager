<?php
namespace zozoh94\filemanager\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use zozoh94\filemanager\Module;
use zozoh94\filemanager\models\handlers\HandlerFactory;

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
 * @property integer $created_at
 * @property integer $updated_at
 * @property Owner[] $owners
 * @property Thumbnail[] $thumbnails
 */
class MediaFile extends ActiveRecord
{
	/**
	 * @var integer Rotate angle for images
	 */
	public $rotate = 0;
	
	/**
	 * @var double X coordinate for left high corner of cropping area
	 */
	public $cropX = 0;
	
	/**
	 * @var double Y coordinate for left high corner of cropping area
	 */
	public $cropY = 0;
	
	/**
	 * @var double Width of cropping area
	 */
	public $cropWidth;
	
	/**
	 * @var double Height of cropping area
	 */
	public $cropHeight;
	
	/**
	 * @var zozoh94\filemanager\models\handlers\BaseHandler or child class
	 */
	protected $handler;
	
	/**
	 * @var yii\web\UploadedFile uploaded file
	 */
	public $file;
	
	/**
	 * Initialization handler of file
	 */
	protected function initHandler()
	{
		if (isset($this->file)) {
			$this->type = $this->file->type;
		}
		
		$this->handler = HandlerFactory::getHandler($this);
	}
	
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->initHandler();
	}
	
	/**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%filemanager_mediafile}}';
    }
    
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
			'timestamp' => [
				'class' => TimestampBehavior::className(),
				'skipUpdateOnClean' => false,
			],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filename', 'type', 'url', 'size'], 'required'],
            [['filename', 'type'], 'string', 'max' => 255],
            [['url'], 'string'],
            [['alt'], 'string', 'max' => 200],
			[['description'], 'string', 'max' => 1000],
            [['size'], 'integer'],
            [['rotate'], 'integer', 'min' => -360, 'max' => 360],
            [['cropX', 'cropY'], 'number', 'min' => 0],
			[['cropWidth', 'cropHeight'], 'number', 'min' => 1],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'filename' => Module::t('main', 'File name'),
            'type' => Module::t('main', 'Type'),
            'url' => Module::t('main', 'Url'),
            'alt' => Module::t('main', 'Description'),
            'size' => Module::t('main', 'Size'),
            // 'description' => Module::t('main', 'Description'),
            'created_at' => Module::t('main', 'Created at'),
            'updated_at' => Module::t('main', 'Updated at'),
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwner()
    {
        return $this->hasOne(Owner::className(), ['mediafile_id' => 'id']);
    }
    
    /**
     * Add owner for mediafile
     */
    protected function addOwner()
    {
		if (Yii::$app->user->isGuest) {
			return false;
		}
		
		$owner = new Owner([
			'user_id' => Yii::$app->user->id,
			'mediafile_id' => $this->id,
		]);
		
		$owner->save();
	}
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getThumbnails()
    {
		return $this->hasMany(Thumbnail::className(), ['mediafile_id' => 'id']);
	}
    
    /**
	 * Get icon url
	 * 
	 * @param string $baseUrl asset's base url
	 * @return string
	 */
	public function getIcon($baseUrl)
	{
		return $this->handler->getIcon($baseUrl);
	}
	
	/**
	 * Get file size
	 * 
	 * @return string size in KB
	 */
	public function getFileSize()
	{
		Yii::$app->formatter->sizeFormatBase = 1000;
		
		return Yii::$app->formatter->asShortSize($this->size, 0);
	}
	
	/**
	 * Get base file type from MIME-type (saved in database)
	 * 
	 * @return string
	 */
	public function getBaseType()
	{
		return substr($this->type, 0, strpos($this->type, '/'));
	}
	
	/**
	 * Get file width x height sizes
	 * 
	 * @param string $delimiter delimiter between width and height
     * @param string $format see [[ImageThumbnail::getSizes()]] for detailed documentation
     * @return string image size like '1366x768'
     */
	public function getSizes($delimiter = 'x', $format = '{w}{d}{h}')
	{
		if ('image' != $this->baseType) {
			return false;
		}
		
		return $this->handler->getSizes($delimiter, $format);
	}
	
	/**
	 * Get one variant of this file
	 * 
	 * @param string $alias alias of file variant
	 * @return string file url
	 */
	public function getFileVariant($alias = 'origin')
	{
		if ('origin' == $alias) {
			return Yii::getAlias('@web/' . $this->url);
		} else {
			return $this->handler->getVariant($alias);
		}
	}
	
	/**
	 * Get list variants of one file. For example, image variants are thumbs files.
	 * 
	 * @return array paths to files
	 * ```
	 * [
	 *     0 => [
	 *         'alias' => 'alias_1',
	 *         'label' => 'label_1',
	 *         'url' => 'url_1',
	 *     ],
	 *     1 => [
	 *         'alias' => 'alias_2',
	 *         'label' => 'label_2',
	 *         'url' => 'url_2',
	 *     ],
	 * ]
	 * ```
	 * or formated array for using in drop down list
	 * ```
	 * [
	 *     'url_1' => 'label_1',
	 *     'url_2' => 'label_2',
	 * ]
	 * ```
	 */
	public function getFileVariants($dropDown = false)
	{
		if ('image' != $this->baseType) {
			$variants = [
				'alias' => 'origin',
				'label' => Module::t('main', 'Original'),
				'url' => $this->getFileVariant(),
			];
		} else {
			$variants = $this->handler->getVariantsList();
		}
		
		if ($dropDown) {
			return $this->handler->dropDownFormatter($variants);
		} else {
			return $variants;
		}
	}
	
	/**
	 * Remove old file's variants and generate new
	 */
	public function refreshFileVariants()
	{
		if ('image' != $this->baseType) {
			return false;
		}
		
		$this->handler->refreshFileVariants();
	}
    
    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
		if (isset($this->file)) {
			return $this->handler->beforeValidate();
		} else {
			return true;
		}
	}
    
    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
		if (parent::beforeSave($insert)) {
			return $this->handler->beforeSave($insert);
		} else {
			return false;
		}
	}

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            if (Module::getInstance()->rbac && !Yii::$app->user->can('filemanagerManageFiles') && Yii::$app->user->can('filemanagerManageOwnFiles')) {
				if (! isset($this->owner) || $this->owner->user_id != Yii::$app->user->id) {
					return false;
				}
			}
            
            if (null !== ($owner = $this->owner)) {
				$owner->delete();
			}
			
			$this->handler->delete();
            
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * @inheritdoc
     */
	public function afterSave($insert, $changedAttributes)
	{
		parent::afterSave($insert, $changedAttributes);
		
		$this->handler->afterSave($insert);
		
		if ($insert && !empty(Yii::$app->user->id)) {
			$this->addOwner();
		}
	}
    
    /**
     * @inheritdoc
     */
	public function afterDelete()
	{
		parent::afterDelete();
		// Mediafile's owner will be removed automatically by database event 'ON DELETE CASCADE'
	}
	
	/**
     * @inheritdoc
     */
    public function afterFind()
    {
		$this->initHandler();
	}
	
	/**
     * @return int last changes timestamp
     */
    public function getLastChanges()
    {
        return !empty($this->updated_at) ? $this->updated_at : $this->created_at;
    }
}