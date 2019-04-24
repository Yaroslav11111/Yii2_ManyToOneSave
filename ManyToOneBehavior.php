<?php

namespace common\behaviors;

use snizhko\fileupload\Storage;
use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\di\Instance;
use yii\helpers\ArrayHelper;

/**
 * Class UploadBehavior
 * @author Eugene Terentev <eugene@terentev.net>
 */
class ManyToOneBehavior extends Behavior
{

    /**
     * @var ActiveRecord
     */
    public $owner;

    public $attribute;

    public $model;

    public $relationModel;

    public $modelScenario = 'default';

    public function events()
    {
        $multipleEvents = [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
        return $multipleEvents;
    }

    /**
     * @return array
     */
    public function fields()
    {
        $fields = [
            $this->attribute => $this->attribute,
        ];

        return $fields;
    }

    public function afterFind()
    {
        $models = $this->owner->{$this->relationModel};
        $fields = $this->fields();
        $data = [];
        foreach ($models as $k => $model) {
            /* @var $model \yii\db\BaseActiveRecord */
            foreach ($fields as $dataField => $modelAttribute) {
                $data[$dataField][] = $model->hasAttribute($modelAttribute)
                    ? ArrayHelper::getValue($model, $modelAttribute)
                    : null;
            }
        }
        $this->owner->{$this->attribute} = $data;
    }

    public function afterInsert()
    {
        if ($this->owner->{$this->attribute}) {
            $model = $this->owner->getRelation($this->relationModel);

            $this->saveRelationModel($model);
        }
    }


    public function afterUpdate()
    {
        $models = $this->owner->getRelation($this->relationModel)->all();
        foreach ($models as $model) {
            $model->delete();
        }
        $primaryModel = $this->owner->getRelation($this->relationModel);
        $this->saveRelationModel($primaryModel);
    }

    public function beforeDelete()
    {
        $models = $this->owner->getRelation($this->relationModel)->all();
        foreach ($models as $model) {
            $model->delete();
        }
    }

    /**
     * @return \yii\db\ActiveQuery|\yii\db\ActiveQueryInterface
     */
    protected function getModelRelation()
    {
        return $this->owner->getRelation($this->relationModel);
    }

    /**
     * @param $model \yii\db\ActiveRecord
     * @param $data
     * @return \yii\db\ActiveRecord
     */
    protected function loadModel(&$model, $data)
    {
        $attributes = array_flip($model->attributes());
        foreach ($this->fields() as $dataField => $modelField) {
            if ($modelField && array_key_exists($modelField, $attributes)) {
                $model->{$modelField} = ArrayHelper::getValue($data, $dataField);
            }
        }
        return $model;
    }

    protected function getAttributeField($type)
    {
        return ArrayHelper::getValue($this->fields(), $type, false);
    }

    public function validateRelationModel()
    {
        $this->loadModel($this->owner, $this->owner->{$this->attribute});
    }

    protected function saveRelationModel($primaryModel)
    {
        foreach ($this->owner->{$this->attribute} as $key => $attr) {
            $modelRelation = new $primaryModel->modelClass;
            if ($modelRelation->hasProperty($this->attribute)) {
                $modelRelation->{$this->attribute} = $attr;
                $this->owner->link($this->relationModel, $modelRelation);
            }
        }
    }
}
