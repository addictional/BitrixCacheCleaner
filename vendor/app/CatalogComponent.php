<?php
/**
 * Created by PhpStorm.
 * User: Ivan.Yakovlev
 * Date: 15.04.2019
 * Time: 11:13
 */

namespace LgCache;



use mysql_xdevapi\Exception;

/**
 * Class CatalogComponent
 * @package LgCache
 */

class CatalogComponent
{
    private $arSectionPathCache = [];

    private $groups = ['2','2,3,4','1,2,3,4'];
    /**
     * @var string
     */
    protected $templatePage = null;
    /**
     * @var string
     */
    protected $template = null;
    /**
     * @var string
     */
    protected $templatePath = null;
    /**
     * @var string
     */
    protected $siteId;
    /**
     * @var string
     */
    protected $langId;
    /**
     * @var string
     */
    protected $arParams = null;
    /**
     * @var array
     */
    protected $catalogMd5 = null;

    /**
     * CatalogComponent constructor.
     */
    public function __construct()
    {
        $args = func_get_args();
        if(!empty($args))
        {
            $this->templatePage = $args[0];
            $this->template = $args[1];
        }
        $this->siteId = \Bitrix\Main\Context::getCurrent()->getSite();
        $this->langId = \Bitrix\Main\Context::getCurrent()->getLanguage();
        $this->siteTemplate = (defined("SITE_TEMPLATE_ID")? SITE_TEMPLATE_ID:"");
    }

