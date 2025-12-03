<?php
namespace Zabba\Module\ZSearchSphinx\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseFactory;

class ZSearchSphinxHelper
{
    public function getSearch($params): array
    {
	$input = Factory::getApplication()->getInput();

	if (!$input->exists('search_box')) {
	    return [];
	}
        $obchodParam = $params->get('obchod', '2', 'STRING');
	$query       = $input->get('search_box', '', 'STRING');
	$znacky      = $input->get('znacky_search', '', 'STRING');
	$sphinxTable = $params->get('sphinx_table', '', 'STRING');

	[$start, $offset, $current] = $this->getPagination($input);
	$fullQuery = $this->prepareQuery($query, $znacky);

	$dbSphinx = $this->getSphinxConnection();
	if (!$dbSphinx) {
	    return [];
	}

	[$ids, $meta] = $this->searchSphinx($dbSphinx, $fullQuery, $start, $offset, $sphinxTable);

	$totalInfo = $this->formatTotalInfo($meta, $start, $offset, $current, $fullQuery);

	if (empty($ids)) {
	    return [$totalInfo];
	}

	$dbJoomla = $this->getJoomlaConnection();
	if (!$dbJoomla) {
	    return [$totalInfo];
	}

	$user     = Factory::getApplication()->getIdentity();
	$userId   = isset($user->id) ? (int)$user->id : 0;
	$shopperGroupId  = $this->getUserGroup($dbJoomla, $userId, $obchodParam);
	$products = $this->fetchProducts($dbJoomla, $ids, $shopperGroupId);

	$docs = [];

	foreach ($ids as $id) {
	    $docs[] = $products[$id] ?? null;
	}

	$docs[] = $totalInfo;

	return $docs;
    }

    private function prepareQuery(string $query, string $znacky): string
    {
	if (mb_strlen($query) < 4) {
	    $query = 'musiszadattriznaky';
	}

	$full = trim($query . ' ' . $znacky);
	return self::sanitizeSphinxQuery($full);
    }
    
    private function getPagination($input): array
    {
	$offset = 24;
	$start  = (int)$input->get('start', 0, 'INT');
	$current = (int)($start / $offset + 1);

	return [$start, $offset, $current];
    }
    
    private function getSphinxConnection()
    {
	try {
	    return (new DatabaseFactory())->getDriver('mysql', self::pripojDatabazi('sphinx'));
	} catch (\Throwable $e) {
	    Factory::getApplication()->enqueueMessage(
		Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
		'warning'
        )   ;
	    return null;
	}
    }

