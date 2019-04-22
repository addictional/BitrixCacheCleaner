<?php
/**
 * Created by PhpStorm.
 * User: Ivan.Yakovlev
 * Date: 16.04.2019
 * Time: 15:03
 */

namespace LgCache;



/**
 * Class Sections
 * @package LgCache
 */

class Sections
{
    /**
     * @var array|null
     */

    private $allSections = null;

    /**
     * @var array|null
     */

    private $sectionSearchArray = null;

    /**
     * Sections constructor.
     */

    public function __construct()
    {
        $this->allSections = $this->getActiveSections();
        $this->allSections = $this->makeSectionTree();
        $this->setSearchEngine();
    }

    /**
     * @return void;
     */

    public function update()
    {
        $restsSections = $this->getRestsSections();
        $result = $this->setSectionsForChange($restsSections);
        $this->sectUpdate($result);
    }

    /**
     * @param array $result
     * @throws \Exception
     */

    private function sectUpdate(array $result)
    {
        global $DB;
        $catalog = new CatalogComponent('catalog/index.php');
        $catalog->getTemplateParams();
        $catalog->initSectionUpdate();
        $activate = [];
        $deactivate = [];
        foreach ($result['A'] as $elem)
        {
            $activate[] = $elem->id;
            if($elem->getParent() != null)
                $activateCache[] = $this->getById($elem->getParent())->code;
            $activateCache[] = $elem->code;
            $childs = $elem->getAllChilds();
            foreach ($childs as $child)
            {
                if($child->active == "Y")
                    $globalActive[$child->id] = $child->id;
            }
        }
        $sql = "UPDATE `b_iblock_section` SET ACTIVE = 'Y',GLOBAL_ACTIVE = 'Y'  WHERE ID IN(".implode(',',$activate).")";
        $sql2 = "UPDATE `b_iblock_section` SET GLOBAL_ACTIVE = 'Y'  WHERE ID IN(".implode(',',$globalActive).")";
        if(!empty($activate))
        {
            $success = $DB->Query($sql,true);
            $success = $DB->Query($sql2,true);
            dump('test');
        }
        if(!empty($activateCache))
            $catalog->clearCache($activateCache);
        foreach ($result['D'] as $elem)
        {
            $deactivate[] = $elem->id;
            if($elem->getParent() != null)
                $deactivateCache[] = $this->getById($elem->getParent())->code;
            $deactivateCache[] = $elem->code;
            $childs = $elem->getAllChilds();
            foreach ($childs as $child)
            {
                if($child->active == "Y")
                    $globalInactive[$child->id] = $child->id;
            }
        }

        $sql = "UPDATE `b_iblock_section` SET ACTIVE = 'N',GLOBAL_ACTIVE = 'N' WHERE ID IN(".implode(',',$deactivate).")";
        $sql2 = "UPDATE `b_iblock_section` SET GLOBAL_ACTIVE = 'N'  WHERE ID IN(".implode(',',$globalInactive).")";
        if(!empty($deactivate))
        {
            $success = $DB->Query($sql,true);
            $success = $DB->Query($sql2,true);
        }
        if(!empty($deactivateCache))
            $catalog->clearCache($deactivateCache);
        if(!empty($result['A']) || !empty($result['D']))
        {
            $GLOBALS['CACHE_MANAGER']->CleanDir('menu');
            \CBitrixComponent::clearComponentCache('bitrix:menu');
        }
    }

    /**
     * @return array
     */

    private function getActiveSections()
    {
        global $DB;
        $sql = 'SELECT ID, IBLOCK_SECTION_ID, CODE, DEPTH_LEVEL, ACTIVE  FROM `b_iblock_section` '.
        'WHERE IBLOCK_ID = 9';
        $return = [];
        $result = $DB->Query($sql);
        while($row = $result->Fetch())
        {
            $return[$row['ID']] = $row;
        }
        return $return;
    }

    /**
     * @return array
     */

