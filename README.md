# EasySwoole Policy
用于验证、解析Policy结构和语法
## 安装
```bash
composer require EasySwoole/Policy
```
## 使用方法
```

use EasySwoole\Policy\PolicyNode;
use EasySwoole\Policy\Policy;

$policy = new Policy();

$policy->addPath('/user/add',PolicyNode::EFFECT_ALLOW);
$policy->addPath('/user/update',PolicyNode::EFFECT_ALLOW);
$policy->addPath('/user/delete',PolicyNode::EFFECT_DENY);
$policy->addPath('/user/*',PolicyNode::EFFECT_DENY);

var_dump($policy->check('user/asdasd'));
var_dump($policy->check('user/add'));
var_dump($policy->check('user/update'));
/*
 * 允许/api/*,但是唯独拒绝/api/order/charge,/api/order/info,/api/sys/*
 */
$policy->addPath('/api/*',PolicyNode::EFFECT_ALLOW);
$policy->addPath('/api/order/charge',PolicyNode::EFFECT_DENY);
$policy->addPath('/api/order/info',PolicyNode::EFFECT_DENY);
$policy->addPath('/api/sys/*',PolicyNode::EFFECT_DENY);
var_dump($policy->check('/api/whatever'));
var_dump($policy->check('/api/order/charge'));
var_dump($policy->check('/api/order/info'));

var_dump($policy->check('/api/sys/whatever'));


///*
// * *表示通配,根节点
// */
//$root = new PolicyNode('*');
//
//$userChild = $root->addChild('user');
//$userChild->addChild('add');
//$userChild->addChild('update');
//$userChild->addChild('*');
//
//$apiChild = $root->addChild('charge');
////$apiChild->addChild('*');
////$userChild->addChild('*');
//
//
//var_dump($root->search('/user/update'));
```