<?php
/* Copyright (C) 2016		ATM Consulting			<support@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/inventory/inventory.php
 *	\ingroup    product
 *	\brief      File of class to manage inventory
 */
 
require_once '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/listview.class.php';
require_once DOL_DOCUMENT_ROOT.'/inventory/class/inventory.class.php';
require_once DOL_DOCUMENT_ROOT.'/inventory/lib/inventory.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

$langs->load('stock');
$langs->load('inventory');

if(empty($user->rights->inventory->read)) accessforbidden();

_action();

function _action() 
{
	global $user, $db, $conf, $langs;	
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/

	$action=GETPOST('action');
	
	switch($action) {
		case 'create':
			if (empty($user->rights->inventory->create)) accessforbidden();
			
			$inventory = new Inventory($db);
			
			card_warehouse( $inventory);

			break;
		
		case 'confirmCreate':
			if (empty($user->rights->inventory->create)) accessforbidden();
		
			$inventory = new Inventory($db);
			$inventory->setValues($_POST);
			
            $fk_inventory = $inventory->create($user);
			if($fk_inventory>0) {
            	
            	$fk_category = (int) GETPOST('fk_category');
            	$fk_supplier = (int) GETPOST('fk_supplier');
            	$fk_warehouse = (int) GETPOST('fk_warehouse');
            	$only_prods_in_stock = (int) GETPOST('OnlyProdsInStock');
            	
            	$inventory->addProductsFor($fk_warehouse,$fk_category,$fk_supplier,$only_prods_in_stock);
            	$inventory->update($user);
            	
            	header('Location: '.dol_buildpath('/inventory/inventory.php?id='.$inventory->id.'&action=edit', 1));
            	
            }
            else{
            	
            	setEventMessage($inventory->error,'errors');
            	header('Location: '.dol_buildpath('/inventory/inventory.php?action=create', 1));
            }
            
			break;
			
		case 'edit':
			if (!$user->rights->inventory->write) accessforbidden();
			
			
			$inventory = new Inventory($db);
			$inventory->fetch(GETPOST('id'));
			
			card($inventory, GETPOST('action'));
			
			break;
			
		case 'save':
			if (!$user->rights->inventory->write) accessforbidden();
			
			
			$id = GETPOST('id');
			
			$inventory = new Inventory($db);
			$inventory->fetch($id);
			
			$inventory->setValues($_REQUEST);
			
			if ($inventory->errors)
			{
				setEventMessage($inventory->errors, 'errors');
				card( $inventory, 'edit');
			}
			else 
			{
				$inventory->udpate($user);
				header('Location: '.dol_buildpath('inventory/inventory.php?id='.$inventory->getId().'&action=view', 1));
			}
			
			break;
			
		case 'confirm_regulate':
			if (!$user->rights->inventory->write) accessforbidden();
			$id = GETPOST('id');
			
			$inventory = new Inventory($db);
			$inventory->fetch($id);
            
            if($inventory->status == 0) {
                $inventory->status = 1;
                $inventory->update($user);
                
                card( $inventory, 'view');
                
            
            }
            else {
               card( $inventory, 'view');
            }
            
			break;
			
		case 'confirm_changePMP':
			
			$id = GETPOST('id');
			
			$inventory = new Inventory($db);
			$inventory->fetch( $id );
			
			$inventory->changePMP($user);
			
			card( $inventory, 'view');
			
			break;
			
		case 'add_line':
			if (!$user->rights->inventory->write) accessforbidden();
			
			$id = GETPOST('id');
			$fk_warehouse = GETPOST('fk_warehouse');
			
			$inventory = new Inventory($db);
			$inventory->fetch( $id );
			
			$fk_product = GETPOST('fk_product');
			if ($fk_product>0)
			{
				$product = new Product($db);
				if($product->fetch($fk_product)<=0 || $product->type != 0) {
					setEventMessage($langs->trans('ThisIsNotAProduct'),'errors');
				}
				else{
					
					//Check product not already exists
					$alreadyExists = false;
					if(!empty($inventory->Inventorydet)) {
						foreach ($inventory->Inventorydet as $invdet)
						{
							if ($invdet->fk_product == $product->id
								&& $invdet->fk_warehouse == $fk_warehouse)
							{
								$alreadyExists = true;
								break;
							}
						}
					}
					if (!$alreadyExists)
					{
					    if($inventory->addProduct($product->id, $fk_warehouse)) {
					    	setEventMessage($langs->trans('ProductAdded'));
					    }
					}
					else
					{
						setEventMessage($langs->trans('inventoryWarningProductAlreadyExists'), 'warnings');
					}
					
				}
				
				$inventory->update($user);
				$inventory->sortDet();
			}
			
			card( $inventory, 'edit');
			
			break;
			
		case 'confirm_delete_line':
			if (!$user->rights->inventory->write) accessforbidden();
			
			
			//Cette action devrais se faire uniquement si le status de l'inventaire est à 0 mais aucune vérif
			$rowid = GETPOST('rowid');
			$Inventorydet = new Inventorydet($db);
			if($Inventorydet->fetch($rowid)>0) {
				$Inventorydet->delete($user);
				setEventMessage("ProductDeletedFromInventory");
			}
			$id = GETPOST('id');
			$inventory = new Inventory($db);
			$inventory->fetch( $id);
			
			card($inventory, 'edit');
			
			break;
        case 'confirm_flush':
            if (!$user->rights->inventory->create) accessforbidden();
            
            
            $id = GETPOST('id');
            
            $inventory = new Inventory($db);
            $inventory->fetch($id);
            
            $inventory->deleteAllLine($user);
            
            setEventMessage($langs->trans('InventoryFlushed'));
            
            card( $inventory, 'edit');
           
            
            break;
		case 'confirm_delete':
			if (!$user->rights->inventory->create) accessforbidden();
            
			
			$id = GETPOST('id');
			
			$inventory = new Inventory($db);
			$inventory->fetch($id);
			
			$inventory->delete($user);
			
			setEventMessage($langs->trans('InventoryDeleted'));
			
			header('Location: '.dol_buildpath('/inventory/list.php', 1));
			exit;
			
			break;
		case 'exportCSV':
			
			$id = GETPOST('id');
			
			$inventory = new Inventory($db);
			$inventory->fetch($id);
			
			_exportCSV($inventory);
			
			exit;
			break;
			
		default:
			if (!$user->rights->inventory->write) accessforbidden();
				
			$id = GETPOST('id');
				
			$inventory = new Inventory($db);
			$inventory->fetch($id);
				
			card($inventory, $action );
				
			
			break;
	}
	
}

