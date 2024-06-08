<?php

namespace Apetito\Search\Cron;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Apetito\Search\Model\MetaDataFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Apetito\Search\Model\ResourceModel\MetaData as MetaDataResource;
use Psr\Log\LoggerInterface;

class MetaData
{
    protected $categoryCollectionFactory;
    protected $pageRepository;
    protected $url;
    protected $metadataFactory;
    protected $resourceConnection;
    protected $searchCriteriaBuilder;
    protected $logger;
    protected $scopeConfig;
    protected $storeManager;
    protected $metadataResource;

    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        PageRepositoryInterface $pageRepository,
        UrlInterface $url,
        MetaDataFactory $metadataFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        MetaDataResource $metadataResource
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->pageRepository = $pageRepository;
        $this->url = $url;
        $this->metadataFactory = $metadataFactory;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->metadataResource = $metadataResource;
    }
    

    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/metacron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("cron running");
        //connection to the database
        $connection = $this->resourceConnection->getConnection();
        //get store and parent from admin
        $storeCode = $this->scopeConfig->getValue('Apetito_searchconfig/search_store/store_view', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $logger->info('Retrieved Store Code: ' . $storeCode);
        
        $parentId = $this->scopeConfig->getValue('Apetito_searchconfig/parent/parent_id', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        
        $logger->info('Retrieved Parent ID: ' . $parentId);
        
        if (!$storeCode || !$parentId) {
            $logger->err('Store code or Parent ID is null. Aborting execution.');
            return;
        }
        
        // Delete existing metadata
        $this->deleteExistingMetaData();
        
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        $categoryCollection->addAttributeToFilter('parent_id', $parentId);
        $categoryCollection->addAttributeToSort('position'); 
         //call getcategorytree function
        $categoryTree = $this->getCategoryTree($categoryCollection);

        $logger->info('Category Tree: ' . print_r($categoryTree, true));
         //call saveCategoryMetaDatafunction
        $this->saveCategoryMetaData($categoryTree);
        foreach ($categoryTree as $categoryData) {
            $categoryMetaData = $this->metadataFactory->create();
            $categoryMetaData->setData([
                'type' => 'category',
                'entity_id' => $categoryData['entity_id'],
                'meta_title' => $categoryData['meta_title'],
                'meta_description' => $categoryData['meta_description'],
                'url' => $categoryData['url'],
            ]);
        
            $categoryMetaData->save();
            $logger->info('Saved Category MetaData: ' . print_r($categoryMetaData->getData(), true));
        }
        ///fetch the cms page using searchCriteriaBuilder
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('store_id', $storeCode, 'eq')->create();
        $pageList = $this->pageRepository->getList($searchCriteria);
        foreach ($pageList->getItems() as $page) {
            $metaTitle = $page->getMetaTitle() ?: $page->getTitle();
            $metaDescription = $page->getMetaDescription();
            $url = $page->getIdentifier();
             
            $cmsMetaData = $this->metadataFactory->create();
            $cmsMetaData->setData([
                'type' => 'cms',
                'entity_id' => $page->getId(),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'url' => $url,
            ]);

            $cmsMetaData->save();
            $logger->info('Saved CMS MetaData: ' . print_r($cmsMetaData->getData(), true));
        }
    }
   //create savecategory data
    public function saveCategoryMetaData($categoryTree)
    {
        //get all categories for each loop 
        foreach ($categoryTree as $categoryData) {
            $categoryMetaData = $this->metadataFactory->create();
            $categoryMetaData->setData([
                'type' => 'category',
                'entity_id' => $categoryData['entity_id'],
                'meta_title' => $categoryData['meta_title'],
                'meta_description' => $categoryData['meta_description'],
                'url' => $categoryData['url'],
            ]);

            $categoryMetaData->save();
            $logger->info('Saved Category MetaData: ' . print_r($categoryMetaData->getData(), true));

            if (!empty($categoryData['children'])) {
                $this->saveCategoryMetaData($categoryData['children']);
            }
        }
    }
//get all $categoryCollectionfor each loop 
    public function getCategoryTree($categoryCollection)
    {
        $categoryTree = [];
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        
        foreach ($categoryCollection as $category) {
            $metaTitle = $category->getData('meta_title');
            $metaDescription = $category->getData('meta_description');
            $urlKey = $category->getData('url_path');
        
            $categoryTree[] = [
                'type' => 'category',
                'entity_id' => $category->getId(),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'url' => $urlKey,
                'children' => $this->getCategoryTree($this->getChildCategories($category->getId())),
            ];
        }

        return $categoryTree;
    }
//get all getChildCategories
    public function getChildCategories($parentId)
    {
        $childCollection = $this->categoryCollectionFactory->create();
        $childCollection->addAttributeToSelect('*');
        $childCollection->addAttributeToFilter('parent_id', $parentId);
        $childCollection->addAttributeToSort('position'); 

        return $childCollection;
    }

    public function deleteExistingMetaData()
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->metadataResource->getMainTable();
        $connection->truncateTable($tableName);
    }

}
