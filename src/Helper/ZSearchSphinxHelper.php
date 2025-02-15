<?php
namespace Zabba\Module\ZSearchSphinx\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseFactory;
//use Joomla\Registry\Registry;
//use Joomla\CMS\Router\Route;

class ZSearchSphinxHelper
{
  /** presunoto do samostatneho helpru v root - jen tam hleda com_ajax
      public function getAjax() 
   
    {
        $input = Factory::getApplication()->getInput();
        if ($input -> exists('query'))
        {
            $q  = $input->post->get('query', '', 'string');
            $condition = preg_replace('/[^A-Za-z0-9\- ]/', '', $q);
            $options = self::pripojDatabazi('sphinx');
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
                ->where("MATCH"."('".$query."')"." ORDER BY product_in_stock DESC LIMIT  0,10 OPTION ranker=sph04");

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
            $options = self::pripojDatabazi('joomla');
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
*/
    
    public function getSearch()
    {
    $input = Factory::getApplication()->getInput();
    if ($input -> exists('search_box'))
    {
        $query  = $input->get('search_box', '', 'string');
        if(strlen($query)<4){$query='musiszadattriznaky';}
        $docs = array();
        $offset = 24;
        $current = 1;
        $url = 'obchod/';
        $app     = Factory::getApplication();
        $user = $app->getIdentity();
        $userId = $user->id;
        $znacky_search = $input -> get('znacky_search','','string');
        $query_order = '%';
        if($znacky_search)
        {
            $text_db = '$db->quoteName';
            $query_order = $znacky_search;
        }
        $query .= ' '.$query_order;
        $query = (string) preg_replace('/[^\p{L}\d\s]/u', ' ', $query);
        $query = trim($query);
        $start  = $input->get('start', '0', 'INT');
        $current = $start/$offset+1;
        $options = self::pripojDatabazi('sphinx');
        $database = new DatabaseFactory();
        try {
            $db = $database->getDriver('mysql', $options);
//                $this->setDatabase($dbDriver);
            } catch (\RuntimeException $exception) {
                Factory::getApplication()->enqueueMessage(
                        Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
                        'warning'
                );
            }
            if (empty($db)) {
                throw new \RuntimeException("Joomla did not return a database object.");
            }
          
        $stmt = $db->getQuery(true);
        $stmt
            ->select ($db->quoteName('id'))
            ->from($db->quoteName('#__sphinx_test1'))
            ->where("MATCH"."('".$query."')"." ORDER BY product_in_stock DESC LIMIT "  . $start .",". $offset." OPTION ranker=sph04,field_weights=(product_name=100)");
        $db->setQuery($stmt);
        $rows = $db->loadAssocList();
        $meta=$db->setQuery('show meta');
        $meta = $db->loadAssocList();
        foreach($meta as $m) 
        {
	    $meta_map[$m['Variable_name']] = $m['Value'];
	}
        $total_found = $meta_map['total_found'];
        $total = $meta_map['total'];
        $total_array = array('total'=> $total, 'total_found' => $total_found, 'offset' => $offset, 'current' => $current,'start' => $start, 'query' => $query);
     	$ids = array();
        $tmpdocs = array();
        if (count($rows)> 0) 
        {
            foreach ($rows as  $v) 
            {
        		$ids[] =  $v['id'];
            }
        $options = self::pripojDatabazi('joomla');
        $database = new DatabaseFactory();
        $db = $database->getDriver('mysqli', $options);
            $user_group =$db->getQuery(true);
            $user_group
                    ->select ($db->quoteName ('virtuemart_shoppergroup_id'))
                    ->from ($db->quoteName ('#__virtuemart_vmuser_shoppergroups'))
                   ->where ($db->quoteName('virtuemart_user_id'). '=' .$userId);
            $db->setQuery($user_group);
            $row_user_group = $db->loadRow();
            if(!$row_user_group)
            {
                $row_user_group[0]=5;
            }
            $q = $db->getQuery(true);
            $q
                ->select ($db->quoteName (array('t1.virtuemart_product_id', 't3.product_in_stock','product_name', 'virtuemart_category_id', 'product_availability','product_price','file_url', 't3.product_params', 't7.calc_value', 't9.mf_name', 't9.virtuemart_manufacturer_id')))
                ->from($db->quoteName('#__virtuemart_products_cs_cz','t1'))
                ->join('INNER',$db->quoteName('#__virtuemart_product_prices','t4'). ' ON ' . $db->quoteName('t1.virtuemart_product_id') . ' = ' . $db->quoteName('t4.virtuemart_product_id'))    
                ->join('INNER',$db->quoteName('#__virtuemart_products','t3'). ' ON ' . $db->quoteName('t1.virtuemart_product_id') . ' = ' . $db->quoteName('t3.virtuemart_product_id'))
                ->join('INNER',$db->quoteName('#__virtuemart_product_medias','t5'). ' ON ' . $db->quoteName('t1.virtuemart_product_id') . ' = ' . $db->quoteName('t5.virtuemart_product_id'))    
                ->join('INNER',$db->quoteName('#__virtuemart_medias','t6'). ' ON ' . $db->quoteName('t5.virtuemart_media_id') . ' = ' . $db->quoteName('t6.virtuemart_media_id'))
                ->join('INNER',$db->quoteName('#__virtuemart_calcs','t7'). ' ON ' . $db->quoteName('t4.product_tax_id') . ' = ' . $db->quoteName('t7.virtuemart_calc_id'))
                ->join('INNER',$db->quoteName('#__virtuemart_product_manufacturers','t8'). ' ON ' . $db->quoteName('t1.virtuemart_product_id') . ' = ' . $db->quoteName('t8.virtuemart_product_id'))
                ->join('INNER',$db->quoteName('#__virtuemart_manufacturers_cs_cz','t9'). ' ON ' . $db->quoteName('t8.virtuemart_manufacturer_id') . ' = ' . $db->quoteName('t9.virtuemart_manufacturer_id'))
                ->join('LEFT',$db->quoteName('#__virtuemart_product_categories','t2'). ' ON ' . $db->quoteName('t1.virtuemart_product_id') . ' = ' . $db->quoteName('t2.virtuemart_product_id'))
                ->where($db->quoteName('t1.virtuemart_product_id'). ' IN '.'  (' . implode(",", $ids) . ')')
                ->where($db->quoteName('t4.virtuemart_shoppergroup_id'). ' = '.$row_user_group[0]);
            $db->setQuery ($q);
            $q = $db->loadAssocList(); 
            foreach ($q as $row) 
            {
                $parametry = self::getBaleni($row['product_params']);
                $price = Round($row['product_price'],2);
                $sdph = Round($price*(1+($row['calc_value']/100)),2);
                $tmpdocs[$row['virtuemart_product_id']] = array(
                    'product_name' => $row['product_name'], 
                    'virtuemart_product_id' => $row['virtuemart_product_id'],
                    'virtuemart_category_id' => $row['virtuemart_category_id'],
                    'product_availability' => $row['product_availability'],
                    'product_price' => $price,
                    'file_url' => $row['file_url'],
                    'min_order_level' => $parametry['min_order_level'],
                    'step_order_level' => $parametry['step_order_level'],
                    's_dph' => $sdph, 
                    'manufacturer' => $row['mf_name'],
                    'manufacturer_id' => $row['virtuemart_manufacturer_id'],
                    'product_in_stock' => $row['product_in_stock'],);
            } 
            foreach ($ids as $id)
            {
                $docs[] = $tmpdocs[$id];
            }
            $last = count ($docs)+1;
            $docs[$last]=$total_array;
	}
    }
	return $docs;  
    }

