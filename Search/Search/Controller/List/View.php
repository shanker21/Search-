<?php
namespace Apetito\Search\Controller\List;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Apetito\Search\Model\ResourceModel\MetaData\CollectionFactory as MetaDataCollectionFactory;

class View extends Action
{
    protected $resultPageFactory;
    protected $metaDataCollectionFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        MetaDataCollectionFactory $metaDataCollectionFactory,
        \Magento\Framework\Escaper $escaper
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->metaDataCollectionFactory = $metaDataCollectionFactory;
        $this->_escaper = $escaper;
        parent::__construct($context);
    }

    public function execute()
    {
        $query = $this->_escaper->escapeHtml($this->getRequest()->getParam('search'));
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Search Results for "%1"', $query));

        $block = $resultPage->getLayout()->getBlock('search.index.index');
        if ($block) {
            $block->setData('query', $query);
        }

        return $resultPage;
    }
}
