<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-03-15
 * Time: 23:50
 */

namespace EasySwoole\Policy;


use EasySwoole\Policy\Exception\Exception;
use EasySwoole\Spl\SplArray;

class PolicyContainer
{
    private $policyList;
    function __construct(array $policy = null)
    {
        $this->policyList = new SplArray();
        if($policy){
            if(isset($policy['allow'])){
                foreach ($policy['allow'] as $item){
                    if(is_string($item)){
                        $this->allow($item);
                    }else{
                        throw new Exception("allow policy error,string required");
                    }
                }
            }
            if(isset($policy['deny'])){
                foreach ($policy['deny'] as $item){
                    if(is_string($item)){
                        $this->deny($item);
                    }else{
                        throw new Exception("deny policy error,string required");
                    }
                }
            }
        }
    }

    function allow(string $path):PolicyContainer
    {
        $path = trim($path," \t\n\r \v/");
        $path = str_replace('/','.',$path);
        $this->policyList->set($path,true);
        return $this;
    }

    function deny(string $path):PolicyContainer
    {
        $path = trim($path," \t\n\r \v/");
        $path = str_replace('/','.',$path);
        $this->policyList->set($path,false);
        return $this;
    }

    function getPolicy():SplArray
    {
        return $this->policyList;
    }
}