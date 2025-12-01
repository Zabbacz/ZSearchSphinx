<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseFactory;
use Zabba\Module\ZSearchSphinx\Site\Helper\ZSearchSphinxHelper;

class ModVirtuemartZsearchsphinxHelper
{
    public static function getAjax()
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
	
	$sphinxTable = self::getTableSphinx();
	$tableFinal = '#__'.$sphinxTable;

	/* ============================================================
         * AUTOCOMPLETE
         * ============================================================ */
        if ($input->exists('query')) {

            $qRaw = trim($input->post->get('query', '', 'string'));
            $condition = preg_replace('/[^A-Za-z0-9\- ]/', '', $qRaw);

            $options = ZSearchSphinxHelper::pripojDatabazi('sphinx');
            $db = (new DatabaseFactory())->getDriver('mysql', $options);

            // vytvořit dotaz
            $last = explode(' ', $qRaw);
            $lastToken = end($last);

            $queryFinal = (strlen($lastToken) < 3)
                ? $qRaw
                : $qRaw . '*';

	    $stmt = $db->getQuery(true)
		->select('product_name')
	    ->from($tableFinal)
	    ->where("
		MATCH('{$queryFinal}')
		ORDER BY product_in_stock DESC
		LIMIT 0, 10
		OPTION ranker=proximity_bm25
	    ");

            $db->setQuery($stmt);
            $rows = $db->loadObjectList();

            $data = [];

            if ($rows) {
                foreach ($rows as $row) {
                    $data[] = [
                        'product_name' => str_ireplace($condition, '<b>' . $condition . '</b>', $row->product_name)
                    ];
                }
            }

            echo json_encode($data);
            return;
        }

        /* ============================================================
         * RECENT SEARCH
         * ============================================================ */
        $jsonInput = $input->json;

        if ($jsonInput->exists('search_query')) {

            $post = json_decode(file_get_contents('php://input'), true);

            if (!$post || empty($post['search_query'])) {
                echo json_encode(['success' => false]);
                return;
            }

            $search = trim($post['search_query']);

            $options = ZSearchSphinxHelper::pripojDatabazi('joomla');
            $db = (new DatabaseFactory())->getDriver('mysqli', $options);

            $query = $db->getQuery(true)
                ->insert('#__zsphinx_recent_search')
                ->columns('search_query')
                ->values($db->quote($search));

            $db->setQuery($query);
            $db->execute();

            echo json_encode(['success' => true]);
            return;  
        }

        // fallback
        echo json_encode([]);
        return;
    }
    
    private static function getTableSphinx() {
	$module = 'mod_virtuemart_zsearchsphinx';

        $options = ZSearchSphinxHelper::pripojDatabazi('joomla');
        $db = (new DatabaseFactory())->getDriver('mysqli', $options);

	$query = $db->getQuery(true)
	    ->select('JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('params') . ", '$.sphinx_table')) AS " . $db->quoteName('sphinx_table'))
	    ->from($db->quoteName('#__modules'))
	    ->where($db->quoteName('module') . ' = :module')
	    ->bind(':module', $module);
	$db->setQuery($query);
	return $db->loadResult();

    }
}

