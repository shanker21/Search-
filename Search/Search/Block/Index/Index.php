<?php
namespace Apetito\Search\Block\Index;

use Magento\Framework\View\Element\Template;
use Apetito\Search\Model\ResourceModel\MetaData\CollectionFactory as MetaDataCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;


class Index extends Template
{
    protected $metaDataCollectionFactory;
    protected $scopeConfig;

    public function __construct(
        Template\Context $context,
        MetaDataCollectionFactory $metaDataCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->metaDataCollectionFactory = $metaDataCollectionFactory;
        $this->_storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function getMetaData()
    {
        $baseurl = $this->_storeManager->getStore()->getBaseUrl();
        $collection = $this->metaDataCollectionFactory->create();
        $items = $collection->getItems();
        
        $metaDataArray = [];
        foreach ($items as $item) {
            $label = $item->getMetaTitle(); 
            $name = $item->getTitle() ? $item->getTitle() : $item->getMetaTitle();
            $description = $item->getMetaDescription();
            $title = $item->getTitle();
            $url = $baseurl . $item->getUrl();
            $label = @html_entity_decode($label);
            $name = @html_entity_decode($name);
            $metaDataArray[] = [
                'label' => $name,
                'value' => $baseurl . $item->getUrl(),
                'title' => $description,
                'description' => $description, 
                'searchData' => $label . ' ' . $description . ' ' . $title . ' '. $url
            ];
        }
        return json_encode($metaDataArray);
    }

    public function isMetaDescriptionEnable() {

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE; 
        $showMetaDesc = $this->scopeConfig->getValue('Apetito_searchconfig/search_result/active', $storeScope);
        if ($showMetaDesc) {
            return true;
        }
        return false;
    }
}
