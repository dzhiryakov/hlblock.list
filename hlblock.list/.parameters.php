<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * @var array $arCurrentValues
 */

use \Bitrix\Main;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Highloadblock as HL;

Loc::loadMessages(__FILE__);

if(!Main\Loader::includeModule('highloadblock'))
{
	return false;
}

if(!function_exists('HLBlockListAddPagerSettings'))
{
	function HLBlockListAddPagerSettings(&$arComponentParameters, $pagerTitle, $bShowAllParam = false)
	{
		$arHiddenTemplates = array(
			'js' => true,
			'admin' => true,
		);

		if(!isset($arComponentParameters['GROUPS']))
		{
			$arComponentParameters['GROUPS'] = array();
		}

		$arComponentParameters["GROUPS"]["PAGER_SETTINGS"] = array(
			"NAME" => Loc::getMessage("HL_LIST_PAGENAV_PAGER_SETTINGS"),
		);

		$arTemplateInfo = \CComponentUtil::GetTemplatesList('bitrix:main.pagenavigation');
		if(empty($arTemplateInfo))
		{
			$arComponentParameters["PARAMETERS"]["PAGER_TEMPLATE"] = Array(
				"PARENT" => "PAGER_SETTINGS",
				"NAME" => Loc::getMessage("HL_LIST_PAGENAV_PAGER_TEMPLATE"),
				"TYPE" => "STRING",
				"DEFAULT" => "",
			);
		}
		else
		{
			sortByColumn($arTemplateInfo, array('TEMPLATE' => SORT_ASC, 'NAME' => SORT_ASC));
			$arTemplateList = array();
			$arSiteTemplateList = array(
				'.default' => Loc::getMessage('HL_LIST_PAGENAV_PAGER_TEMPLATE_SITE_DEFAULT')
			);
			$arTemplateID = array();
			foreach($arTemplateInfo as $code => &$template)
			{
				if('' != $template["TEMPLATE"] && '.default' != $template["TEMPLATE"])
				{
					$arTemplateID[] = $template["TEMPLATE"];
				}
				if(!isset($template['TITLE']))
				{
					$template['TITLE'] = $template['NAME'];
				}
			}
			unset($template);

			if(!empty($arTemplateID))
			{
				$rsSiteTemplates = \CSiteTemplate::GetList(
					array(),
					array("ID" => $arTemplateID),
					array()
				);
				while($arSitetemplate = $rsSiteTemplates->Fetch())
				{
					$arSiteTemplateList[$arSitetemplate['ID']] = $arSitetemplate['NAME'];
				}
			}

			foreach($arTemplateInfo as &$template)
			{
				if(isset($arHiddenTemplates[$template['NAME']]))
				{
					continue;
				}
				$strDescr = $template["TITLE"].' ('.('' != $template["TEMPLATE"] && '' != $arSiteTemplateList[$template["TEMPLATE"]] ? $arSiteTemplateList[$template["TEMPLATE"]] : Loc::getMessage("HL_LIST_PAGENAV_PAGER_TEMPLATE_SYSTEM")).')';
				$arTemplateList[$template['NAME']] = $strDescr;
			}
			unset($template);
			$arComponentParameters["PARAMETERS"]["PAGER_TEMPLATE"] = array(
				"PARENT" => "PAGER_SETTINGS",
				"NAME" => Loc::getMessage("HL_LIST_PAGENAV_PAGER_TEMPLATE_EXT"),
				"TYPE" => "LIST",
				"VALUES" => $arTemplateList,
				"DEFAULT" => ".default",
				"ADDITIONAL_VALUES" => "Y"
			);
		}

		$arComponentParameters["PARAMETERS"]["DISPLAY_TOP_PAGER"] = Array(
			"PARENT" => "PAGER_SETTINGS",
			"NAME" => Loc::getMessage("HL_LIST_PAGENAV_TOP_PAGER"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		);
		$arComponentParameters["PARAMETERS"]["DISPLAY_BOTTOM_PAGER"] = Array(
			"PARENT" => "PAGER_SETTINGS",
			"NAME" => Loc::getMessage("HL_LIST_PAGENAV_BOTTOM_PAGER"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		);
		$arComponentParameters["PARAMETERS"]["PAGER_SEF_MODE"] = Array(
			"PARENT" => "PAGER_SETTINGS",
			"NAME" => Loc::getMessage("HL_LIST_PAGENAV_SEF_MODE"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		);

		if($bShowAllParam)
		{
			$arComponentParameters["PARAMETERS"]["PAGER_SHOW_ALL"] = Array(
				"PARENT" => "PAGER_SETTINGS",
				"NAME" => Loc::getMessage("HL_LIST_PAGENAV_SHOW_ALL"),
				"TYPE" => "CHECKBOX",
				"DEFAULT" => "N"
			);
		}
	}
}

$hlBlocks = array();
$userProps = array();
$userSortFields = array('ID' => 'ID');

$hlBblockExists = (!empty($arCurrentValues['BLOCK_ID']) && (int)$arCurrentValues['BLOCK_ID'] > 0);

$hlBlocksIterator = HL\HighloadBlockTable::getList(array(
	'order' => array('ID' => 'ASC'),
	'select' => array('ID', 'NAME')
));
while($hlBlock = $hlBlocksIterator->fetch())
{
	$hlBlocks[$hlBlock['ID']] = '['.$hlBlock['ID'].'] '.$hlBlock['NAME'];
}

if($hlBblockExists)
{
	$arUf = $GLOBALS["USER_FIELD_MANAGER"]->GetUserFields('HLBLOCK_'.$arCurrentValues['BLOCK_ID'], 0, LANGUAGE_ID);
	$userProps = array();
	if(!empty($arUf))
	{
		foreach($arUf as $key => $val)
		{
			$userProps[$val["FIELD_NAME"]] = (strlen($val["EDIT_FORM_LABEL"]) > 0 ? $val["EDIT_FORM_LABEL"] : $val["FIELD_NAME"]);
			$userSortFields[$val["FIELD_NAME"]] = (strlen($val["EDIT_FORM_LABEL"]) > 0 ? $val["EDIT_FORM_LABEL"] : $val["FIELD_NAME"]);
		}
	}
}

$userSortFields['RAND'] = Loc::getMessage('HL_LIST_SORT_FIELD_RAND');

$arComponentParameters = array(
	"PARAMETERS" => array(
		"BLOCK_ID" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_BLOCK_ID"),
			"TYPE" => "LIST",
			"VALUES" => $hlBlocks,
			"REFRESH" => "Y"
		),
		"DETAIL_URL" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_DETAIL_URL"),
			"TYPE" => "STRING",
			"DEFAULT" => ""
		),
		"FILTER_NAME" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_FILTER_NAME"),
			"TYPE" => "STRING",
			"DEFAULT" => "arrHLRowsFilter"
		),
		"PAGE_COUNT" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_PAGE_COUNT"),
			"TYPE" => "STRING"
		),
		"SORT_FIELD" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_SORT_FIELD"),
			"TYPE" => "LIST",
			"ADDITIONAL_VALUES" => "Y",
			"SIZE" => (count($userSortFields) > 5 ? 8 : 3),
			"VALUES" => $userSortFields
		),
		"SORT_ORDER" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_SORT_ORDER"),
			"TYPE" => "LIST",
			"VALUES" => array(
				"ASC" => Loc::getMessage("HL_LIST_SORT_ORDER_ASC"),
				"DESC" => Loc::getMessage("HL_LIST_SORT_ORDER_DESC")
			)
		),
		"AJAX_MODE" => array(),
		"CACHE_TIME" => array("DEFAULT" => 86400),
		"CACHE_FILTER" => array(
			"PARENT" => "BASE",
			"NAME" => Loc::getMessage("HL_LIST_CACHE_FILTER"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N"
		),
		"USER_PROPERTY" => array(
			"PARENT" => "ADDITIONAL_SETTINGS",
			"NAME" => Loc::getMessage("HL_LIST_USER_PROPERTY"),
			"TYPE" => "LIST",
			"VALUES" => $userProps,
			"SIZE" => (count($userProps) > 5 ? 8 : 3),
			"MULTIPLE" => "Y"
		),
	)
);

HLBlockListAddPagerSettings(
	$arComponentParameters,
	Loc::getMessage("INNOVA_SR_LIST_PAGE_NAVIGATION_NAME"), //$pagerTitle
	true //$bShowAllParam
);