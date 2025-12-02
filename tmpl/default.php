<?php
// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Zabba\Module\ZSearchSphinx\Site\Helper\ZSearchSphinxHelper;
use Joomla\CMS\HTML\HTMLHelper;

$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('mod_virtuemart_zsearchsphinx');
$wa->useScript('mod_virtuemart_zsearchsphinx.search');
$wa->useStyle('mod_virtuemart_zsearchsphinx.style');
$language = Factory::getApplication()->getLanguage();
$language->load('mod_virtuemart_zsearchsphinx', JPATH_BASE . '/modules/mod_virtuemart_zsearchsphinx');

$obchodParam = $params->get('obchod', '2', 'STRING');  // velkoobchod = 1, maloobchod = 2
$velkoobchod = $params->get('velkoobchod_id', '1', 'INT'); // Skupina velkoobchodních uživatelů
$viewStock = $params->get('stock', '0', 'INT'); //ma se zobrazovat mnozstvi skladem ?
$sphinxTable = $params->get('sphinx_table', '', 'STRING');
$action_form = Uri::root() . 'index.php/obchod/';
$input = Factory::getApplication()->getInput();
// Získáme výsledky z helperu
//$helperInstance = new \Zabba\Module\ZSearchSphinx\Site\Helper\ZSearchSphinxHelper();
//$docs=$helperInstance->getSearch($params);
if (!is_array($docs)) {
    $docs = [];
}
?>

<form method="GET" action="<?= htmlspecialchars($action_form, ENT_QUOTES, 'UTF-8') ?>" id="search_form">
    <input type="text" name="search_box" class="form-control form-control-lg"
           placeholder="<?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_SEARCH_PRODUCT'); ?>"
           id="search_box" data-toggle="dropdown" autocomplete="off"
           value="<?php echo $input->exists('search_box') ? htmlspecialchars($input->get('search_box', '', 'STRING'), ENT_QUOTES, 'UTF-8') : ''; ?>"
    />
    <span id="search_result"></span>

    <input type="submit" class="btn btn-primary" id="send" name="send" value="<?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_FIND'); ?>" />
    <p class="lead">
    <?php
    // Souhrn a výpis značek — poslední prvek v $docs by měl být total_array podle helperu
    $lastIndex = count($docs) - 1;
    $hasSummary = $lastIndex >= 0 && is_array($docs[$lastIndex]) && array_key_exists('total_found', $docs[$lastIndex]);

    if ($hasSummary) {
        $summary = $docs[$lastIndex];
        // pokud je pouze 1 produkt a ostatní indexy obsahují data -> přesměrujeme přímo na produkt
        $total = (int) ($summary['total'] ?? 0);
        if ($total === 1 && !empty($docs[0]) && !empty($docs[0]['virtuemart_product_id'])) {
            $first = $docs[0];
            $product_link = Route::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . ((int)$first['virtuemart_product_id']) . '&virtuemart_category_id=' . ((int)($first['virtuemart_category_id'] ?? 0)));
            $app->redirect($product_link);
            // exit pro jistotu
            exit;
        }

        $query_znacky = $summary['query'] ?? '';
        $znacky = ZSearchSphinxHelper::getManufacturers($query_znacky, $sphinxTable);
        echo '<strong>' . Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_MANUFACTURERS') . '</strong><br />';
        if (is_array($znacky) && count($znacky) > 0) {
            foreach ($znacky as $znacka) {
                $safe = htmlspecialchars($znacka, ENT_QUOTES, 'UTF-8');
                echo '<button type="submit" name="znacky_search" value="' . $safe . '" class="btn btn-primary">' . $safe . '</button> ';
            }
        }

        $foundTotal = (int) ($summary['total'] ?? 0);
        echo '<br />' . Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_FOUND_ITEMS') . ' ' . $foundTotal . '<br />';
    }
    ?>
    </p>
</form>