    private function getRestsSections()
    {
        global $DB;
        $rests = Parser::jsonParse();
        $newArr = [];
        foreach ($rests as $key => &$elem)
            $newArr[] = "'".$key."'";
        $rests = $newArr;
        unset($newArr);
        $sql = "SELECT prop.ID, element.XML_ID, prop.VALUE AS PARENT, sect.IBLOCK_SECTION_ID AS SECT FROM `b_iblock_element` AS element ".
            PHP_EOL."INNER JOIN `b_iblock_element_property` AS prop ON prop.IBLOCK_ELEMENT_ID = element.ID AND IBLOCK_PROPERTY_ID = 161 ".
            PHP_EOL."LEFT JOIN `b_iblock_section_element` AS sect ON sect.IBLOCK_ELEMENT_ID = prop.VALUE ".
            PHP_EOL."WHERE element.IBLOCK_ID = 10 AND element.XML_ID IN(".implode(",",$rests).") ".
            PHP_EOL."GROUP BY SECT";
        $res = $DB->Query($sql);
        $return = [];
        while ($row = $res->Fetch())
        {
            $return[$row['SECT']] = $row;
        }
        return $return;
    }

    /**
     * @param array $rests
     * @return array
     */

    private function setSectionsForChange(array $rests)
    {

        $shouldBeActive = [];
        $activate = [];
        $deactivate = [];
        foreach ($this->sectionSearchArray as $section)
        {
            if(isset($rests[$section->id]))
                {
                    $r =  $this->getParents($section);
                    foreach ($r as $a)
                    {
                        $shouldBeActive[$a->id] = $a;
                    }
                    $shouldBeActive[$section->id] = $section;
                }
        }
        foreach ($shouldBeActive as $section)
            if($this->getById($section->id)->active == "N")
                $activate[$section->id] = $section;
        foreach ($this->sectionSearchArray as $section)
            if($section->active == "Y" && !isset($shouldBeActive[$section->id]))
                $deactivate[] = $section;
        return ['A' => $activate,'D'=>$deactivate];
    }

    /**
     * @param bool $full
     * @param null $section
     * @return array
     */

    private function makeSectionTree($full = false,$section = null)
    {
        $return = [];
        if($full)
        {
            $newSections = [];
            foreach ($this->allSections as $key => $section)
            {
                if($section['DEPTH_LEVEL']==1)
                {
                    $section['CHILD'] = $this->makeSectionTree(false,$section);
                    $newSections[$key] = new SectionTree($section);
                }
            }
            return $newSections;
        }
        else
        {
            foreach ($this->allSections as $key => $child)
            {
                if($child['IBLOCK_SECTION_ID'] == $section['ID']
                    && $child['DEPTH_LEVEL'] == $section['DEPTH_LEVEL']+1)
                {
                    $child['CHILD'] = $this->makeSectionTree(false,$child);
                    $return[] = new SectionTree($child);
                }

            }
            return $return;
        }
    }

    //работает но можно сделать в разы проще=)
//    public function getById(int $ID)
//    {
//        foreach ($this->allSections as $section)
//        {
//            if($section->id == $ID)
//            {
//                return $section;
//            }
//            else
//            {
//                $found =  $section->searchSectionById($ID);
//                if($found)
//                    return $found;
//            }
//        }
//        return false;
//    }

    /**
     * @param SectionTree $section
     * @return array
     */

    private function getParents(SectionTree $section)
    {
        $parent = [];
        $current = $section;
        for ($i = 1; $i < $section->getDepth();$i++)
        {
            if($current->getParent() == null)
            {
                $parent[] = $current;
                break;
            }else
            {
                $current = $this->getById($current->getParent());
                $parent[] = $current;
            }
        }
        return $parent;
    }

    /**
     *
     */
    private function setSearchEngine()
    {
        foreach ($this->allSections as $section)
        {
            $this->sectionSearchArray[$section->id] = $section;
            $childs = $section->getAllChilds();
            foreach ($childs as $child)
            {
                $this->sectionSearchArray[$child->id] = $child;
            }
        }
        ksort($this->sectionSearchArray);
    }

    /**
     * @param $id
     * @return SectionTree
     */

    public function getById($id)
    {
        return $this->sectionSearchArray[$id];
    }

}