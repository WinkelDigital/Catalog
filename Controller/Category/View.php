<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Winkel\Catalog\Controller\Category;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Product\ProductList\ToolbarMemorizer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\View\LayoutFactory;

/**
 * View a category on storefront. Needs to be accessible by POST because of the store switching.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class View extends \Magento\Catalog\Controller\Category\View implements HttpGetActionInterface, HttpPostActionInterface
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\Design $catalogDesign,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator $categoryUrlPathGenerator,
        PageFactory $resultPageFactory,
        LayoutFactory $layoutFactory,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory,
        Resolver $layerResolver,
        CategoryRepositoryInterface $categoryRepository,
        ToolbarMemorizer $toolbarMemorizer = null,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context,
        $catalogDesign,
        $catalogSession,
        $coreRegistry,
        $storeManager,
        $categoryUrlPathGenerator,
        $resultPageFactory,
        $resultForwardFactory,
        $layerResolver,
        $categoryRepository,
        $toolbarMemorizer);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->layoutFactory = $layoutFactory;
    }
    /**
     * Category view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
      if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
        $category = $this->_initCategory();
        $result = $this->resultJsonFactory->create();
        return $result->setData($this->getProdsOnly());
      }else{
        return parent::execute();
      }
    }

    public function getProdsOnly(){
      $block = $this->layoutFactory->create();
      $listBlock = $block->addBlock('Magento\Catalog\Block\Product\ListProduct','tz_custom_list');
      $pricing = $block->createBlock('Magento\Framework\Pricing\Render','product.price.render.default',['data' =>
      ['price_render_handle'=>'catalog_product_prices',
        'use_link_for_as_low_as'=>true]
      ]);
      $listBlock->setTemplate('Winkel_Catalog::html/product/product_only.phtml');
      $toolbar = $block->addBlock('Magento\Catalog\Block\Product\ProductList\Toolbar','product_list_toolbar','tz_custom_list');
      $pager = $block->addBlock('Magento\Theme\Block\Html\Pager','tz_custom_pager','product_list_toolbar');
      $layer = $block->addBlock('Magento\LayeredNavigation\Block\Navigation\Category','tz_custom_layer','tz_custom_list');
      $layer->setTemplate('Magento_LayeredNavigation::layer/view.phtml');
      $state = $block->addBlock('Magento\LayeredNavigation\Block\Navigation\State','state','tz_custom_layer');


      return [
        'page'=>$pager->getCurrentPage(),
        'total'=>$listBlock->_getProductCollection()->getSize(),
        'state'=> $state->toHtml(),
        'limit'=>$toolbar->getLimit() * 1,
        'html'=>$listBlock->toHtml()];
    }

}
