<?php
/**
 * Created by PhpStorm.
 * User: Ivan.Yakovlev
 * Date: 12.04.2019
 * Time: 14:22
 */

namespace LgCache;

use PhpParser\Error;
use PhpParser\Node\Expr\Isset_;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

/**
 * Class Parser
 * @package LgCache
 */
class Parser
{

    const RESTS = "/upload/rests/last_imported";

    /**
     * @param $pagePath
     * @return array
     */

    public static function phpParser(string $pagePath)
    {
        $pagePath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$pagePath;
        $file = fopen($pagePath,"r") or die('Unable to open file!');
        $info = fread($file,filesize($pagePath));
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        try {
            $ast = $parser->parse($info);
        } catch (Error $e)
        {
            echo "Parse error: {$e->getMessage()}\n";
        }
        $return = [];
        foreach ($ast as $ass)
        {
            $data = self::readObj($ass);
            if($data && !empty($data))
            {
                foreach ($data as $d)
                {
                    $return[] = $d;
                }
            }
        }
        fclose($file);
        return $return;
    }

    /**
     * @param $array
     * @return array
     */

    public static function getArr($array)
    {
        $return = [];
        foreach ($array as $index => $elem)
        {
            $key = (isset($elem->key))? $elem->key->value : $index;
            $flag = isset($elem->value->items);
            if($flag)
                $return[$key] = self::getArr($elem->value->items);
            else
                $return[$key] = $elem->value->value;
        }
        return $return;
    }

    /**
     * @param $obj
     * @return array|bool
     */

    public static function readObj($obj)
    {
        $return = [];
        if($obj instanceof \PhpParser\Node\Stmt\If_)
        {
            if($obj->stmts != null || !empty($obj->stmts))
            {
                foreach ($obj->stmts as $statement)
                {
                    $test = self::readObj($statement);
                    if($test != null && !empty($test) && $test)
                    {
                        foreach ($test as $t){
                            $return[]= $t;
                        }
                    }
                }
            }
            if($obj->elseifs != null || !empty($obj->elseifs))
            {
                if($obj->elseifs->stmts != null || !empty($obj->elseifs->stmts))
                {
                    foreach ($obj->elseifs->stmts as $statement)
                    {
                        $test = self::readObj($statement);
                        if($test != null && !empty($test) && $test)
                        {
                            foreach ($test as $t){
                                $return[]= $t;
                            }
                        }
                    }
                }
            }
            if($obj->else != null || !empty($obj->else))
            {
                if($obj->else->stmts != null || !empty($obj->else->stmts))
                {
                    foreach ($obj->else->stmts as $statement)
                    {
                        $test = self::readObj($statement);
                        if($test != null && !empty($test) && $test)
                        {
                            foreach ($test as $t){
                                $return[]= $t;
                            }
                        }
                    }
                }
            }
        }
        elseif ($obj instanceof \PhpParser\Node\Stmt\Expression)
        {
            if($obj->expr instanceof \PhpParser\Node\Expr\MethodCall)
            {
                $func = $obj->expr;
                if($func->var->name != "APPLICATION")
                    return false;
                if($func->name->name != "IncludeComponent")
                    return false;
                $return[] = self::getArr($func->args);
            }
        }else
            return false;
        return (!empty($return))? $return : false;
    }

    /**
     * @return bool|array
     */

    public static function jsonParse()
    {
        $path = $_SERVER['DOCUMENT_ROOT'].self::RESTS;
        $methods = new self();
        if(is_dir($path))
        {
            $dir = new \RecursiveDirectoryIterator($path,\RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($dir);
            foreach ($files as $file)
            {
                if(!isset($currentFile))
                    $currentFile = [
                        'date' => $methods->dateFormater($file->getFilename()),
                        'file' => $file
                    ];
                if($currentFile['date']->lessThan($methods->dateFormater($file->getFilename())))
                    $currentFile = [
                        'date' => $methods->dateFormater($file->getFilename()),
                        'file' => $file
                    ];
            }
            $zip = new \ZipArchive();
            if($zip->open($currentFile['file']->getRealPath()))
                return json_decode($zip->getFromName('rests.json'));
        }
        return false;

    }

    /**
     * @param $name
     * @return \Carbon\Carbon
     */

    private function dateFormater($name)
    {
        $date = explode("_",explode(".",$name)[0])[1];
        $year = substr($date,0,4);
        $month = substr($date,4,2);
        $day = substr($date,6,2);
        $hour = substr($date,8,2);
        $minute = substr($date,10,2);
        $sec = substr($date,12,2);

        $date = \Carbon\Carbon::create($year,$month,$day,
            $hour,$minute,$sec,new \DateTimeZone( 'Europe/Moscow'));
        return $date;
    }
}