    /**
     * @param null $templatePage
     * @param null $template
     */
    public function setTemplate($templatePage = null, $template = null)
    {
        $this->templatePage = $templatePage;
        $this->template = $template;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function getCatalogParams()
    {
        if($this->templatePage == null)
            throw new \Exception('catalog page undefined!');
        $components = \LgCache\Parser::phpParser($this->templatePage);
        foreach ($components as $component)
        {
            if($component[0] === "bitrix:catalog")
            {
                return  $component;
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getTemplateParams()
    {
        $catalogParams = $this->getCatalogParams();
        $component = new \CBitrixComponent();
        $component->InitComponent($catalogParams[0],$catalogParams[1]);
        $component->initComponentTemplate();
        $this->templatePath = $this->getTemplatePath($component);
    }

    /**
     * @param \CBitrixComponent $component
     * @return mixed
     */
    protected function getTemplatePath(\CBitrixComponent $component)
    {

        $dir[0] = $_SERVER['DOCUMENT_ROOT']."/local/templates/".
            $component->getTemplate()->__siteTemplate.
            "/components".$component->getRelativePath().DIRECTORY_SEPARATOR.
            $component->getTemplateName();
        $dir[1] = $_SERVER['DOCUMENT_ROOT']."/local/components".$component->getRelativePath()
            .DIRECTORY_SEPARATOR. $component->getTemplateName();
        $dir[2] = $_SERVER['DOCUMENT_ROOT']."/bitrix/templates/".
            $component->getTemplate()->__siteTemplate."/components".$component->getRelativePath().DIRECTORY_SEPARATOR.
            $component->getTemplateName();
        $dir[3] = $_SERVER['DOCUMENT_ROOT'].$component->__path.DIRECTORY_SEPARATOR.
            $component->getTemplateName();
        foreach ($dir as $path)
        {
            if(is_dir($path))
                return str_replace($_SERVER['DOCUMENT_ROOT']."/","",$path);
        }
    }

    /**
     * @throws \Exception
     */
    public function initSectionUpdate()
    {
        $path = $this->templatePath."/section_vertical.php";
        $catalogParams = $this->getCatalogParams();
        $arParams = Parser::phpParser($path);
        $this->sectionFilter($arParams);
        $arParams = $this->comparingArrays($catalogParams,$arParams);
        $this->arParams = $arParams;
        $this->catalogMd5 =  "/".substr(md5("/catalog/index.php"), 0, 3);
    }

    /**
     * @param $array
     */
    private function sectionFilter(array &$array)
    {
        foreach ($array as $index => $elem)
        {
            if($elem[0] != "bitrix:catalog.section.list")
            {
                unset($array[$index]);
            }
        }
    }

    /**
     * @param $first
     * @param $second
     * @return mixed
     */
    private function comparingArrays(array $first,array $second)
    {
        $hideSectName = (isset($first[2]["SECTIONS_HIDE_SECTION_NAME"])) ? $first[2]["SECTIONS_HIDE_SECTION_NAME"]: "N";
        foreach ($second as $index => &$elem)
        {
            foreach ($elem[2] as $key => &$parameter )
            {
                if($parameter === null && $key != "SECTION_ID" && $key!= "SECTION_CODE" )
                {
                    if($key == "IBLOCK_TYPE" || $key == "IBLOCK_ID"|| $key == "CACHE_TYPE"|| $key == "CACHE_GROUPS"
                        || $key == "CACHE_TIME" || $key == "ADD_SECTIONS_CHAIN")
                    {
                        $parameter = $first[2][$key];
                    }
                    elseif(isset($first[2]["SECTION_".$key]))
                    {
                        $parameter = $first[2]["SECTION_".$key];
                    }elseif(isset($first[2]["SECTIONS_".$key]))
                    {
                        $parameter = $first[2]["SECTIONS_".$key];
                    }else
                    {
                       if($key == "HIDE_SECTION_NAME")
                           $parameter = $hideSectName;
                       if($key == "SECTION_URL")
                           $parameter = $first[2]['SEF_FOLDER'].$first[2]['SEF_URL_TEMPLATES']['section'];
                    }
                }
                if(preg_match('/^\d*$/',$parameter))
                    if($parameter != "36000000" && $key != "COUNT_ELEMENTS")
                        $parameter = (int)$parameter;
                if($key == "ADD_SECTIONS_CHAIN")
                    $parameter = ($parameter == "Y")? true: false;
                if($key == "COUNT_ELEMENTS")
                    $parameter = ($parameter == "Y")? true : false;
                if($key == "SECTION_URL" && (empty($parameter) || $parameter == null))
                    $parameter = "";
            }
        }
        $this->arParams = $second;
        return $second;
    }

    /**
     * @param $arParams
     * @param bool $code
     * @param bool $id
     * @return array
     */
    private function getCachePaths(&$arParams , $code = false, $id = false)
    {
        global $USER;
        $cacheIds = [];
        foreach ($arParams as $index => $component)
        {
            $component[2]['SECTION_ID'] = ($id)? $id : 0 ;
            $component[2]['SECTION_CODE'] = ($code)? $code : "" ;
            $cacheID = $this->siteId."|".$this->langId.($this->siteTemplate != "" ? "|".$this->siteTemplate:"");
            $cacheID .="|".$component[0]."|".($component[1] == ".default"? "":$component[1])."|";
            foreach($component[2] as $k=>$v)
            {
                if(strncmp("~", $k, 1))
                    $cacheID .= ",".$k."=".serialize($v);
            }
            if(($offset = \CTimeZone::getOffset()) <> 0)
                $cacheID .= "|".$offset;
            if($component[2]['CACHE_GROUPS'] == "Y")
                foreach ($this->groups as $group)
                {
                    $newCacheId = $cacheID."|".serialize($group);
                    $cacheIds[] = $_SERVER['DOCUMENT_ROOT']."/bitrix/cache/".$this->siteId.$this->relativePath($component[0])
                        .$this->catalogMd5."/".$this->getSalt($newCacheId);
                }
            else
            {
//                $cacheID .= "|".serialize($USER->GetGroups());
                $cacheIds[] = $_SERVER['DOCUMENT_ROOT']."/bitrix/cache/".$this->siteId.$this->relativePath($component[0])
                    .$this->catalogMd5."/".$this->getSalt($cacheID);
            }

        }
        return $cacheIds;
    }

    /**
     * @param $cacheID
     * @return string
     */
    private function getSalt($cacheID)
    {
        $un = md5($cacheID);
        return substr($un, 0, 2)."/".$un.".php";
    }

    /**
     * @param $name
     * @return string
     */
    private function relativePath($name)
    {
        return "/".implode("/",explode(':',$name));
    }

    /**
     * @param bool $code
     * @param bool $id
     * @throws \Exception
     */
    public function clearCache($code = false,$id = false)
    {
        $paths = [];
        if(!$code && !$id)
            throw new \Exception("Lack of params!");
        if($this->arParams == null)
            throw new \Exception("Nothing for clear");
        $selected = "";
        if($code)
            $selected = $code;
        if($id)
            $selected = $id;
        if(is_array($selected))
            foreach ($selected as $c)
            {
                if($code)
                {
                    $pathers = $this->getCachePaths($this->arParams,$c);
                    foreach ($pathers as $p)
                    {
                        $paths[] = $p;
                    }
                }
                else
                {
                    $pathers = $this->getCachePaths($this->arParams,$c);
                    foreach ($pathers as $p)
                    {
                        $paths[] = $p;
                    }
                }
            }
        else
        {
            if($code)
                $paths = $this->getCachePaths($this->arParams,$selected);
            else
                $paths = $this->getCachePaths($this->arParams,false,$selected);
        }
        foreach ($paths as $path)
        {
            if(is_file($path))
                unlink($path);
        }
    }

//    private function getSectionCodePath($sectionId)
//    {
//        if (!isset($this->arSectionPathCache[$sectionId]))
//        {
//            self::$arSectionPathCache[$sectionId] = "";
//            $res = \CIBlockSection::GetNavChain(0, $sectionId, ["ID", "CODE"], true);
//            foreach ($res as $a)
//            {
//                self::$arSectionCodeCache[$a["ID"]] = rawurlencode($a["CODE"]);
//                self::$arSectionPathCache[$sectionId] .= rawurlencode($a["CODE"])."/";
//            }
//            unset($a, $res);
//            self::$arSectionPathCache[$sectionId] = rtrim(self::$arSectionPathCache[$sectionId], "/");
//        }
//        return self::$arSectionPathCache[$sectionId];
//    }
}