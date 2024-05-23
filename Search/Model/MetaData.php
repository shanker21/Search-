<?php
namespace Apetito\Search\Model;

use Magento\Framework\Model\AbstractModel;
use Apetito\Search\Model\ResourceModel\MetaData as ResourceModel;

class MetaData extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
