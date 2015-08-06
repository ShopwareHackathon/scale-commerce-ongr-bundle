<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\ONGRIndexingBundle\Product;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\ESIndexingBundle\Console\ProgressHelperInterface;
use Shopware\Bundle\ESIndexingBundle\Product\ProductProviderInterface;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Bundle\StoreFrontBundle\Struct\ListProduct;
use Shopware\Bundle\StoreFrontBundle\Struct\Shop;
use Shopware\Components\HttpClient\GuzzleFactory;
use Shopware\Components\HttpClient\GuzzleHttpClient;

class OngrIndexer
{
    /**
     * @var ProductProviderInterface
     */
    private $provider;

    /**
     * @var ProductQueryFactory
     */
    private $queryFactory;

    /**
     * @param ProductProviderInterface $provider
     * @param ProductQueryFactory $queryFactory
     */
    public function __construct(
        ProductProviderInterface $provider,
        ProductQueryFactory $queryFactory
    ) {
        $this->provider = $provider;
        $this->queryFactory = $queryFactory;
    }

    /**
     * @param Shop $shop
     * @param ProgressHelperInterface $progress
     */
    public function populate(Shop $shop, ProgressHelperInterface $progress)
    {
        $categoryId = $shop->getCategory()->getId();
        $idQuery = $this->queryFactory->createCategoryQuery($categoryId, 100);
        $progress->start($idQuery->fetchCount(), 'Indexing products');

        while ($productIds = $idQuery->fetch()) {
            $query = $this->queryFactory->createProductIdQuery($productIds);
            $this->indexProducts($shop, $query->fetch());
            $progress->advance(count(array_unique($productIds)));
        }

        $progress->finish();
    }

    /**
     * @param Shop $shop
     * @param string[] $numbers
     */
    private function indexProducts(Shop $shop, $numbers)
    {
        if (empty($numbers)) {
            return;
        }

        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');

        $context = $contextService->createProductContext(
            $shop->getId(),
            null,
            ContextService::FALLBACK_CUSTOMER_GROUP
        );

        $ps = Shopware()->Container()->get('shopware_storefront.product_service');
        $products = $ps->getList($numbers, $context);
        $categories = $this->getCategories($products);

        $baseURL = 'http://' . $shop->getHost() . $shop->getPath() . '/';

        $data = [];
        foreach ($products as $product) {
            $category = [
                '_id' => "1",
                'title' => "Shoes",
            ];

            $category = [
                '_id' => 2,
                '_parent' => 1,
                'title' => "Women Shoes",
                'hiden' => true,
            ];

            $category = [
                '_id' => "3",
                'title' => "Accesories",
            ];

            $categoryIds = (isset($categories[$product->getId()])) ? $categories[$product->getId()] : array();

            $productData = [
                '_id'   => $product->getVariantId(),
                'parent' => ($product->isMainVariant()) ? null : $product->getMainVariantId(),
                'title'  => $product->getName(),
                'sku' => $product->getNumber(),
                'description' => $product->getShortDescription(), // $product->getLongDescription()
                'price' => $product->getCheapestPrice()->getCalculatedPrice(),
                'category' => [1], //$categoryIds,
                'image' => $baseURL . $product->getCover()->getFile(),
                //'thumb' => 'my thumb', //$product->getCover()->getThumbnails()[0]->get,
                'urls' => [
                    [
                        'url' => 'products/' . $product->getVariantId(),
                        'key' => '',
                    ]
                ]
            ];

            if ($product->getPropertySet()) {
                foreach ($product->getPropertySet()->getGroups() as $group) {
                    if ($group->getName() === 'color') {
                        foreach ($group->getOptions() as $option) {
                            $productData['color'] = $option->getName();
                        }
                    }
                    if ($group->getName() === 'size') {
                        foreach ($group->getOptions() as $option) {
                            $productData['size'] = $option->getName();
                        }
                    }
                }
            }

            $data[] = [
                'type' => 'product',
                'index' => $productData
            ];
        }

        $data = json_encode($data, JSON_PRETTY_PRINT);
        $client = new GuzzleHttpClient(new GuzzleFactory());

        $result = $client->post(
            "http://10.70.31.110:8000/app.php/api/v10",
            [],
            $data
        );


        var_dump($result->getStatusCode());
        return;

        $remove   = array_diff($numbers, array_keys($products));

        $documents = [];
        foreach ($products as $product) {
            $documents[] = ['index' => ['_id' => $product->getNumber()]];
            $documents[] = $product;
        }

        foreach ($remove as $number) {
            $documents[] = ['delete' => ['_id' => $number]];
        }
    }

    /**
     * @param ListProduct[] $products
     * @return array[]
     */
    private function getCategories($products)
    {
        $ids = array_map(function (BaseProduct $product) {
            return (int) $product->getId();
        }, $products);


        $connection = Shopware()->Container()->get('dbal_connection');
        $query = $connection->createQueryBuilder();
        $query->select(['mapping.articleID', 'categories.id', 'categories.path'])
            ->from('s_articles_categories', 'mapping')
            ->innerJoin('mapping', 's_categories', 'categories', 'categories.id = mapping.categoryID')
            ->where('mapping.articleID IN (:ids)')
            ->setParameter(':ids', $ids, Connection::PARAM_INT_ARRAY)
        ;

        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            $articleId = (int) $row['articleID'];
            if (isset($result[$articleId])) {
                $categories = $result[$articleId];
            } else {
                $categories = [];
            }
            $temp = explode('|', $row['path']);
            $temp[] = $row['id'];

            $result[$articleId] = array_merge($categories, $temp);
        }

        return array_map(function ($row) {
            return array_values(array_unique(array_filter($row)));
        }, $result);
    }
}
