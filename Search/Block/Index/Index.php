<?php
namespace Apetito\Search\Block\Index;

use Magento\Framework\View\Element\Template;
use Apetito\Search\Model\ResourceModel\MetaData\CollectionFactory as MetaDataCollectionFactory;

class Index extends Template
{
    protected $metaDataCollectionFactory;

    public function __construct(
        Template\Context $context,
        MetaDataCollectionFactory $metaDataCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->metaDataCollectionFactory = $metaDataCollectionFactory;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getMetaData()
    {
        //get base url 
        $baseurl = $this->_storeManager->getStore()->getBaseUrl();
        //Create to collection data
        $collection = $this->metaDataCollectionFactory->create();
         //get the collection items value
        $items = $collection->getItems();
        
        $metaDataArray = [];
        foreach ($items as $item) {
            $label = $item->getMetaTitle() ?: $baseurl . $item->getUrl(); 
            $description = $item->getMetaDescription();
            $metaDataArray[] = [
                'label' => $label,
                'value' => $baseurl . $item->getUrl(),
                'description' => $description, 
                'searchData' => $label . ' ' . $description 
            ];
        }
        
        return json_encode($metaDataArray);
    }
}
