<?php
namespace Winkel\Catalog\Block\Product;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\Element;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Render;
use Magento\Framework\Url\Helper\Data;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\Registry;


/**
 * Product list
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class ListProduct extends \Magento\Catalog\Block\Product\AbstractProduct implements IdentityInterface
{
    /**
     * @param Context $context
     * @param PostHelper $postDataHelper
     * @param Resolver $layerResolver
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Data $urlHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        PostHelper $postDataHelper,
        Resolver $layerResolver,
        CategoryRepositoryInterface $categoryRepository,
        Data $urlHelper,
        ProductFactory $mProduct,
        StockStateInterface $stockState,
        Registry $registry,
        array $data = []
    ) {
        $this->mProduct = $mProduct;
        $this->_catalogLayer = $layerResolver->get();
        $this->_postDataHelper = $postDataHelper;
        $this->categoryRepository = $categoryRepository;
        $this->urlHelper = $urlHelper;
        $this->stockState = $stockState;
        $this->registry = $registry;
        parent::__construct(
            $context,
            $data
        );
    }

  function getCategory(){
      return $this->registry->registry('current_category');
  }
  function getPercentTag($productID){
      $product = $this->mProduct->create()->load($productID);

      $optionText = "";
      $attr = $product->getResource()->getAttribute('discount_name');
      if ($attr->usesSource()) {
         $optionText = $attr->getSource()->getOptionText($product->getDiscountName());
      }
      return $optionText;
  }

  /**
   * Default toolbar block name
   *
   * @var string
   */
  protected $_defaultToolbarBlock = Toolbar::class;

  /**
   * Product Collection
   *
   * @var AbstractCollection
   */
  protected $_productCollection;

  /**
   * Catalog layer
   *
   * @var Layer
   */
  protected $_catalogLayer;

  /**
   * @var PostHelper
   */
  protected $_postDataHelper;

  /**
   * @var Data
   */
  protected $urlHelper;

  /**
   * @var CategoryRepositoryInterface
   */
  protected $categoryRepository;



  /**
   * Retrieve loaded product collection
   *
   * The goal of this method is to choose whether the existing collection should be returned
   * or a new one should be initialized.
   *
   * It is not just a caching logic, but also is a real logical check
   * because there are two ways how collection may be stored inside the block:
   *   - Product collection may be passed externally by 'setCollection' method
   *   - Product collection may be requested internally from the current Catalog Layer.
   *
   * And this method will return collection anyway,
   * even when it did not pass externally and therefore isn't cached yet
   *
   * @return AbstractCollection
   */
  public function _getProductCollection()
  {
      if ($this->_productCollection === null) {
          $this->_productCollection = $this->initializeProductCollection();
      }

      return $this->_productCollection;
  }

  /**
   * Get catalog layer model
   *
   * @return Layer
   */
  public function getLayer()
  {
      return $this->_catalogLayer;
  }

  /**
   * Retrieve loaded category collection
   *
   * @return AbstractCollection
   */
  public function getLoadedProductCollection()
  {
      return $this->_getProductCollection();
  }

  /**
   * Retrieve current view mode
   *
   * @return string
   */
  public function getMode()
  {
      if ($this->getChildBlock('toolbar')) {
          return $this->getChildBlock('toolbar')->getCurrentMode();
      }

      return $this->getDefaultListingMode();
  }

  /**
   * Get listing mode for products if toolbar is removed from layout.
   * Use the general configuration for product list mode from config path catalog/frontend/list_mode as default value
   * or mode data from block declaration from layout.
   *
   * @return string
   */
  private function getDefaultListingMode()
  {
      // default Toolbar when the toolbar layout is not used
      $defaultToolbar = $this->getToolbarBlock();
      $availableModes = $defaultToolbar->getModes();

      // layout config mode
      $mode = $this->getData('mode');

      if (!$mode || !isset($availableModes[$mode])) {
          // default config mode
          $mode = $defaultToolbar->getCurrentMode();
      }

      return $mode;
  }

  /**
   * Need use as _prepareLayout - but problem in declaring collection from
   * another block (was problem with search result)
   * @return $this
   */
  protected function _beforeToHtml()
  {
      $collection = $this->_getProductCollection();

      $this->addToolbarBlock($collection);

      $collection->load();

      return parent::_beforeToHtml();
  }

  /**
   * Add toolbar block from product listing layout
   *
   * @param Collection $collection
   */
  private function addToolbarBlock(Collection $collection)
  {
      $toolbarLayout = $this->getToolbarFromLayout();

      if ($toolbarLayout) {
          $this->configureToolbar($toolbarLayout, $collection);
      }
  }

  /**
   * Retrieve Toolbar block from layout or a default Toolbar
   *
   * @return Toolbar
   */
  public function getToolbarBlock()
  {
      $block = $this->getToolbarFromLayout();

      if (!$block) {
          $block = $this->getLayout()->createBlock($this->_defaultToolbarBlock, uniqid(microtime()));
      }

      return $block;
  }

  /**
   * Get toolbar block from layout
   *
   * @return bool|Toolbar
   */
  private function getToolbarFromLayout()
  {

      $toolbarLayout = $this->getLayout()->getBlock('product_list_toolbar');

      return $toolbarLayout;
  }


  /**
   * Retrieve additional blocks html
   *
   * @return string
   */
  public function getAdditionalHtml()
  {
      return $this->getChildHtml('additional');
  }

  /**
   * Retrieve list toolbar HTML
   *
   * @return string
   */
  public function getToolbarHtml()
  {
      return $this->getChildHtml('toolbar');
  }

  /**
   * @param AbstractCollection $collection
   * @return $this
   */
  public function setCollection($collection)
  {
      $this->_productCollection = $collection;
      return $this;
  }

  /**
   * @param array|string|integer| Element $code
   * @return $this
   */
  public function addAttribute($code)
  {
      $this->_getProductCollection()->addAttributeToSelect($code);
      return $this;
  }

  /**
   * @return mixed
   */
  public function getPriceBlockTemplate()
  {
      return $this->_getData('price_block_template');
  }

  /**
   * Retrieve Catalog Config object
   *
   * @return Config
   */
  protected function _getConfig()
  {
      return $this->_catalogConfig;
  }

  /**
   * Prepare Sort By fields from Category Data
   *
   * @param Category $category
   * @return $this
   */
  public function prepareSortableFieldsByCategory($category)
  {
      if (!$this->getAvailableOrders()) {
          $this->setAvailableOrders($category->getAvailableSortByOptions());
      }
      $availableOrders = $this->getAvailableOrders();
      if (!$this->getSortBy()) {
          $categorySortBy = $this->getDefaultSortBy() ?: $category->getDefaultSortBy();
          if ($categorySortBy) {
              if (!$availableOrders) {
                  $availableOrders = $this->_getConfig()->getAttributeUsedForSortByArray();
              }
              if (isset($availableOrders[$categorySortBy])) {
                  $this->setSortBy($categorySortBy);
              }
          }
      }

      return $this;
  }

  /**
   * Return identifiers for produced content
   *
   * @return array
   */
  public function getIdentities()
  {
      $identities = [];

      $category = $this->getLayer()->getCurrentCategory();
      if ($category) {
          $identities[] = Product::CACHE_PRODUCT_CATEGORY_TAG . '_' . $category->getId();
      }

      //Check if category page shows only static block (No products)
      if ($category->getData('display_mode') == Category::DM_PAGE) {
          return $identities;
      }

      foreach ($this->_getProductCollection() as $item) {
          $identities = array_merge($identities, $item->getIdentities());
      }

      return $identities;
  }

  /**
   * Get post parameters
   *
   * @param Product $product
   * @return array
   */
  public function getAddToCartPostParams(Product $product)
  {
      $url = $this->getAddToCartUrl($product);
      return [
          'action' => $url,
          'data' => [
              'product' => $product->getEntityId(),
              ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
          ]
      ];
  }

  /**
   * @param Product $product
   * @return string
   */
  public function getProductPrice(Product $product)
  {
      $priceRender = $this->getPriceRender();

      $price = '';
      if ($priceRender) {
          $price = $priceRender->render(
              FinalPrice::PRICE_CODE,
              $product,
              [
                  'include_container' => true,
                  'display_minimal_price' => true,
                  'zone' => Render::ZONE_ITEM_LIST,
                  'list_category_page' => true
              ]
          );
      }

      return $price;
  }

  /**
   * Specifies that price rendering should be done for the list of products
   * i.e. rendering happens in the scope of product list, but not single product
   *
   * @return Render
   */
  protected function getPriceRender()
  {
      return $this->getLayout()->getBlock('product.price.render.default')
          ->setData('is_product_list', true);
  }

  /**
   * Configures product collection from a layer and returns its instance.
   *
   * Also in the scope of a product collection configuration, this method initiates configuration of Toolbar.
   * The reason to do this is because we have a bunch of legacy code
   * where Toolbar configures several options of a collection and therefore this block depends on the Toolbar.
   *
   * This dependency leads to a situation where Toolbar sometimes called to configure a product collection,
   * and sometimes not.
   *
   * To unify this behavior and prevent potential bugs this dependency is explicitly called
   * when product collection initialized.
   *
   * @return Collection
   */
  private function initializeProductCollection()
  {
      $layer = $this->getLayer();
      /* @var $layer Layer */
      if ($this->getShowRootCategory()) {
          $this->setCategoryId($this->_storeManager->getStore()->getRootCategoryId());
      }

      // if this is a product view page
      if ($this->_coreRegistry->registry('product')) {
          // get collection of categories this product is associated with
          $categories = $this->_coreRegistry->registry('product')
              ->getCategoryCollection()->setPage(1, 1)
              ->load();
          // if the product is associated with any category
          if ($categories->count()) {
              // show products from this category
              $this->setCategoryId(current($categories->getIterator())->getId());
          }
      }

      $origCategory = null;
      if ($this->getCategoryId()) {
          try {
              $category = $this->categoryRepository->get($this->getCategoryId());
          } catch (NoSuchEntityException $e) {
              $category = null;
          }

          if ($category) {
              $origCategory = $layer->getCurrentCategory();
              $layer->setCurrentCategory($category);
          }
      }
      $collection = $layer->getProductCollection();

      $this->prepareSortableFieldsByCategory($layer->getCurrentCategory());

      if ($origCategory) {
          $layer->setCurrentCategory($origCategory);
      }

      $this->addToolbarBlock($collection);

      $this->_eventManager->dispatch(
          'catalog_block_product_list_collection',
          ['collection' => $collection]
      );

      return $collection;
  }

  /**
   * Configures the Toolbar block with options from this block and configured product collection.
   *
   * The purpose of this method is the one-way sharing of different sorting related data
   * between this block, which is responsible for product list rendering,
   * and the Toolbar block, whose responsibility is a rendering of these options.
   *
   * @param ProductList\Toolbar $toolbar
   * @param Collection $collection
   * @return void
   */
  private function configureToolbar(Toolbar $toolbar, Collection $collection)
  {
      // use sortable parameters
      $orders = $this->getAvailableOrders();
      if ($orders) {
          $toolbar->setAvailableOrders($orders);
      }
      $sort = $this->getSortBy();
      if ($sort) {
          $toolbar->setDefaultOrder($sort);
      }
      $dir = $this->getDefaultDirection();
      if ($dir) {
          $toolbar->setDefaultDirection($dir);
      }
      $modes = $this->getModes();
      if ($modes) {
          $toolbar->setModes($modes);
      }
      // set collection to toolbar and apply sort
      $toolbar->setCollection($collection);
      $this->setChild('toolbar', $toolbar);
  }
  function detect_featured($productID){
      $product = $this->mProduct->create()->load($productID);
      $isFeatured = $product->getAttributeText('featured');

      if (is_array($isFeatured)){
          foreach ($isFeatured as $value) {
              $isFeatured = $value;
          }
      }

      return $isFeatured;
  }

  function getAttValue($productID, $attCode){
      $product = $this->mProduct->create()->load($productID);

      $productType = $product->getTypeId();

      $stockstat = 0;
      $stockstatName = "Allow Extra Time";
      if($productType == 'configurable'){
          $_children = $product->getTypeInstance()->getUsedProducts($product);

          if(trim(strtolower($attCode)) == "stock_status"){
              foreach ($_children as $child){
                  $productID = $child->getID();
                  $productFinal = $this->mProduct->create()->load($productID);
                  $val = $productFinal->getAttributeText($attCode);
                  if(trim(strtolower($val)) == "in stock"){
                    $stockstat = 1;
                    $stockstatName = "In Stock";
                  }
              }
          }
          else{
              foreach ($_children as $child){
                  $productID = $child->getID();
                  break;
              }
          }
      }

      $productFinal = $this->mProduct->create()->load($productID);
      $val = $productFinal->getAttributeText($attCode);

      if($stockstat == 1){
          $val = $stockstatName;
      }

      return $val;
  }

  function getDispatch($productID){
      $product = $this->mProduct->create()->load($productID);
      $StockState = $this->stockState;

      $productType = $product->getTypeId();
      $productFinal = false;
      if($productType == 'configurable'){
          $_children = $product->getTypeInstance()->getUsedProducts($product);

          foreach ($_children as $child){
              $productID = $child->getID();
              $productFinal = $child;
              $qty = $StockState->getStockQty($productID, $product->getStore()->getWebsiteId());
              if($qty > 0){
                  break;
              }
          }
      }
      $val = "";
      if($productFinal){
        $val = $productFinal->getAttributeText('dispatch');
      }

      if($val != ""){
          $splitVal = explode('-', $val);
          $minWeek = $splitVal[0];
          $maxWeek = $splitVal[1];

          $startDate = date("M d", strtotime("+". $minWeek ." week"));
          $endDate = date("M d", strtotime("+". $maxWeek ." week"));
          $datePrint = $startDate . ' - ' . $endDate;
      }
      else{
          $datePrint = "";
      }


      return $datePrint;
  }

  function getVariant($productID){
      $product = $this->mProduct->create()->load($productID);
      $productType = $product->getTypeId();

      $val = "";
      $item = 0;
      $islength = 0;

      $arrAttCodeColors = array('teak', 'fabric', 'leather', 'sling', 'aluminium', 'wicker', 'stainless_steel', 'rope', 'ceramic', 'marble');
      $arrAttCodeSizes = array('size','length');
      $arrAttFrameSizes = array('frame');
      $arrTempColors = array();
      $arrTempSizes = array();
      $arrFrameOptions = array();
      $listAttCode = array();
      if($productType == 'configurable'){

          $productAttributeOptions = $product->getTypeInstance()->getConfigurableAttributes($product);

          foreach ($productAttributeOptions as $valAtt) {
              array_push($listAttCode, $valAtt["attribute_code"]);
          }

          $_children = $product->getTypeInstance()->getUsedProducts($product);
          foreach ($_children as $child){
              $item++;
              foreach ($arrAttCodeColors as $valColors) {
                  if (in_array($valColors, $listAttCode)){
                      $detectVal = $child->getAttributeText($valColors);
                      if($detectVal != "" ){
                          array_push($arrTempColors, $detectVal);
                      }
                  }
              }
              foreach ($arrAttCodeSizes as $valSizes) {
                  if (in_array($valSizes, $listAttCode)){
                      $detectValSizes = $child->getAttributeText($valSizes);
                      if($detectValSizes != "" ){
                          array_push($arrTempSizes, $detectValSizes);
                      }
                  }
              }
              foreach ($arrAttFrameSizes as $valFrame) {
                  if (in_array($valFrame, $listAttCode)){
                      $detectValFrame = $child->getAttributeText($valFrame);
                      if($detectValFrame != "" ){
                          array_push($arrFrameOptions, $detectValFrame);
                      }
                  }
              }

          }

          $arrTempColors = array_unique($arrTempColors);
          $arrTempSizes = array_unique($arrTempSizes);
          $arrFrameOptions = array_unique($arrFrameOptions);

          if(!empty($arrTempColors)){
              $val .= '<p class="my-0">' . __("%1 Colors Available", count($arrTempColors)) . '</p>';
          }
          if(!empty($arrTempSizes)){
              $val .= '<p class="my-0">' . __("%1 Sizes Available", count($arrTempSizes)) . '</p>';
          }
           if(!empty($arrFrameOptions)){
              $val .= '<p class="my-0">' . __("%1 Frame Options Available", count($arrFrameOptions)) . '</p>';
          }
      }
      return $val;
  }


  function getProductType($productID){

      $product = $this->mProduct->create()->load($productID);
      $productType = $product->getTypeId();

      return $productType;
  }



  function getAmbience($id, $block){
      $ambienceImage = false;

      $productImage = $this->mProduct->create()->load($id);
      $ambience = $productImage->getResource()->getAttribute('ambience')->getFrontend()->getValue($productImage);
      if($ambience){
          $finalAmbience = str_replace('//','/',$ambience);
          $ambienceImage = $block->getUrl("pub/media/catalog").'product'.$finalAmbience;
      }
      return $ambienceImage;
  }

  function getUntilSale($productID){

      $product = $this->mProduct->create()->load($productID);
      $StockState = $this->stockState;
      $productType = $product->getTypeId();
      $datePrint = "";
      if($productType == 'configurable'){
          $_children = $product->getTypeInstance()->getUsedProducts($product);

          foreach ($_children as $child){
              if($child->getSpecialPrice()){
                  $endDateSpecialPrice = $child->getSpecialToDate();
                  $formatEndDateSpecialPrice = __("Until "). date("M d", strtotime($endDateSpecialPrice));
                  $datePrint = $formatEndDateSpecialPrice;
              }
          }
      }
      else{
          if($product->getSpecialPrice()){
              $endDateSpecialPrice = $product->getSpecialToDate();
              $formatEndDateSpecialPrice = __("Until "). date("M d", strtotime($endDateSpecialPrice));
              $datePrint = $formatEndDateSpecialPrice;
          }
      }

      return $datePrint;
  }
}