    private function searchSphinx($db, string $query, int $start, int $offset, string $sphinxTable): array
    {
	$table = '#__'.$sphinxTable;
	$stmt = $db->getQuery(true)
	    ->select('id')
	    ->from($table)
	    ->where("
		MATCH('{$query}')
		ORDER BY product_in_stock DESC
		LIMIT {$start}, {$offset}
		OPTION ranker=proximity_bm25
	    ");
	$db->setQuery($stmt);
	$rows = $db->loadAssocList();

	// Extract IDs
	$ids = array_map(
	    fn($r) => (int)$r['id'],
	    $rows ?? []
	);

	// META
	$db->setQuery('SHOW META');
	$meta = $db->loadAssocList();

	$map = [];
	if ($meta) {
	    foreach ($meta as $m) {
		if (isset($m['Variable_name'], $m['Value'])) {
		    $map[$m['Variable_name']] = $m['Value'];
		}
	    }
	}

	return [$ids, $map];
    }

    private function formatTotalInfo(array $meta, int $start, int $offset, int $current, string $query): array
    {
	return [
	    'total'       => isset($meta['total']) ? (int)$meta['total'] : 0,
	    'total_found' => isset($meta['total_found']) ? (int)$meta['total_found'] : 0,
	    'offset'      => $offset,
	    'current'     => $current,
	    'start'       => $start,
	    'query'       => $query
	];
    }

    private function getJoomlaConnection()
    {
	try {
	    return (new DatabaseFactory())->getDriver('mysqli', self::pripojDatabazi('joomla'));
	} catch (\Throwable $e) {
	    Factory::getApplication()->enqueueMessage(
		Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
		'warning'
	    );
	    return null;
	}
    }
    
    private function getUserGroup($db, int $userId, string $obchodParam): int
    {
	if ($obchodParam === '1') {
	    $qGroup = $db->getQuery(true)
		    ->select($db->quoteName('virtuemart_shoppergroup_id'))
		    ->from($db->quoteName('#__virtuemart_vmuser_shoppergroups'))
		    ->where($db->quoteName('virtuemart_user_id') . '= :userid')
		    ->bind(':userid', $userId);

	    $db->setQuery($qGroup);
	    $groupRow = $db->loadRow();
	    return $groupRow[0] ?? 5; // Default shopper group if not found
	} else {
	    return 0; // Default for maloobchod
	}
    }

    private function fetchProducts($db, array $ids, int $shopperGroupId): array
    {
	$idsList = implode(',', array_map('intval', $ids));

	$published = 1;
	// Main query
	$q = $db->getQuery(true)
	    ->select([
		't1.virtuemart_product_id',
		't1.product_name',
		't3.product_in_stock',
		't3.product_availability',
		't3.product_params',
		't2.virtuemart_category_id',
		't4.product_price',

		'(SELECT m.file_url
		    FROM #__virtuemart_product_medias pm
		    JOIN #__virtuemart_medias m 
			ON m.virtuemart_media_id = pm.virtuemart_media_id
		    WHERE pm.virtuemart_product_id = t1.virtuemart_product_id
		    ORDER BY pm.ordering ASC
		    LIMIT 1
		) AS file_url',

		'(SELECT calc.calc_value
		    FROM #__virtuemart_calcs calc
		    WHERE calc.virtuemart_calc_id = t4.product_tax_id
		    LIMIT 1
		) AS calc_value',

		'(SELECT mc.mf_name
		    FROM #__virtuemart_product_manufacturers pm
		    JOIN #__virtuemart_manufacturers_cs_cz mc
			ON mc.virtuemart_manufacturer_id = pm.virtuemart_manufacturer_id
		    WHERE pm.virtuemart_product_id = t1.virtuemart_product_id
		    LIMIT 1
		) AS manufacturer',

		'(SELECT pm.virtuemart_manufacturer_id
		    FROM #__virtuemart_product_manufacturers pm
		    WHERE pm.virtuemart_product_id = t1.virtuemart_product_id
		    LIMIT 1
		) AS manufacturer_id'
	    ])

	    ->from('#__virtuemart_products_cs_cz AS t1')
	    ->join('INNER', '#__virtuemart_products AS t3 ON t1.virtuemart_product_id = t3.virtuemart_product_id')
	    ->join('INNER', '#__virtuemart_product_prices AS t4 ON t1.virtuemart_product_id = t4.virtuemart_product_id')
	    ->join('LEFT', '#__virtuemart_product_categories AS t2 ON t1.virtuemart_product_id = t2.virtuemart_product_id')

	    ->where('t1.virtuemart_product_id IN (' . implode(',', $ids) . ')')
	    ->where('t4.virtuemart_shoppergroup_id = :shopperGroup')
	    ->where('t3.published = :published')

	    ->bind(':shopperGroup', $shopperGroupId)
	    ->bind(':published', $published);

	$db->setQuery($q);
	$rows = $db->loadAssocList();

	$out = [];
	foreach ($rows as $row) {
	    $out[(int)$row['virtuemart_product_id']] = $this->formatProduct($row);
	}

	return $out;
    }

    private function formatProduct(array $row): array
    {
	$params = self::getBaleni($row['product_params']);

	$price = round($row['product_price'], 2);
	$sdph  = round($price * (1 + ($row['calc_value'] / 100)), 2);

	return [
	    'product_name'            => $row['product_name'],
	    'virtuemart_product_id'   => (int)$row['virtuemart_product_id'],
	    'virtuemart_category_id'  => $row['virtuemart_category_id'],
	    'product_availability'    => $row['product_availability'],
	    'product_price'           => $price,
	    'file_url'                => $row['file_url'],
	    'min_order_level'         => $params['min_order_level'] ?? 1,
	    'step_order_level'        => $params['step_order_level'] ?? 1,
	    'max_order_level'	      => $params['max_order_level'] ?? null,
	    's_dph'                   => $sdph,
	    'manufacturer'            => $row['mf_name'],
	    'manufacturer_id'         => $row['virtuemart_manufacturer_id'],
	    'product_in_stock'        => $row['product_in_stock'],
	];
    }

    
    
    public static function getManufacturers(?string $query_znacky = null, string $sphinxTable): array
    {
        $safeQuery = $query_znacky ? self::sanitizeSphinxQuery($query_znacky) : '';
        try {
	    $database = new DatabaseFactory();
	    $dbSphinx = $database->getDriver('mysql', self::pripojDatabazi('sphinx'));
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
                'warning'
            );
	}
        $stmt2 = $dbSphinx->getQuery(true);
        $matchClause = "MATCH('{$safeQuery}')";
        $stmt2
            ->select($dbSphinx->quoteName('mf_name'))
            ->from($dbSphinx->quoteName('#__'.$sphinxTable))
            ->where($matchClause . " GROUP BY mf_name LIMIT 0,1000 OPTION ranker=proximity_bm25");
        $dbSphinx->setQuery($stmt2);
        $manufacturers = $dbSphinx->loadColumn();
        return is_array($manufacturers) ? $manufacturers : [];
    }

    public static function getBaleni($input)
    {
	$defaults = [
	    'min_order_level'  => 1,
	    'step_order_level' => 1,
	    'max_order_level'  => null
	];

	if (!$input) {
	    return $defaults;
	}

	$out = [];

	// min_order_level
	if (preg_match('/min_order_level="([^"]*)"/', $input, $m)) {
	    $out['min_order_level'] = ($m[1] !== '') ? (int)$m[1] : 1;
	}

	// step_order_level
	if (preg_match('/step_order_level="([^"]*)"/', $input, $m)) {
	    $out['step_order_level'] = ($m[1] !== '') ? (int)$m[1] : 1;
	}

	// max_order_level
	if (preg_match('/max_order_level="([^"]*)"/', $input, $m)) {
	    $out['max_order_level'] = ($m[1] !== '') ? (int)$m[1] : null;
	}
	
	return array_merge($defaults, $out);
    }

    public static function pripojDatabazi(string $database): array
    {
        $option = [];
        switch ($database) {
            case 'sphinx':
                $option['driver']   = 'mysql';
                $option['host']     = '127.0.0.1:9306';
                $option['prefix']   = '';
                break;

            case 'joomla':
                $option['driver']   = 'mysqli';
                $option['host']     = Factory::getApplication()->get('host');
                $option['user']     = Factory::getApplication()->get('user');
                $option['password'] = Factory::getApplication()->get('password');
                $option['database'] = Factory::getApplication()->get('db');
                $option['prefix']   = Factory::getApplication()->get('dbprefix');
                break;
        }
        return $option;
    }

    public static function sanitizeSphinxQuery(string $q): string
    {
        $q = preg_replace('/[^\p{L}\d\s]/u', ' ', $q);
        $q = str_replace("'", ' ', $q);
        return trim($q);
    }
}