<?php if (count($docs) > 0 && (! $hasSummary || (count($docs) > 1))): ?>
    <div class="span9"><?php require_once dirname(__FILE__) . '/paginator.php'; ?></div>

    <div class="vm-product-grid container my-5">
        <div class="row gy-4 g-xxl-5">
            <?php
            // Poslední prvek je summary; projdeme všechny kromě posledního
            $items = $hasSummary ? array_slice($docs, 0, $lastIndex) : $docs;
            foreach ($items as $doc):
                if (empty($doc) || empty($doc['virtuemart_product_id'])) {
                    continue;
                }
                $product_id = (int) $doc['virtuemart_product_id'];
                $product_name = htmlspecialchars($doc['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $category_id = (int) ($doc['virtuemart_category_id'] ?? 0);
                $image_path = Uri::root() . 'images/virtuemart/product/resized/' . ($doc['file_url'] ?? '');
                $product_link = Uri::root() . 'index.php/obchod/?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $product_id . '&virtuemart_category_id=' . $category_id;
                $availability = htmlspecialchars($doc['product_availability'] ?? '', ENT_QUOTES, 'UTF-8');
                $in_stock = (int) ($doc['product_in_stock'] ?? 0);
                $price = isset($doc['product_price']) ? number_format((float)$doc['product_price'], 2) : '';
                $s_dph = isset($doc['s_dph']) ? round((float)$doc['s_dph'], 0) : '';
                ?>
                <div class="product col-6 col-md-6 col-lg-3 row-1 w-desc-1">
                    <div class="product-container d-flex flex-column h-100" data-vm="product-container">
                        <div class="vm-product-media-container text-center d-flex flex-column justify-content-center" style="min-height:300px">
                            <form method="post" class="product js-recalculate" action="#">
                                <div class="main-image">
                                    <div class="product-thumnails-image-handler">
                                        <img src="<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $product_name ?>" />
                                    </div>
                                    <div class="clear"></div>
                                </div>

                                <div class="nazev-produktu">
                                    <a href="<?= htmlspecialchars($product_link, ENT_QUOTES, 'UTF-8') ?>"><?= $product_name ?></a>
                                </div>
                                <br />
                                <strong><?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_AVAILABILITY') . ' ' . $availability; ?></strong>
                                <br />
				<?php if($viewStock === '1'){?>
                                <strong><?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_STOCK') . ' ' . $in_stock; ?></strong>
				<?php } ?>
                                <br />

                                <?php if ($obchodParam === '1'): ?>
                                    <div class="cena">
                                        <i><?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_PRICE') . ' ' . $price . ' Kč bez DPH/ks'; ?></i>
                                    </div>
                                <?php else: ?>
                                    <div class="cena mt-2 mb-2">
                                        <i><?php echo htmlspecialchars(Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_PRICE') . ' ' . $s_dph . ' Kč /ks', ENT_QUOTES, 'UTF-8'); ?></i>
                                    </div>
                                <?php endif; ?>

			<?php
			    if(($obchodParam ==='1' AND (Factory::getUser()->groups[(int)$velkoobchod]===(int)$velkoobchod)) OR $obchodParam ==='2') {
                                $minOrder = htmlspecialchars($doc['min_order_level'] ?? '1', ENT_QUOTES, 'UTF-8');
                                $stepOrder = htmlspecialchars($doc['step_order_level'] ?? '1', ENT_QUOTES, 'UTF-8');
                        ?>
				<input class="quantity-input"
				    type="number"
				    name="quantity[]"
				    value="<?php echo $minOrder; ?>"
				    min="<?php echo $minOrder; ?>"
				    step="<?php echo $stepOrder; ?>" />

				<input type="submit"
				    name="addtocart"
				    class="btn btn-primary addtocart-button mt-3"
				    value="<?php echo Text::_('COM_VIRTUEMART_CART_ADD_TO'); ?>" />
				
			    <input type="hidden" name="virtuemart_product_id[]" value="<?php echo $product_id; ?>" />
			    <input type="hidden" name="option" value="com_virtuemart" />
			    <input type="hidden" name="view" value="cart" />
			    <noscript><input type="hidden" name="task" value="add"/></noscript>  
			<?php					
			    }					    
			else{
			    echo 'Nakupovat mohou pouze registrovaní obchodníci.';
			}
			?>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="span9"><?php require dirname(__FILE__) . '/paginator.php'; ?></div>

<?php elseif ($input->get('search_box')): ?>
    <p><?php echo Text::_('MOD_VIRTUEMART_ZSEARCHSPHINX_NOT_FOUND'); ?></p>
<?php endif; ?>
