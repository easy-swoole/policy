<?php
/**
 *
 * Copyright  FaShop
 * License    http://www.fashop.cn
 * link       http://www.fashop.cn
 * Created by FaShop.
 * User: hanwenbo
 * Date: 2019-02-20
 * Time: 11:31
 *
 */

namespace EasySwoole\Policy;


use EasySwoole\Spl\SplArray;

class Policy
{
    const EFFECT_ALLOW = 'allow';
    const EFFECT_DENY = 'deny';
    const EFFECT_UNKNOWN = 'unknown';
    private $policy;

    function __construct()
    {
        $this->policy = new SplArray();
    }

    function addPolicyList(PolicyContainer $container):Policy
    {
        $array = $container->getPolicy()->getArrayCopy();
        $result = $array + $this->policy->getArrayCopy();
        $this->policy = new SplArray($result);
        return $this;
    }

    function clearPolicy():Policy
    {
        $this->policy = new SplArray();
        return $this;
    }

    function getPolicyArray(string $path = null)
    {
        if($path){
            $path = trim($path," \t\n\r \v/");
            $path = str_replace('/','.',$path);
            return $this->policy->get($path);
        }
        return $this->policy->getArrayCopy();
    }

    function verify(string $path,array $data = null)
    {
//        $path = trim($path," \t\n\r \v/");
//        $paths = explode('/',$path);
//        if(!$data){
//            $data = $this->policy->getArrayCopy();
//        }
//        $spl = new SplArray($data);
//        while ($path = array_shift($paths)){
//            $info = $spl->get($path);
//            if($info === true){
//                return self::EFFECT_ALLOW;
//            }else if($info === false){
//                return self::EFFECT_DENY;
//            }else{
//                if(is_array($info)){
//
//                }else{
//                    if($path == "*"){
//                        return self::EFFECT_UNKNOWN;
//                    }
//                }
//            }
//        }


        $path = trim($path," \t\n\r \v/");
        $path = str_replace('/','.',$path);
        $info = $this->policy->get($path);
        if($info === true){
            return self::EFFECT_ALLOW;
        }else if($info === false){
            return self::EFFECT_DENY;
        }else if($info === null){
            $paths = explode('.',$path);
            $item = array_pop($paths);
            //尝试搜索这个目录是否设置了*权限
            if($item != '*'){
                $paths[] = '*';
                $path = implode('.',$paths);
                $info = $this->policy->get($path);
                if($info === true){
                    return self::EFFECT_ALLOW;
                }else if($info === false){
                    return self::EFFECT_DENY;
                }
            }
            return self::EFFECT_UNKNOWN;
        }else{
            return self::EFFECT_UNKNOWN;
        }
    }
}

