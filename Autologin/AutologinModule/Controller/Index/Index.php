<?php

namespace Autologin\AutologinModule\Controller\Index;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Checkout\Model\SessionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;


    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $stockState;


    /** @var SessionFactory */
    protected $checkoutSession;

    /** @var CartRepositoryInterface */
    protected $cartRepository;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var Json */
    protected $json;

    /** @var Configurable */
    protected $configurableType;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;
    /**
     * @var Product
     */
    protected $_product;


    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactory $productFactory
     * @param Session $session
     * @param Product $product
     * @param StockStateInterface $stockState
     * @param Json $json
     * @param SessionFactory $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Configurable $configurableType
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        Context                    $context,
        PageFactory                $resultPageFactory,
        ProductRepositoryInterface $productRepository,
        ProductFactory             $productFactory,
        Session                    $session,
        Product                    $product,
        StockStateInterface        $stockState,
        Json                       $json,
        SessionFactory             $checkoutSession,
        CartRepositoryInterface    $cartRepository,
        Configurable               $configurableType,
        CustomerFactory            $customerFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->_customerSession = $session;
        $this->_product = $product;
        $this->stockState = $stockState;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->json = $json;
        $this->configurableType = $configurableType;
        $this->customerFactory = $customerFactory;
        parent::__construct($context);
    }


    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $k = $this->_request->getParam('k');

        $products = $this->_request->getParam('products');

        $customerIdByHash = $this->getCustomerIdByHash($k);

        $this->_customerSession->loginById($customerIdByHash);

        $firstExplode = explode('|', $products);

        foreach ($firstExplode as $item) {
            $secondExplode = explode('_', $item);
            $productSku = $secondExplode[0];
            $productQuantity = $secondExplode[1];
        }

        if ($this->_customerSession->isLoggedIn()) {

            $secret_hash = $this->_customerSession->getCustomer()->getData('secret_hash');

            $existProduct = $this->_product->getIdBySku($productSku);

            $existQty = $this->getStockQty($this->_product->loadByAttribute('sku', $productSku)->getId());

            try {
                if ($k == $secret_hash && $existProduct && $productQuantity <= $existQty) {

                    $product = $this->productRepository->getById($existProduct);
                    $session = $this->checkoutSession->create();
                    $quote = $session->getQuote();
                    $quote->addProduct($product, $productQuantity);

                    $this->cartRepository->save($quote);
                    $session->replaceQuote($quote)->unsLastRealOrderId();

                    return $this->redirect('checkout/cart');

                } else {
                    return $this->redirect('/');
                }
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

        } else {
            return $this->redirect('/');
        }
    }

    /**
     * @param $path
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function redirect($path)
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path);
        return $resultRedirect;
    }

    /**
     * @param $productId
     * @return float
     */
    public function getStockQty($productId)
    {
        return $this->stockState->getStockQty($productId);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getCustomerIdByHash($code)
    {
        $collection = $this->customerFactory->create()->getCollection()
            ->addAttributeToSelect("*")
            ->addAttributeToFilter("secret_hash", $code)
            ->load();

        $c_data = $collection->getData();
        return $c_data[0]['entity_id'];

    }
}