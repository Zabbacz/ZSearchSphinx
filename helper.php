<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseFactory;
use Zabba\Module\ZSearchSphinx\Site\Helper\ZSearchSphinxHelper;

class ModVirtuemartZsearchsphinxHelper
{
    public static function getAjax() 
    {
        $input = Factory::getApplication()->getInput();
        if ($input -> exists('query'))
        {
            $q  = $input->post->get('query', '', 'string');
            $condition = preg_replace('/[^A-Za-z0-9\- ]/', '', $q);
            $options = ZSearchSphinxHelper::pripojDatabazi('sphinx');
            $database = new DatabaseFactory();
            $db = $database->getDriver('mysql', $options);
            $stmt = $db->getQuery(true);
            $aq = explode(' ',$q);
            if(strlen($aq[count($aq)-1])<3)
            {
        	$query = $q;
            }
            else
            {
                $query = $q.'*';
            }
            $stmt
                ->select($db->quoteName("product_name"))
                ->from($db->quoteName("#__sphinx_test1"))
                ->where("MATCH"."(".$db->quote($query).")"." ORDER BY product_in_stock DESC LIMIT  0,10 OPTION ranker=sph04");

            $db->setQuery($stmt);
            $results = $db->loadObjectList();
            $replace_string = '<b>'.$condition.'</b>';
            if($results)
            {
                foreach($results as $row)
                {
                    $data[] = array(
                        'product_name'		=>	str_ireplace($condition, $replace_string, $row->product_name)
                    );
                }
            echo json_encode($data);
            }
            else{
                echo json_encode('');
            }
        }
        $input = Factory::getApplication()->getInput()->json;
        if ($input -> exists('search_query'))
        {
            $post_data = json_decode(file_get_contents('php://input'), true);
            $data = array(
		':search_query'		=>	$post_data['search_query']
            );
            $options = ZSearchSphinxHelper::pripojDatabazi('joomla');
            $database = new DatabaseFactory();
            $db = $database->getDriver('mysqli', $options);
            $query = $db->getQuery(true);
            $query
                ->insert($db->quoteName('#__zsphinx_recent_search'))
                ->columns($db->quoteName('search_query'))
                ->values('"'.$data[':search_query'].'"');
            $db->setQuery($query);
            $db->execute();        
            $output = array(
		'success'	=>	true
            );
            echo json_encode($output);

        }
    }
}
