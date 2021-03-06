<?php
namespace Shopware\Components\Routing\Generators;

use Shopware\Components\QueryAliasMapper;
use Shopware\Components\Routing\GeneratorListInterface;
use Shopware\Components\Routing\Context;
use Doctrine\DBAL\Connection;

/**
 * @category  Shopware
 * @package   Shopware\Components\Routing
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class RewriteGenerator implements GeneratorListInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var QueryAliasMapper
     */
    private $queryAliasMapper;

    /**
     * @param Connection $connection
     * @param QueryAliasMapper $queryAliasMapper
     */
    public function __construct(Connection $connection, QueryAliasMapper $queryAliasMapper)
    {
        $this->connection = $connection;
        $this->queryAliasMapper = $queryAliasMapper;
    }

    /**
     * @return string
     */
    protected function getAssembleQuery()
    {
        $sql = 'SELECT org_path, path FROM s_core_rewrite_urls WHERE subshopID=:shopId AND org_path IN (:orgPath) AND main=1 ORDER BY id DESC';

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $params, Context $context)
    {
        $orgQuery = $this->preAssemble($params, $context);
        if (!is_array($orgQuery)) {
            return false;
        }

        $orgPath = http_build_query($orgQuery, '', '&');

        list($url) = $this->rewriteList([$orgPath], $context);

        if ($url === false) {
            return false;
        }

        if ($context->isUrlToLower()) {
            $url = strtolower($url);
        }
        $query = array_diff_key($params, $orgQuery);
        // Remove globals
        unset($query['module'], $query['controller']);
        // Remove action, if action is a part of the seo url
        if (isset($orgQuery['sAction']) || isset($query['action']) && $query['action'] == 'index') {
            unset($query['action']);
        }
        if (!empty($query)) {
            $url .= '?' . $this->rewriteQuery($query);
        }

        return $url;
    }


    /**
     * @param array $list
     * @param Context $context
     * @return array
     */
    public function generateList(array $list, Context $context)
    {
        $orgQueryList = array_map(function ($params) use ($context) {
            return $this->preAssemble($params, $context);
        }, $list);

        if (max($orgQueryList) === false) {
            return $list;
        }

        $orgPathList = array_map(function ($orgQuery) {
            return http_build_query($orgQuery, '', '&');
        }, $orgQueryList);

        $urls = $this->rewriteList($orgPathList, $context);
        if (max($urls) === false) {
            return $list;
        }

        //Add query / strtolower
        array_walk($urls, function (&$url, $key) use ($context, $list, $orgQueryList) {
            if ($url !== false) {
                if ($context->isUrlToLower()) {
                    $url = strtolower($url);
                }
                $query = array_diff_key($list[$key], $orgQueryList[$key]);
                unset($query['module'], $query['controller']);
                if (isset($orgQueryList[$key]['sAction']) || isset($query['action']) && $query['action'] == 'index') {
                    unset($query['action']);
                }
                if (!empty($query)) {
                    $url .= '?' . $this->rewriteQuery($query);
                }
            }
        });

        return $urls;
    }

    /**
     * @param array $params
     * @param Context $context
     * @return array|bool
     */
    private function preAssemble($params, Context $context)
    {
        if (isset($params['module']) && $params['module'] != 'frontend') {
            return false;
        }

        if ($context->getShopId() === null) {
            return false;
        }

        if (!isset($params['controller'])) {
            return false;
        }

        return $this->getOrgQueryArray($params);
    }

    /**
     * @param array $list
     * @param Context $context
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function rewriteList(array $list, Context $context)
    {
        $query = $this->getAssembleQuery();
        $statement = $this->connection->executeQuery(
            $query,
            [
                ':shopId' => $context->getShopId(),
                ':orgPath' => $list
            ],
            [
                ':shopId' => \PDO::PARAM_INT,
                ':orgPath' => Connection::PARAM_STR_ARRAY
            ]
        );

        $rows = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($list as $key => $orgPath) {
            if (isset($rows[$orgPath])) {
                $list[$key] = $rows[$orgPath];
            } else {
                $list[$key] = false;
            }
        }

        return $list;
    }


    /**
     * @param $query
     * @return array
     */
    protected function getOrgQueryArray($query)
    {
        $orgQuery = ['sViewport' => $query['controller']];
        switch ($query['controller']) {
            case 'detail':
                $orgQuery['sArticle'] = $query['sArticle'];
                break;
            case 'blog':
                if (isset($query['action']) && $query['action'] != 'index') {
                    $orgQuery['sAction'] = $query['action'];
                    $orgQuery['sCategory'] = $query['sCategory'];
                    $orgQuery['blogArticle'] = $query['blogArticle'];
                } else {
                    $orgQuery['sCategory'] = $query['sCategory'];
                }
                break;
            case 'cat':
                $orgQuery ['sCategory'] = $query['sCategory'];
                break;
            case 'supplier':
                $orgQuery ['sSupplier'] = $query['sSupplier'];
                break;
            case 'campaign':
                if (isset($query['sCategory'])) {
                    $orgQuery ['sCategory'] = $query['sCategory'];
                }
                $orgQuery['emotionId'] = $query['emotionId'];
                break;
            case 'support':
            case 'ticket':
                $orgQuery['sViewport'] = 'ticket';
                if (isset($query['sFid'])) {
                    $orgQuery['sFid'] = $query['sFid'];
                }
                break;
            case 'custom':
                if (isset($query['sCustom'])) {
                    $orgQuery['sCustom'] = $query['sCustom'];
                }
                break;
            case 'content':
                if (isset($query['sContent'])) {
                    $orgQuery['sContent'] = $query['sContent'];
                }
                break;
            case 'listing':
                if (isset($query['action']) && $query['action'] == 'manufacturer') {
                    $orgQuery['sAction'] = $query['action'];
                    $orgQuery['sSupplier'] = $query['sSupplier'];
                }
                break;
            default:
                if (isset($query['action'])) {
                    $orgQuery['sAction'] = $query['action'];
                }
                break;
        }
        return $orgQuery;
    }

    /**
     * @param array $query
     * @return string
     */
    private function rewriteQuery($query)
    {
        $tmp = $this->queryAliasMapper->replaceLongParams($query);

        return http_build_query($tmp, '', '&');
    }
}
