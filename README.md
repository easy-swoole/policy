# EasySwoole Policy

一个轻量的层级路径授权库。它将资源或动作组织成策略树，并通过精确路径和通配路径返回授权效果。

## 环境要求

- PHP 8.1 或更高版本
- JSON 扩展

## 安装

```bash
composer require easyswoole/policy
```

## 授权效果

权限状态由 `AccessStatus` 枚举定义。

| 枚举成员 | 字符串值 | 含义 |
| --- | --- | --- |
| `AccessStatus::ALLOW` | `allow` | 允许 |
| `AccessStatus::DENY` | `deny` | 拒绝 |
| `AccessStatus::UNKNOWN` | `unknown` | 没有匹配到明确规则 |

`addPath()` 和 `setAllow()` 接受 `AccessStatus` 枚举成员。`check()` 和 `getEffect()` 返回枚举成员，可用 `=== AccessStatus::ALLOW` 判断是否允许访问。导出和恢复策略数组时，`allow` 字段使用字符串值。

## 快速开始

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use EasySwoole\Policy\AccessStatus;
use EasySwoole\Policy\Policy;

$policy = new Policy();

$policy->addPath('/user/add', AccessStatus::ALLOW);
$policy->addPath('/user/update', AccessStatus::ALLOW);
$policy->addPath('/user/delete', AccessStatus::DENY);
$policy->addPath('/user/*', AccessStatus::DENY);

var_dump($policy->check('/user/add'));          // AccessStatus::ALLOW
var_dump($policy->check('/user/delete'));       // AccessStatus::DENY
var_dump($policy->check('/user/other'));        // AccessStatus::DENY
var_dump($policy->check('/user/other/detail')); // AccessStatus::DENY
var_dump($policy->check('/unknown'));           // AccessStatus::UNKNOWN
```

`addPath()` 的授权效果默认为 `allow`，因此下面两种写法等价：

```php
$policy->addPath('/article/read');
$policy->addPath('/article/read', AccessStatus::ALLOW);
```

## 路径匹配规则

### 精确规则优先

精确规则优先于同级通配规则；如果精确分支没有匹配到明确授权，则回退到通配规则。

```php
$policy = new Policy();
$policy->addPath('/api/*', AccessStatus::ALLOW);
$policy->addPath('/api/order/charge', AccessStatus::DENY);
$policy->addPath('/api/order/info', AccessStatus::DENY);

var_dump($policy->check('/api/product/list')); // AccessStatus::ALLOW
var_dump($policy->check('/api/order/other'));  // AccessStatus::ALLOW，回退到 /api/*
var_dump($policy->check('/api/order/charge')); // AccessStatus::DENY，精确规则优先
```

### 通配符

- 位于路径中间的 `*` 匹配一个路径段。
- 位于路径末尾的 `*` 匹配该节点下至少一个路径段，并覆盖其后任意层级。
- 通配节点可以同时设置自身授权和更具体的子规则；子规则未命中时仍会回退到通配节点自身的授权。

```php
$policy = new Policy();
$policy->addPath('/api/*', AccessStatus::ALLOW);
$policy->addPath('/api/*/private', AccessStatus::DENY);

var_dump($policy->check('/api/shop'));          // AccessStatus::ALLOW
var_dump($policy->check('/api/shop/products')); // AccessStatus::ALLOW
var_dump($policy->check('/api/shop/private'));  // AccessStatus::DENY
```

中间通配符只消费一个路径段：

```php
$policy = new Policy();
$policy->addPath('/a/*/c', AccessStatus::ALLOW);

var_dump($policy->check('/a/b/c'));   // AccessStatus::ALLOW
var_dump($policy->check('/a/b/d/c')); // AccessStatus::UNKNOWN
```

### 路径格式

- 开头和结尾的 `/` 会被忽略，`user/add` 与 `/user/add/` 等价。
- 路径区分大小写。
- 添加空路径或包含连续 `/` 的路径会抛出 `EasySwoole\Policy\Exception\Exception`。
- 检查包含连续 `/` 的路径不会抛出异常，返回 `AccessStatus::UNKNOWN`。

## 直接构建策略树

除了使用 `Policy`，也可以直接操作 `PolicyNode`：

```php
use EasySwoole\Policy\AccessStatus;
use EasySwoole\Policy\PolicyNode;

$root = new PolicyNode('*');
$user = $root->addChild('user');
$user->addChild('read')->setAllow(AccessStatus::ALLOW);
$user->addChild('delete')->setAllow(AccessStatus::DENY);
$user->addChild('*')->setAllow(AccessStatus::DENY);

$node = $root->search('/user/read');
$effect = $node?->getEffect() ?? AccessStatus::UNKNOWN;

var_dump($effect); // AccessStatus::ALLOW
```

节点名称在构造后不可修改。`getEffect()` 返回节点的 `AccessStatus`。

## 导出与恢复

策略树可以导出为数组，也可以从导出的数组安全恢复：

```php
$data = $root->toArray();
$restored = new PolicyNode($data);

