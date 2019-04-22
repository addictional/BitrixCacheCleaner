<?php
/**
 * Created by PhpStorm.
 * User: Ivan.Yakovlev
 * Date: 17.04.2019
 * Time: 13:47
 */

namespace LgCache;

/**
 * Class SectionTree
 * @package LgCache
 */
class SectionTree
{
    /**
     * @var null
     */
    public $id = null;
    /**
     * @var string null
     */
    public $code = null;
    /**
     * @var null
     */
    public $active = null;

    /**
     * @var null
     */
    private $depth = null;
    /**
     * @var null
     */
    private $parent = null;
    /**
     * @var null
     */
    private $childs = null;

    /**
     * SectionTree constructor.
     * @param array $array
     */

    public function __construct(array $array)
    {
        if(isset($array) && $array!= null)
        {
            $this->id = $array['ID'];
            $this->code =  $array['CODE'];
            $this->active =  $array['ACTIVE'];
            $this->depth =  $array['DEPTH_LEVEL'];
            $this->childs =  $array['CHILD'];
            $this->parent = $array['IBLOCK_SECTION_ID'];
        }
    }

    /**
     * @return array
     */
    public function getAllChilds()
    {
        $array = [];
        foreach ($this->childs as $child)
        {
            $childs = $this->getChilds($child);
            foreach ($childs as $c)
                $array[] = $c;
        }
        return $array;
    }

    /**
     * @param $childs
     * @return array
     */
    private function getChilds($childs)
    {
        $array = [];
        if(!empty($childs->childs)) {
            foreach ($childs->childs as $child)
            {
                $newArr = $this->getChilds($child);
                foreach ($newArr as $s)
                    $array[] = $s;
            }
        }
            $array[] = $childs;
        return $array;
    }

    /**
     * @param $id
     * @return bool
     */
    public function searchSectionById(int $id)
    {
        foreach ($this->childs as $child)
        {
            if($child->id == $id)
                return $child;
            elseif(!empty($child->childs))
            {
                $found = $child->searchSectionById($id);
                if($found)
                    return $found;
            }
        }
        return false;
    }

    /**
     * @return integer
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @return int|null
     */

    public function getParent()
    {
        return $this->parent;
    }


}