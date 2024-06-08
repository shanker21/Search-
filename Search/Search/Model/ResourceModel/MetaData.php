<?php
namespace Apetito\Search\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MetaData extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('cms_categories_data', 'id');
    }
}