function card_warehouse(&$inventory)
{
	global $langs,$conf,$db, $user, $form;
	
	dol_include_once('/categories/class/categorie.class.php');    
        
	llxHeader('',$langs->trans('inventorySelectWarehouse'),'','');
	print dol_get_fiche_head(inventoryPrepareHead($inventory));
	
	echo '<form name="confirmCreate" action="'.$_SERVER['PHP_SELF'].'" method="post" />';
	echo '<input type="hidden" name="action" value="confirmCreate" />';
	
    $formproduct = new FormProduct($db);
    
    ?>
    <table class="border" width="100%" >
        <tr>
            <td><?php echo $langs->trans('Title') ?></td>
            <td><input type="text" name="title" value="" size="50" /></td> 
        </tr>
        <tr>
            <td><?php echo $langs->trans('Date') ?></td>
            <td><?php echo $form->select_date(time(),'date_inventory'); ?></td> 
        </tr>
        
        <tr>
            <td><?php echo $langs->trans('inventorySelectWarehouse') ?></td>
            <td><?php echo $formproduct->selectWarehouses('', 'fk_warehouse') ?></td> 
        </tr>
        
        <tr>
            <td><?php echo $langs->trans('SelectCategory') ?></td>
            <td><?php echo $form->select_all_categories(0,'', 'fk_category') ?></td> 
        </tr>
        <tr>
            <td><?php echo $langs->trans('SelectFournisseur') ?></td>
            <td><?php echo $form->select_thirdparty('','fk_supplier','s.fournisseur = 1') ?></td> 
        </tr>
        <tr>
            <td><?php echo $langs->trans('OnlyProdsInStock') ?></td>
            <td><input type="checkbox" name="OnlyProdsInStock" value="1"></td> 
        </tr>
        
    </table>
    <?php
    
	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('inventoryConfirmCreate').'" />';
	print '</div>';
	
	echo '</form>';
	
	llxFooter('');
}