    public static function getManufacturers($query_znacky = null)
    {
        $options = self::pripojDatabazi('sphinx');
        $database = new DatabaseFactory();
        $db = $database->getDriver('mysql', $options);
        $stmt2 = $db->getQuery(true);
        $stmt2
            ->select ($db->quoteName('mf_name'))
            ->from($db->quoteName('#__sphinx_test1'))
            ->where("MATCH"."('".$query_znacky."')"." GROUP BY mf_name LIMIT  0,1000 OPTION ranker=sph04");
        $db->setQuery($stmt2);
        $manufacturers = $db->loadColumn();
        return $manufacturers;
    
    }

    public static function getBaleni($vstup) 
    {
        mb_internal_encoding("UTF-8");
        $vystup = array();
        if($vstup)
        {
            $delka = mb_strlen($vstup);
            $podretezec = mb_strpos($vstup,':');
            $nahrad = array(' ' => '');
            $vstup_zprac = (mb_substr(strtr($vstup,$nahrad), $podretezec, $delka));
            $cisla = explode('|', $vstup_zprac);
            $i = 0; foreach ($cisla as $cislo):
                $polozka = array(str_replace('"','',explode('=',($cisla[$i]))));
                if(isset($polozka[0][1]))
                {
                    $key = $polozka[0][0];
                    $value = $polozka[0][1];
                    $vystup[$key] = $value;
                }
            $i++;
            endforeach;
        }
        else
        {
            $vystup["min_order_level"] = "1";
            $vystup["step_order_level"] = "1";

        }   
    return($vystup);
    }

    public static function pripojDatabazi($database) 
    {
        $option = array();
        switch ($database)
        {
            case 'sphinx':
                $option['driver']   = 'mysql';            // Database driver name
                $option['host']     = '127.0.0.1:9306';    // Database host name
                $option['prefix']   = '';             // Database prefix (may be empty)

            break;
            case 'joomla':
                $option['driver']   = 'mysqli';            // Database driver name
                $option['host']     = Factory::getApplication()->get('host');    // Database host name
                $option['user']     = Factory::getApplication()->get('user');       // User for database authentication
                $option['password'] = Factory::getApplication()->get('password');   // Password for database authentication
                $option['database'] = Factory::getApplication()->get('db');     // Database name
                $option['prefix']   = Factory::getApplication()->get('dbprefix');             // Database prefix (may be empty)
            break;

        }     
    return $option;
       
    }
}
