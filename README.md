# EasySwoole Policy
用于验证、解析Policy结构和语法
## 安装
```bash
composer require EasySwoole/Policy
```
## 使用方法
```php
$container = new \EasySwoole\Policy\PolicyContainer();
$container
    //可以开放某个目录的全部权限，
    ->allow('/goods/*')
    //但可以单独禁止部分行为
    ->deny('/goods/del')
    ->deny('/goods/update')
    ->allow('/public/*')
    ->allow('/api/test2')
    ->allow('/api/test3/*')
    ->deny('/api/test4')
;
$array = $container->getPolicy();
$policy = new \EasySwoole\Policy\Policy();
$policy->addPolicyList($container);

//*
// * 当校验某个具体规则时候
// */
var_dump($policy->verify('/goods/del'));
/*
 * 当目录下存在allow*或者是deny*的时候，那么未知行为都是遵照最高的allow或者是deny
 */
var_dump($policy->verify('/goods/test'));
/*
 * 注意，这里/goods会当成一个action处理，因此某个目录的权限是unknown
// */
var_dump($policy->verify('/goods'));
//
////因为goods目录有设置明确的*权限，因此/goods/*得到的结果是allow
var_dump($policy->verify('/goods/*'));
////而api目录并没有设置明确的*权限，因此得到的结果是未知
var_dump($policy->verify('/api/*'));
//
var_dump($policy->verify('/api/test2'));
var_dump($policy->verify('/api/test3/sub'));
var_dump($policy->verify('/api/test4'));

var_dump($policy->verify('/public/test'));
```


策略json格式如下：
```json

```
