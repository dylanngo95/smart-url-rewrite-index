<?php

declare(strict_types=1);

namespace Smart\UrlRewriteIndex\Plugin\UrlRewrite\Controller;


use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Smart\UrlRewriteIndex\Model\ResourceModel\UrlRewrite\Collection as UrlRewriteCollection;
use Smart\UrlRewriteIndex\Model\ResourceModel\UrlRewrite\CollectionFactory;


/**
 * Class Router
 * @package Smart\UrlRewriteIndex\Plugin\UrlRewrite\Controller
 */
class Router extends \Magento\UrlRewrite\Controller\Router
{

    /**
     * @var CollectionFactory
     */
    private $rewriteCollection;

    /**
     * @param ActionFactory $actionFactory
     * @param UrlInterface $url
     * @param StoreManagerInterface $storeManager
     * @param ResponseInterface $response
     * @param UrlFinderInterface $urlFinder
     * @param CollectionFactory $rewriteCollection
     */
    public function __construct(
        ActionFactory $actionFactory,
        UrlInterface $url,
        StoreManagerInterface $storeManager,
        ResponseInterface $response,
        UrlFinderInterface $urlFinder,
        CollectionFactory $rewriteCollection
    )
    {
        parent::__construct($actionFactory, $url, $storeManager, $response, $urlFinder);
        $this->rewriteCollection = $rewriteCollection;
    }

    /**
     * @param \Magento\UrlRewrite\Controller\Router $subject
     * @param callable $proceed
     * @param RequestInterface|Http $request
     * @return ActionInterface|null
     * @throws NoSuchEntityException
     */
    public function aroundMatch(\Magento\UrlRewrite\Controller\Router $subject, callable $proceed, RequestInterface $request)
    {
//        return $proceed($request);

        $rewrite = $this->getRewrite(
            $request->getPathInfo(),
            $this->storeManager->getStore()->getId()
        );

        if ($rewrite->getEntityId() === null) {
            //No rewrite rule matching current URl found, continuing with
            //processing of this URL.
            return null;
        }
        if ($rewrite->getRedirectType()) {
            //Rule requires the request to be redirected to another URL
            //and cannot be processed further.
            return $this->processRedirect($request, $rewrite);
        }
        //Rule provides actual URL that can be processed by a controller.
        $request->setAlias(
            UrlInterface::REWRITE_REQUEST_PATH_ALIAS,
            $rewrite->getRequestPath()
        );
        $request->setPathInfo('/' . $rewrite->getTargetPath());

        return $this->actionFactory->create(
            \Magento\Framework\App\Action\Forward::class
        );
    }

    /**
     * Find rewrite based on request data
     * @param string $requestPath
     * @param int $storeId
     * @return DataObject
     */
    public function getRewrite($requestPath, $storeId)
    {
        /** @var UrlRewriteCollection $collection */
        $collection = $this->rewriteCollection->create();
        $result = $collection
            ->addFieldToFilter('request_path', ['like' => substr($requestPath, 1)])
            ->addFieldToFilter('store_id', $storeId)
            ->getFirstItem();
        return $result;
    }

}
