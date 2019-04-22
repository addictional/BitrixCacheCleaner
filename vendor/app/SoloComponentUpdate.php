<?php
/**
 * Created by PhpStorm.
 * User: Ivan.Yakovlev
 * Date: 19.04.2019
 * Time: 9:48
 */

namespace LgCache;


class SoloComponentUpdate extends CatalogComponent
{
    public function __construct()
    {
        parent::__construct();
        $this->setParams();
    }
    protected function setParams()
    {
        dump($this->template);
    }
}