$node = $restored->search('/user/delete');
var_dump($node?->getEffect()); // AccessStatus::DENY
```

恢复时会递归创建子节点，并校验节点名称、子节点结构和授权效果。也可以更新现有节点的授权或子节点：

```php
$restored->restore([
    'allow' => AccessStatus::DENY->value,
]);
```

## 异常处理

无效路径、节点名称、子节点结构或授权效果会抛出：

```php
EasySwoole\Policy\Exception\Exception
```

该异常继承自 `InvalidArgumentException`：

```php
use EasySwoole\Policy\Exception\Exception as PolicyException;

try {
    $policy->addPath('/api//order');
} catch (PolicyException $exception) {
    echo $exception->getMessage(); // Policy path cannot contain empty segments.
}
```

## 方法执行结果示例

### `Policy::__construct()`

创建一棵空策略树。此时没有任何明确授权规则：

```php
$policy = new Policy();

var_dump($policy->check('/article/read')); // AccessStatus::UNKNOWN
var_dump($policy->toArray());
```

输出结构：

```php
array(3) {
  ["name"]=> string(1) "*"
  ["leaves"]=> array(0) {}
  ["allow"]=> string(7) "unknown"
}
```

### `Policy::addPath()`

该方法没有返回值；执行结果是创建或更新规则：

```php
$result = $policy->addPath('/article/read', AccessStatus::ALLOW);

var_dump($result);                         // NULL
var_dump($policy->check('/article/read')); // AccessStatus::ALLOW

$policy->addPath('/article/read', AccessStatus::DENY);
var_dump($policy->check('/article/read')); // AccessStatus::DENY，已有规则被更新
```

### `Policy::check()`

返回 `AccessStatus::ALLOW`、`AccessStatus::DENY` 或 `AccessStatus::UNKNOWN`：

```php
$policy = new Policy();
$policy->addPath('/article/read', AccessStatus::ALLOW);
$policy->addPath('/article/delete', AccessStatus::DENY);

var_dump($policy->check('/article/read'));   // AccessStatus::ALLOW
var_dump($policy->check('/article/delete')); // AccessStatus::DENY
var_dump($policy->check('/article/update')); // AccessStatus::UNKNOWN
```

### `Policy::toArray()`

返回完整策略树数组：

```php
$policy = new Policy();
$policy->addPath('/article/read', AccessStatus::ALLOW);

print_r($policy->toArray());
```

输出：

```text
Array
(
    [name] => *
    [leaves] => Array
        (
            [article] => Array
                (
                    [name] => article
                    [leaves] => Array
                        (
                            [read] => Array
                                (
                                    [name] => read
                                    [leaves] => Array
                                        (
                                        )
                                    [allow] => allow
                                )
                        )
                    [allow] => unknown
                )
        )
    [allow] => unknown
)
```

### `PolicyNode::__construct()`

可以使用节点名称或已导出的数组创建节点：

```php
$node = new PolicyNode('article');
var_dump($node->getName());   // article
var_dump($node->getEffect()); // AccessStatus::UNKNOWN

$restored = new PolicyNode([
    'name' => 'read',
    'leaves' => [],
    'allow' => AccessStatus::ALLOW->value,
]);

var_dump($restored->getName());   // read
var_dump($restored->getEffect()); // AccessStatus::ALLOW
```

### `PolicyNode::addChild()`

返回新建或已经存在的同名子节点：

```php
$root = new PolicyNode('*');
$first = $root->addChild('article');
$second = $root->addChild('article');

var_dump($first->getName()); // article
var_dump($first === $second); // true，不会重复创建
```

### `PolicyNode::getName()`

返回当前节点名称：

```php
$node = new PolicyNode('article');

var_dump($node->getName()); // article
```

### `PolicyNode::getLeaves()`

返回以节点名称为键的直接子节点数组：

```php
$root = new PolicyNode('*');
$root->addChild('article');
$root->addChild('user');

$leaves = $root->getLeaves();

var_dump(array_keys($leaves));            // ["article", "user"]
var_dump($leaves['article']->getName());  // article
```

### `PolicyNode::setLeaves()`

该方法没有返回值；执行后原有的直接子节点会被整体替换：

```php
$root = new PolicyNode('*');
$article = new PolicyNode('article');
$user = new PolicyNode('user');

$result = $root->setLeaves([
    'article' => $article,
    'user' => $user,
]);

var_dump($result);                         // NULL
var_dump(array_keys($root->getLeaves())); // ["article", "user"]
```

数组键必须与节点名称一致。子节点引用自身或祖先形成循环时，`setLeaves()` 会抛出策略异常，并保留原有子节点。

### `PolicyNode::setAllow()` 与 `PolicyNode::getEffect()`

`setAllow()` 没有返回值，`getEffect()` 返回设置后的授权效果：

```php
$node = new PolicyNode('read');

var_dump($node->getEffect()); // AccessStatus::UNKNOWN

$result = $node->setAllow(AccessStatus::ALLOW);

