<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

/**
 * @var $arParams array
 * @var $arResult array
 */

$this->setFrameMode(true);

$cols = array_keys($arResult['FIELDS']);
?>
<table class="table table-bordered table-striped">
	<tbody>
		<tr>
			<th>ID</th>
			<?foreach($cols as $col):?>
				<th><?=$arResult['FIELDS'][$col]['EDIT_FORM_LABEL'];?></th>
			<?endforeach;?>
		</tr>
		<?foreach($arResult['ITEMS'] as $item):?>
			<tr>
				<td><?=$item['ID'];?></td>
				<?foreach($cols as $col):?>
					<td><?=$item[$col];?></td>
				<?endforeach;?>
			</tr>
		<?endforeach;?>
	</tbody>
</table>
<?if($arParams['DISPLAY_BOTTOM_PAGER']) {
	echo $arResult['NAV_STRING'];
}?>