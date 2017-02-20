<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use \Bitrix\Main;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Highloadblock as HL;
use \Bitrix\Main\Entity;

Loc::loadMessages(__FILE__);

class HLListComponent extends CBitrixComponent
{
	protected $queryParams = array();
	protected $preFilter = array();
	protected $navigation = false;
	protected $nav = null;

	protected function checkModules()
	{
		$reqModules = $this->getModules();
		if(!is_array($reqModules) || empty($reqModules))
		{
			$reqModules = array('highloadblock');
		}
		foreach($reqModules as $module)
		{
			if(!Main\Loader::includeModule($module))
			{
				throw new Exception(Loc::getMessage('HL_LIST_MODULE_NOT_FOUND', array('#MODULE_ID#' => $module)));
			}
		}
	}

	protected function getModules()
	{
		return array(
			'highloadblock'
		);
	}

	protected function initPageNavigation()
	{
		$nav = new \Bitrix\Main\UI\PageNavigation("nav-rows");
		$nav->allowAllRecords($this->arParams["PAGER_SHOW_ALL"] == "Y")
			->setPageSize($this->arParams["PAGE_COUNT"])
			->initFromUri();

		if($this->arParams['DISPLAY_TOP_PAGER'] || $this->arParams['DISPLAY_BOTTOM_PAGER'])
		{
			$this->navigation = array(
				"page_size" => $nav->getPageSize(),
				"page_number" => $nav->getCurrentPage(),
				"page_count" => $this->arParams["PAGE_COUNT"],
				"showAll" => $this->arParams["PAGER_SHOW_ALL"],
				"allRecords" => ($nav->allRecordsShown() ? "Y" : "N")
			);
		}
		else
		{
			$this->navigation = false;
		}
		$this->nav = $nav;
	}

	protected function extractDataFromCache()
	{
		if($this->arParams['CACHE_TYPE'] == 'N')
		{
			return false;
		}

		$additionalCacheDependencies = array($this->navigation);

		return !($this->startResultCache(
			false,
			$additionalCacheDependencies
		));
	}

	protected function putDataToCache()
	{
		$this->endResultCache();
	}

	protected function abortDataCache()
	{
		$this->abortResultCache();
	}

	public function onPrepareComponentParams($params)
	{
		$params["BLOCK_ID"] = intval($params["BLOCK_ID"]);
		if(!isset($params["CACHE_TIME"]))
		{
			$params["CACHE_TIME"] = 86400;
		}
		$params["DETAIL_URL"] = trim($params["DETAIL_URL"]);
		$params["CACHE_FILTER"] = ($params["CACHE_FILTER"] == "Y" ? "Y" : "N");
		$params["PAGE_COUNT"] = intval($params["PAGE_COUNT"]);

		$arAllowedSortOrders = array("ASC", "DESC");
		if(!in_array($params["SORT_ORDER"], $arAllowedSortOrders))
			$params["SORT_ORDER"] = "ASC";

		if($params["CACHE_TYPE"] == "N"
			|| ($params["CACHE_TYPE"] == "A" && Main\Config\Option::get("main", "component_cache_on", "Y") == "N")
		)
		{
			$params["CACHE_TIME"] = 0;
		}

		if(empty($params["FILTER_NAME"])
			|| !preg_match("/^[A-Za-z_][A-Za-z01-9_]*$/", $params["FILTER_NAME"])
		)
		{
			$this->preFilter = array();
		}
		else
		{
			global ${$params["FILTER_NAME"]};
			$this->preFilter = ${$params["FILTER_NAME"]};
			if(!is_array($this->preFilter))
			{
				$this->preFilter = array();
			}
		}

		if(!empty($this->preFilter) && $params["CACHE_FILTER"] === "N")
		{
			$params["CACHE_TIME"] = 0;
		}

		if(empty($params["USER_PROPERTY"]) || !is_array($params["USER_PROPERTY"]))
		{
			$params["USER_PROPERTY"] = array();
		}
		else
		{
			$params["USER_PROPERTY"] = array_unique(array_filter($params["USER_PROPERTY"]));
		}

		return $params;
	}

	public function initResult()
	{
		$this->arResult = array(
			"BLOCK_ID" => null,
			"BLOCK_NAME" => null,
			"ITEMS" => array(),
			"FIELDS" => array()
		);

		if($this->nav instanceof \Bitrix\Main\UI\PageNavigation)
		{
			$this->arResult['NAV_OBJECT'] = $this->nav;
		}
	}