function card(&$inventory, $action='edit')
{
	global $langs, $conf, $db, $user,$form;
	
	llxHeader('',$langs->trans('inventoryEdit'),'','');
	
	if($action == 'changePMP')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$inventory->id, $langs->trans('ApplyNewPMP'), $langs->trans('ConfirmApplyNewPMP', $inventory->getTitle()), 'confirm_changePMP', array(),'no',1);
	}
	else if($action == 'flush')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$inventory->id,$langs->trans('FlushInventory'),$langs->trans('ConfirmFlushInventory',$inventory->getTitle()),'confirm_flush',array(),'no',1);
	}
	else if($action == 'delete')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$inventory->id,$langs->trans('Delete'),$langs->trans('ConfirmDelete',$inventory->getTitle()),'confirm_delete',array(),'no',1);
	}
	else if($action == 'delete_line')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$inventory->id.'&rowid='.GETPOST('rowid'),$langs->trans('DeleteLine'),$langs->trans('ConfirmDeleteLine',$inventory->getTitle()),'confirm_delete_line',array(),'no',1);
	}
	else if($action == 'regulate')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$inventory->id,$langs->trans('RegulateStock'),$langs->trans('ConfirmRegulateStock',$inventory->getTitle()),'confirm_regulate',array(),'no',1);
	}
	
	$warehouse = new Entrepot($db);
	$warehouse->fetch($inventory->fk_warehouse);
	
	print dol_get_fiche_head(inventoryPrepareHead($inventory, $langs->trans('inventoryOfWarehouse', $warehouse->libelle), empty($action) ? '': '&action='.$action));
	
	$lines = array();
	card_line($inventory, $lines, $action);
	
	print '<b>'.$langs->trans('inventoryOnDate')." ".$inventory->getDate('date_inventory').'</b><br><br>';
	
	$inventoryTPL = array(
		'id'=> $inventory->id
		,'date_cre' => $inventory->getDate('date_cre', 'd/m/Y')
		,'date_maj' => $inventory->getDate('date_maj', 'd/m/Y H:i')
		,'fk_warehouse' => $inventory->fk_warehouse
		,'status' => $inventory->status
		,'entity' => $inventory->entity
		,'amount' => price( round($inventory->amount,2) )
		,'amount_actual'=>price (round($inventory->amount_actual,2))
		
	);
	
	$can_validate = !empty($user->rights->inventory->validate);
	$view_url = dol_buildpath('/inventory/inventory.php', 1);
	
	$view = array(
		'mode' => $action
		,'url' => dol_buildpath('/inventory/inventory.php', 1)
		,'can_validate' => (int) $user->rights->inventory->validate
		,'is_already_validate' => (int) $inventory->status
		,'token'=>$_SESSION['newtoken']
	);
	
	include './tpl/inventory.tpl.php';
	
	llxFooter('');
}


