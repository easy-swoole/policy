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

use EasySwoole\Policy\Exception\Exception as PolicyException;


class Policy
{
    protected PolicyNode $root;

    public function __construct()
    {
        /**
         * 表示通配,根节点
         */
        $this->root = new PolicyNode("*");
    }

    /**
     * 添加路径并设置权限
     * @param string $path
     */
    public function addPath(string $path, AccessStatus $allow = AccessStatus::ALLOW): void
    {
        $path = trim($path, '/');
        if ($path === '') {
            throw new PolicyException('Policy path cannot be empty.');
        }

        $list = explode('/', $path);
        if (in_array('', $list, true)) {
            throw new PolicyException('Policy path cannot contain empty segments.');
        }

        $temp = $this->root;
        foreach ($list as $segment) {
            $temp = $temp->addChild($segment);//递归设置节点
        }
        $temp->setAllow($allow);
    }

    /**
     * 检测权限
     * @param string $path
     */
    public function check(string $path): AccessStatus
    {
        return $this->root->search($path)?->getEffect() ?? AccessStatus::UNKNOWN;
    }

    /**
     * 所有节点
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->root->toArray();
    }
}

