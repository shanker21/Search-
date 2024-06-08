<?php
namespace Apetito\Search\Model\ResourceModel\MetaData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Apetito\Search\Model\MetaData as Model;
use Apetito\Search\Model\ResourceModel\MetaData as ResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'apetito_search_metadata_collection';
    protected $_eventObject = 'metadata_collection';

    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