var_dump($result);            // NULL
var_dump($node->getEffect()); // AccessStatus::ALLOW
```

### `PolicyNode::search()`

匹配成功时返回 `PolicyNode`，没有任何匹配分支时返回 `null`：

```php
$root = new PolicyNode('*');
$root->addChild('article')
    ->addChild('read')
    ->setAllow(AccessStatus::ALLOW);

$matched = $root->search('/article/read');
$missing = $root->search('/user/read');

var_dump($matched?->getName());   // read
var_dump($matched?->getEffect()); // AccessStatus::ALLOW
var_dump($missing);               // NULL
```

### `PolicyNode::toArray()`

递归返回当前节点及全部子节点：

```php
$node = new PolicyNode('read');
$node->setAllow(AccessStatus::ALLOW);

var_dump($node->toArray());
```

输出：

```php
array(3) {
  ["name"]=> string(4) "read"
  ["leaves"]=> array(0) {}
  ["allow"]=> string(5) "allow"
}
```

### `PolicyNode::restore()`

更新当前节点并返回节点自身，方便链式或继续使用：

```php
$node = new PolicyNode('article');
$result = $node->restore([
    'allow' => AccessStatus::DENY->value,
    'leaves' => [
        'read' => [
            'name' => 'read',
            'leaves' => [],
            'allow' => AccessStatus::ALLOW->value,
        ],
    ],
]);

var_dump($result === $node);                       // true
var_dump($node->getEffect());                      // AccessStatus::DENY
var_dump($node->search('/read')?->getEffect());    // AccessStatus::ALLOW
```

## API 概览

### `Policy`

| 方法 | 说明 | 执行结果 |
| --- | --- | --- |
| `addPath(string $path, AccessStatus $allow = AccessStatus::ALLOW): void` | 添加或更新路径规则 | 无返回值；后续 `check()` 使用新规则 |
| `check(string $path): AccessStatus` | 检查路径 | 返回 `AccessStatus::ALLOW`、`AccessStatus::DENY` 或 `AccessStatus::UNKNOWN` |
| `toArray(): array` | 导出完整策略树 | 返回嵌套数组 |

### `PolicyNode`

| 方法 | 说明 | 执行结果 |
| --- | --- | --- |
| `addChild(string $nodeName): PolicyNode` | 添加或获取直接子节点 | 返回对应的子节点实例 |
| `search(string $path): ?PolicyNode` | 搜索最匹配的策略节点 | 返回节点或 `null` |
| `getEffect(): AccessStatus` | 获取节点授权效果 | 返回 `AccessStatus::ALLOW`、`AccessStatus::DENY` 或 `AccessStatus::UNKNOWN` |
| `setAllow(AccessStatus $allow): void` | 设置节点授权效果 | 无返回值；节点效果被更新 |
| `getName(): string` | 获取节点名称 | 返回名称字符串 |
| `getLeaves(): array` | 获取直接子节点 | 返回 `array<array-key, PolicyNode>` |
| `setLeaves(array $leaves): void` | 替换直接子节点并验证结构 | 无返回值；子节点集合被替换 |
| `toArray(callable\|int\|null $filter = null): array` | 递归导出节点 | 返回嵌套数组 |
| `restore(array $data = []): static` | 从数组安全更新节点 | 返回当前节点实例 |

## 开发与测试

安装开发依赖：

```bash
composer install
```

运行 PHPUnit：

```bash
composer test
```

## 单进程性能测试

基准脚本会生成可重复的随机策略树，并按 50% 精确命中、30% 通配命中、20% 未命中的比例执行检查：

```bash
composer benchmark
```

默认生成 10,000 条规则并执行 100,000 次检查。也可以直接指定规则数、检查次数和随机种子：

```bash
php benchmark/PolicyBenchmark.php 50000 200000 20261016
```

参数顺序如下：

```text
php benchmark/PolicyBenchmark.php <规则数> <检查次数> <随机种子>
```

输出包含规则构建耗时、构建吞吐量、构建内存、检查耗时、检查吞吐量、平均延迟、峰值内存及结果分布。基准结果会受到 PHP 版本、硬件、负载和调试扩展影响，建议在相同环境中进行版本间对比。

以下是在 PHP 8.4.24、单进程、固定随机种子 `20261016` 下的一次参考结果：

| 规则数 | 检查次数 | 构建耗时 | 构建内存 | 检查耗时 | 检查吞吐量 | 平均延迟 | 峰值内存 |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 1,000 | 100,000 | 4.58 ms | 2.00 MiB | 98.13 ms | 1,019,048 ops/s | 0.981 μs | 14.03 MiB |
| 10,000 | 100,000 | 41.27 ms | 22.00 MiB | 118.68 ms | 842,589 ops/s | 1.187 μs | 32.03 MiB |
| 50,000 | 200,000 | 237.59 ms | 104.00 MiB | 347.88 ms | 574,916 ops/s | 1.739 μs | 120.05 MiB |

这些数字用于观察数量级和版本间变化，不应视为不同机器上的性能承诺。

## License

[Apache License 2.0](LICENSE)