function card_line(&$inventory, &$lines, $mode)
{
	global $db,$langs,$user,$conf;
	$inventory->amount_actual = 0;
	
	$TCacheEntrepot = array();

	foreach ($inventory->Inventorydet as $k => $Inventorydet)
	{
	    
        $product = & $Inventorydet->product;
		$stock = $Inventorydet->qty_stock;
	
        $pmp = $Inventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $Inventorydet->pa;
		$current_pa = $Inventorydet->current_pa;
        
		$e = new Entrepot($db);
		if(!empty($TCacheEntrepot[$Inventorydet->fk_warehouse])) $e = $TCacheEntrepot[$Inventorydet->fk_warehouse];
		elseif($e->fetch($Inventorydet->fk_warehouse) > 0) $TCacheEntrepot[$e->id] = $e;
		
		$qty = (float)GETPOST('qty_to_add')[$k];
		
		$lines[]=array(
			'produit' => $product->getNomUrl(1).'&nbsp;-&nbsp;'.$product->label,
			'entrepot'=>$e->getNomUrl(1),
			'barcode' => $product->barcode,
			'qty' =>($mode == 'edit' ? '<input type="text" name="qty_to_add['.$k.']" value="'.$qty.'" size="8" style="text-align:center;" /> <a id="a_save_qty_'.$k.'" href="javascript:save_qty('.$k.')">'.img_picto($langs->trans('Add'), 'plus16@inventory').'</a>' : '' ),
			'qty_view' => ($Inventorydet->qty_view ? $Inventorydet->qty_view : 0),
			'qty_stock' => $stock,
			'qty_regulated' => ($Inventorydet->qty_regulated ? $Inventorydet->qty_regulated : 0),
			'action' => ($user->rights->inventory->write && $mode=='edit' ? '<a href="'.dol_buildpath('inventory/inventory.php?id='.$inventory->id.'&action=delete_line&rowid='.$Inventorydet->id, 1).'">'.img_picto($langs->trans('inventoryDeleteLine'), 'delete').'</a>' : ''),
			'pmp_stock'=>round($pmp_actual,2),
            'pmp_actual'=> round($pmp * $Inventorydet->qty_view,2),
			'pmp_new'=>(!empty($user->rights->inventory->changePMP) && $mode == 'edit' ? '<input type="text" name="new_pmp['.$k.']" value="'.$Inventorydet->new_pmp.'" size="8" style="text-align:right;" /> <a id="a_save_new_pmp_'.$k.'" href="javascript:save_pmp('.$k.')">'.img_picto($langs->trans('Save'), 'bt-save.png@inventory').'</a>' :  price($Inventorydet->new_pmp)),
            'pa_stock'=>round($last_pa * $stock,2),
            'pa_actual'=>round($last_pa * $Inventorydet->qty_view,2),
			'current_pa_stock'=>round($current_pa * $stock,2),
			'current_pa_actual'=>round($current_pa * $Inventorydet->qty_view,2),
            'k'=>$k,
            'id'=>$Inventorydet->id
		);
	}

}

function _exportCSV(&$inventory) {
	global $conf;
	
	header('Content-Type: application/octet-stream');
    header('Content-disposition: attachment; filename=inventory-'. $inventory->getId().'-'.date('Ymd-His').'.csv');
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
	
	echo 'Ref;Label;barcode;qty theorique;PMP;dernier PA;';
	if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) echo 'PA courant;';
	echo 'qty réelle;PMP;dernier PA;';
	if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) echo 'PA courant;';
	echo 'qty regulée;'."\r\n";
	
	foreach ($inventory->Inventorydet as $k => $Inventorydet)
	{
		$product = & $Inventorydet->product;
		$stock = $Inventorydet->qty_stock;
	
        $pmp = $Inventorydet->pmp;
		$pmp_actual = $pmp * $stock;
		$inventory->amount_actual+=$pmp_actual;

        $last_pa = $Inventorydet->pa;
        $current_pa = $Inventorydet->current_pa;
		
		if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) {
			$row=array(
				'produit' => $product->ref
				,'label'=>$product->label
				,'barcode' => $product->barcode
				,'qty_stock' => $stock
				,'pmp_stock'=>round($pmp_actual,2)
	            ,'pa_stock'=>round($last_pa * $stock,2)
				,'current_pa_stock'=>round($current_pa * $stock,2)
			    ,'qty_view' => $Inventorydet->qty_view ? $Inventorydet->qty_view : 0
				,'pmp_actual'=>round($pmp * $Inventorydet->qty_view,2)
	            ,'pa_actual'=>round($last_pa * $Inventorydet->qty_view,2)
	        	,'current_pa_actual'=>round($current_pa * $Inventorydet->qty_view,2)    
				,'qty_regulated' => $Inventorydet->qty_regulated ? $Inventorydet->qty_regulated : 0
				
			);
			
		}
		else{
			$row=array(
				'produit' => $product->ref
				,'label'=>$product->label
				,'barcode' => $product->barcode
				,'qty_stock' => $stock
				,'pmp_stock'=>round($pmp_actual,2)
	            ,'pa_stock'=>round($last_pa * $stock,2)
	            ,'qty_view' => $Inventorydet->qty_view ? $Inventorydet->qty_view : 0
				,'pmp_actual'=>round($pmp * $Inventorydet->qty_view,2)
	            ,'pa_actual'=>round($last_pa * $Inventorydet->qty_view,2)
	            
				,'qty_regulated' => $Inventorydet->qty_regulated ? $Inventorydet->qty_regulated : 0
				
		);
			
		}
		
		
		echo '"'.implode('";"', $row).'"'."\r\n";
		
	}
	
	exit;
}

