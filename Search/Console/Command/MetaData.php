<?php

namespace Apetito\Search\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Apetito\Search\Model\MetaDataFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Apetito\Search\Model\ResourceModel\MetaData as MetaDataResource;

class MetaData extends Command
{
    protected $categoryCollectionFactory;
    protected $pageRepository;
    protected $state;
    protected $metadataFactory;
    protected $resourceConnection;
    protected $searchCriteriaBuilder;
    protected $scopeConfig;
    protected $storeManager;
    protected $metadataResource;

    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        PageRepositoryInterface $pageRepository,
        State $state,
        MetaDataFactory $metadataFactory,
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        MetaDataResource $metadataResource
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->pageRepository = $pageRepository;
        $this->state = $state;
        $this->metadataFactory = $metadataFactory;
        $this->resourceConnection = $resourceConnection;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->metadataResource = $metadataResource;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('apetito:search:metadata')
             ->setDescription('Update meta data for categories and CMS pages.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $e) {
            // Area code already set
        }

        $connection = $this->resourceConnection->getConnection();
        $storeCode = $this->scopeConfig->getValue('Apetito_searchconfig/search_store/store_view', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $parentId = $this->scopeConfig->getValue('Apetito_searchconfig/parent/parent_id', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        // Delete existing metadata
        $this->deleteExistingMetaData();

        // Process categories
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        $categoryCollection->addAttributeToFilter('parent_id', $parentId);
        $categoryCollection->addAttributeToFilter('is_active', 1);
        $categoryCollection->addAttributeToSort('position');

        $categoryTree = $this->getCategoryTree($categoryCollection);

        $this->saveCategoryMetaData($categoryTree);
        // Process CMS pages
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
        }

        $output->writeln('<info>Meta data updated successfully.</info>');

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }

    protected function saveCategoryMetaData($categoryTree)
    {
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
            
            if (!empty($categoryData['children'])) {
                $this->saveCategoryMetaData($categoryData['children']);
            }
        }
    }

    protected function getCategoryTree($categoryCollection)
    {
        $categoryTree = [];

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

    protected function getChildCategories($parentId)
    {
        $childCollection = $this->categoryCollectionFactory->create();
        $childCollection->addAttributeToSelect('*');
        $childCollection->addAttributeToFilter('parent_id', $parentId);
        $childCollection->addAttributeToSort('position');

        return $childCollection;
    }

    protected function deleteExistingMetaData()
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->metadataResource->getMainTable();
        $connection->truncateTable($tableName);
    }
}