	public function prepareQuery()
	{
		$this->queryParams["order"] = array(
			$this->arParams["SORT_FIELD"] => $this->arParams["SORT_ORDER"]
		);

		$this->queryParams["filter"] = $this->preFilter;
		$this->queryParams["select"] = array_merge(array('ID'), $this->arParams['USER_PROPERTY']);
		$this->queryParams["runtime"] = array(
			new \Bitrix\Main\Entity\ExpressionField("RAND", "RAND()")
		);

		if($this->arParams["PAGE_COUNT"] > 0)
		{
			$this->queryParams["limit"] = $this->arParams["PAGE_COUNT"];
		}
	}

	public function makeQuery()
	{
		if(!intval($this->arParams["BLOCK_ID"]))
		{
			throw new \Exception(Loc::getMessage('HL_LIST_HLBLOCK_ID_NOT_SET'));
		}

		$hlblockId = $this->arParams['BLOCK_ID'];
		$hlBlock = HL\HighloadBlockTable::getById($hlblockId)->fetch();

		if(empty($hlBlock))
		{
			throw new \Exception(Loc::getMessage('HL_LIST_HLBLOCK_NOT_FOUND'));
		}

		$this->arResult['BLOCK_ID'] = $hlBlock['ID'];
		$this->arResult['BLOCK_NAME'] = $hlBlock['NAME'];

		$entity = HL\HighloadBlockTable::compileEntity($hlBlock);
		$dataClass = $entity->getDataClass();

		$fields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields('HLBLOCK_'.$hlBlock['ID'], 0, LANGUAGE_ID);
		foreach($fields as $key => $field)
		{
			if(!in_array($key, $this->arParams['USER_PROPERTY']))
			{
				unset($fields[$key]);
			}
		}

		$this->arResult['FIELDS'] = $fields;

		if(!isset($fields[$this->arParams["SORT_FIELD"]]))
		{
			$this->arParams["SORT_FIELD"] = "ID";
		}

		$rows = $dataClass::getList(array(
			'order' 		=> $this->queryParams["order"],
			'filter' 		=> $this->queryParams["filter"],
			'select' 		=> $this->queryParams["select"],
			'runtime' 		=> $this->queryParams["runtime"],
			'count_total'	=> true,
			'offset' 		=> $this->arResult["NAV_OBJECT"]->getOffset(),
			'limit' 		=> $this->arResult["NAV_OBJECT"]->getLimit(),
		));

		$this->arResult["NAV_OBJECT"]->setRecordCount($rows->getCount());
		$this->arResult["NAV_STRING"] = $this->getNavString($this->arResult["NAV_OBJECT"]);

		while($row = $rows->fetch())
		{
			foreach($row as $field => $value)
			{
				if($field == 'ID')
				{
					continue;
				}

				$row['~'.$field] = $value;
				$arUserField = $fields[$field];
				$html = call_user_func_array(
					array($arUserField["USER_TYPE"]["CLASS_NAME"], "getadminlistviewhtml"),
					array(
						$arUserField,
						array(
							"NAME" => "FIELDS[".$row['ID']."][".$arUserField["FIELD_NAME"]."]",
							"VALUE" => htmlspecialcharsbx($value)
						)
					)
				);

				if($html == '')
				{
					$html = '&nbsp;';
				}
				$row[$field] = $html;
			}

			$this->arResult['ITEMS'][] = $row;
		}
	}

	public function makeUrl()
	{
		if(strlen($this->arParams['DETAIL_URL']) > 0 && count($this->arResult['ITEMS']) > 0)
		{
			foreach($this->arResult['ITEMS'] as $key => &$item)
			{
				$item['~DETAIL_PAGE_URL'] = str_replace(
					array("#ID#", "#BLOCK_ID#"),
					array($item["ID"], $this->arResult["BLOCK_ID"]),
					$this->arParams['DETAIL_URL']
				);
				$item['DETAIL_PAGE_URL'] = htmlspecialcharsbx($item['~DETAIL_PAGE_URL']);
			}
		}
	}

	public function getNavString($nav)
	{
		if(!($nav instanceof \Bitrix\Main\UI\PageNavigation))
		{
			return false;
		}

		global $APPLICATION;

		ob_start();
		$APPLICATION->IncludeComponent(
			"bitrix:main.pagenavigation",
			$this->arParams["PAGER_TEMPLATE"],
			array(
				"NAV_OBJECT" => $nav,
				"SEF_MODE" => $this->arParams["PAGER_SEF_MODE"],
			),
			$this,
			array(
				"HIDE_ICONS" => "Y",
			)
		);
		return ob_get_clean();
	}

	public function executeComponent()
	{
		try
		{
			$this->checkModules();
			$this->initPageNavigation();
			if (!$this->extractDataFromCache())
			{
				$this->initResult();
				$this->prepareQuery();
				$this->makeQuery();
				$this->makeUrl();
				$this->includeComponentTemplate();
				$this->putDataToCache();
			}
		}
		catch(Exception $e)
		{
			$this->abortDataCache();
			ShowError($e->getMessage());
		}
		return true;
	}
}
?>