function _footerList($view,$total_pmp,$total_pmp_actual,$total_pa,$total_pa_actual, $total_current_pa,$total_current_pa_actual) {
	global $conf,$user,$langs;
	
	    if ($view['can_validate'] == 1) { ?>
        <tr style="background-color:#dedede;">
            <th colspan="3">&nbsp;</th>
            <?php if (! empty($conf->barcode->enabled)) { ?>
					<th align="center">&nbsp;</td>
			<?php } ?>
            <th align="right"><?php echo price($total_pmp) ?></th>
            <th align="right"><?php echo price($total_pa) ?></th>
            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="right">'.price($total_current_pa).'</th>';   	
					 }
			?>
            <th>&nbsp;</th>
            <th align="right"><?php echo price($total_pmp_actual) ?></th>
            <?php
            if(!empty($user->rights->inventory->changePMP)) {
               	echo '<th>&nbsp;</th>';	
			}
			?>
            <th align="right"><?php echo price($total_pa_actual) ?></th>
            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="right">'.price($total_current_pa_actual).'</th>';   	
					 }
			?>

            <th>&nbsp;</th>
            <?php if ($view['is_already_validate'] != 1) { ?>
            <th>&nbsp;</th>
            <?php } ?>
        </tr>
        <?php } 
}
function _headerList($view) {
	global $conf,$user,$langs;
	
	?>
			<tr style="background-color:#dedede;">
				<th align="left" width="20%">&nbsp;&nbsp;Produit</th>
				<th align="center"><?php echo $langs->trans('Warehouse'); ?></th>
				<?php if (! empty($conf->barcode->enabled)) { ?>
					<th align="center"><?php echo $langs->trans('Barcode'); ?></th>
				<?php } ?>
				<?php if ($view['can_validate'] == 1) { ?>
					<th align="center" width="20%"><?php echo $langs->trans('TheoricalQty'); ?></th>
					<?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th align="center" width="20%" colspan="3">'.$langs->trans('TheoricalValue').'</th>';   	
					 }
					 else {
					 	echo '<th align="center" width="20%" colspan="2">'.$langs->trans('TheoricalValue').'</th>';
					 }
					 
					?>
					
				<?php } ?>
				    <th align="center" width="20%"><?php echo $langs->trans('RealQty'); ?></th>
				<?php if ($view['can_validate'] == 1) { ?>
				    
				    <?php
				    
				     $colspan = 2;
					 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) $colspan++;
				     if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)) $colspan++;
					
	                 echo '<th align="center" width="20%" colspan="'.$colspan.'">'.$langs->trans('RealValue').'</th>';
					 
					?>
						
					<th align="center" width="15%"><?php echo $langs->trans('RegulatedQty'); ?></th>
				<?php } ?>
				<?php if ($view['is_already_validate'] != 1) { ?>
					<th align="center" width="5%">#</th>
				<?php } ?>
				
			</tr>
			<?php if ($view['can_validate'] == 1) { ?>
	    	<tr style="background-color:#dedede;">
	    	    <th colspan="<?php echo empty($conf->barcode->enabled) ? 3 : 4;  ?>">&nbsp;</th>
	    	    <th><?php echo $langs->trans('PMP'); ?></th>
	    	    <th><?php echo $langs->trans('LastPA'); ?></th>
	    	    <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th>'.$langs->trans('CurrentPA').'</th>';   	
					 }
					 
				?>
	    	    <th>&nbsp;</th>
	    	    <th><?php echo $langs->trans('PMP'); ?></th>
	    	    <?php
	    	    if(!empty($user->rights->inventory->changePMP)) {
	    	    	echo '<th rel="newPMP">'.$langs->trans('ColumnNewPMP').'</th>';
	    	    }
	    	    ?>
	            <th><?php echo $langs->trans('LastPA'); ?></th>
	            <?php
	                 if(!empty($conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA)){
	              		echo '<th>'.$langs->trans('CurrentPA').'</th>';   	
					 }
					 
				?>
	            <th>&nbsp;</th>
	            <?php if ($view['is_already_validate'] != 1) { ?>
	            <th>&nbsp;</th>
	            <?php } ?>
	    	</tr>
	    	<?php 
	} 
	
}