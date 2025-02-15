<?php 
// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Zabba\Module\ZSearchSphinx\Site\Helper\ZSearchSphinxHelper;

$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('mod_virtuemart_zsearchsphinx');
$wa->useScript('mod_virtuemart_zsearchsphinx.search');
$wa->useStyle('mod_virtuemart_zsearchsphinx.style');
$language = Factory::getApplication()->getLanguage();
$language->load('mod_virtuemart_zsearchsphinx', JPATH_BASE . '/modules/mod_virtuemart_zsearchsphinx');
?>

    <?php
$action_form = JUri::root() .'index.php/obchod/';
$input = Factory::getApplication()->getInput()
?>
<form method="GET" action= <?=$action_form?> id="search_form">
        <input type="text" name="search_box" class="form-control form-control-lg" placeholder="<?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_SEARCH_PRODUCT'); ?>"
              id="search_box" data-toggle="dropdown" autocomplete="off"
              value="<?php
                        if ($input -> exists('search_box'))
                        {
                            echo $input->get('search_box','','STRING');                                 
                        }
                        else 
                        {
                            echo '';
                        }
                        ?>"
              />
        <span id="search_result"></span>

        <input type="submit" class="btn btn-primary"
	        id="send" name="send" value="<?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_FIND'); ?>"<br />

<p class="lead">
<?php 
    if(isset($docs[count((array) ($docs))]['total_found'])):
        if ($docs[count((array) ($docs))]['total'] == 1) {
            $app = Factory::getApplication();
            $product_link = Route::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id='.($docs[0] ["virtuemart_product_id"]).'&virtuemart_category_id='.($docs[0] ["virtuemart_category_id"]));
            $app->redirect( Route::_($product_link) );
        }

        $last = count ($docs);
        $query_znacky = $docs[$last]['query'];
        $znacky = (ZSearchSphinxHelper::getManufacturers($query_znacky));
        echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_MANUFACTURERS').'<br />';     
        foreach($znacky as $znacka){
        echo'<input type="submit" name="znacky_search" class="btn btn-primary" value="'.$znacka.'">';

        }
        
        echo '<br />'.Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_FOUND_ITEMS').$docs[count((array) ($docs))]['total'].'<br />';
    endif;

?>
</form>
</p>     
    
<?php if (count((array)($docs)) > 0): ?>
    <div class="span9"><?php require_once dirname(__FILE__) . '/paginator.php';?></div>
	 <div class="vm-product-grid container my-5"> <!--1-->
		<div class="row gy-4 g-xxl-5"><!--2-->
       
<?php
    $i = 0; foreach ($docs as $doc):
        $product_id = $doc["virtuemart_product_id"] ?? null;
        $product_name = $doc['product_name'] ?? null;
	    $category_id = $doc['virtuemart_category_id'] ?? null;
	?>
    <?php if (!$product_id) continue;?>
      <div class="product col-6 col-md-6 col-lg-3 row-1 w-desc-1"><!--3-->
        <div class="product-container d-flex flex-column h-100" data-vm="product-container"><!--4-->
     	   <div class="vm-product-media-container text-center d-flex flex-column justify-content-center" style="min-height:300px"><!--5-->
			<form method="post" class="product js-recalculate" action="#">
       		<div class="main-image">
            	<div class="product-details-imege-handler"> 
                <?php $image_link = JUri::root() .'images/virtuemart/product/resized/'.$doc['file_url'];?>
                <img src=<?=$image_link;?>>
            </div> 
            <div class="clear"></div>
        </div>
        
        <?php			
        $product_link = (JURI::root().'index.php/obchod/?option=com_virtuemart&view=productdetails&virtuemart_product_id='.($doc['virtuemart_product_id']).'&virtuemart_category_id='.($doc['virtuemart_category_id'])); 
        ?>
        <div class="nazev-produktu">
            <a href="<?=$product_link; ?>"><?= $doc['product_name']?></a>
        </div>
        <br />
        <?='<strong>'.Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_AVAILABILITY').$doc['product_availability'].'</strong>' ?>
        <br />
        <br />
        <div class="cena">
            <?= "<i>".Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_PRICE').$doc['product_price']." Kč bez DPH/ks </i>"?>    
        </div>
  	<?php
        $velkoobchod = $params->get('velkoobchod_id','','STRING');
        $obchod = $params->get('obchod', '2', 'STRING');  //velkoobchod = 1, maloobchod = 2
        if(($obchod ==='1' AND (Factory::getUser()->groups[(int)$velkoobchod]===(int)$velkoobchod)) OR $obchod ==='2') {
        ?>
            <?= "<input class='quantity-input' type='number' name='quantity[]' value=".$doc['min_order_level']." step=".$doc['step_order_level'].">"?> 
            <input type="submit" name="addtocart" class="btn btn-primary" value="Do košíku" title="Do košíku">
            <input type="hidden" name="virtuemart_product_id[]" value=<?=$product_id?>>
            <noscript><input type="hidden" name="task" value="add"/></noscript>  
            <br/>
            <input type="hidden" name="option" value="com_virtuemart">
            <input type="hidden" name="view" value="cart">
            <input type="hidden" name="virtuemart_product_id[]" value=<?=$product_id?>>
            <input type="hidden" name="pname" value=<?=$product_name?>>
            <input type="hidden" name="pid" value=<?=$product_id?>>
            <input type="hidden" name="Itemid" value=<?=$category_id?>>
        <?php }?>
  </form>
    		</div><!--5-->
		</div><!--4-->
	</div><!--3-->
         
<?php $i++; endforeach; ?>
</div> <!--2-->
</div> <!--1-->

<div class="span9"><?php require dirname(__FILE__) . '/paginator.php';?></div>
    <?php 
        elseif ($input->get('search_box')): echo($input->get('search_box','','STRING')).
        "<p>".Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_NOT_FOUND').$doc['product_price']."</p>"?>    
        
        <?php endif; ?>