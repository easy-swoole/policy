<?php


namespace EasySwoole\Policy;


use EasySwoole\Policy\Exception\Exception as PolicyException;
use EasySwoole\Spl\SplBean;

class PolicyNode extends SplBean
{
    /*
     * 当前节点路径
     */
    protected string $name = '*';
    /**
     * 子节点列表
     * @var array<array-key, PolicyNode>
     */
    protected array $leaves = [];
    /*
     * 当前节点权限
     */
    protected string $allow = 'unknown';

    /**
     * @param string|array<string, mixed> $info
     */
    public function __construct(string|array $info = '*')
    {
        parent::__construct();

        if (is_string($info)) {
            $this->assertValidName($info);
            $this->name = $info;
        } else {
            $this->hydrate($info);
        }

    }

    public function addChild(string $nodeName): PolicyNode
    {
        if ($nodeName === '' || str_contains($nodeName, '/')) {
            throw new PolicyException('Policy node name must be a non-empty path segment.');
        }

        if (isset($this->leaves[$nodeName])) {
            $node = $this->leaves[$nodeName];
        } else {
            $node = new PolicyNode($nodeName);
            $this->leaves[$nodeName] = $node;
        }
        return $node;
    }

    /**
     * 获取节点名称
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取节点的权限
     * @return array<array-key, PolicyNode>
     */
    public function getLeaves(): array
    {
        return $this->leaves;
    }

    /**
     * 设置节点权限
     * @param array<array-key, mixed> $leaves
     */
    public function setLeaves(array $leaves): void
    {
        foreach ($leaves as $name => $node) {
            if (!$node instanceof PolicyNode) {
                throw new PolicyException("Policy leaf '{$name}' must be a PolicyNode instance.");
            }
            // PHP stores some numeric string keys as integers.
            if ((string) $name !== $node->getName()) {
                throw new PolicyException("Policy leaf key '{$name}' must match the node name.");
            }
        }
        $active = [spl_object_id($this) => true];
        $visited = [];
        foreach ($leaves as $node) {
            self::assertAcyclic($node, $active, $visited);
        }

        $this->leaves = $leaves;
    }

    /**
     * 判断是否允许
     */
    public function getEffect(): AccessStatus
    {
        return AccessStatus::from($this->allow);
    }

    /**
     * 设置是否允许
     */
    public function setAllow(AccessStatus $allow): void
    {
        $this->allow = $allow->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(callable|int|null $filter = null): array
    {
        $list = parent::toArray($filter);
        if (isset($list['leaves'])) {
            foreach ($list['leaves'] as $key => $item) {
                $list['leaves'][$key] = $item->toArray($filter);
            }
        }
        return $list;
    }

    /**
     * 搜索实体路径。精确节点优先；精确分支没有有效授权时回退到通配节点。
     */
    public function search(string $path): ?PolicyNode
    {
        $path = trim($path, '/');
        if ($path === '') {
            return $this;
        }

        $segments = explode('/', $path);
        if (in_array('', $segments, true)) {
            return null;
        }

        return $this->searchSegments($segments, 0);
    }

    /**
     * @param list<string> $segments
     */
    private function searchSegments(array $segments, int $index): ?PolicyNode
    {
        if ($index >= count($segments)) {
            return $this;
        }

        $segment = $segments[$index];
        $exactMatch = null;

        if (isset($this->leaves[$segment])) {
            $exactMatch = $this->leaves[$segment]->searchSegments($segments, $index + 1);
            if ($exactMatch !== null && $exactMatch->getEffect() !== AccessStatus::UNKNOWN) {
                return $exactMatch;
            }
        }

        if (isset($this->leaves['*'])) {
            $wildcard = $this->leaves['*'];

            // A terminal wildcard applies to every remaining descendant.
            if ($wildcard->leaves === []) {
                return $wildcard;
            }

            $wildcardMatch = $wildcard->searchSegments($segments, $index + 1);
            if ($wildcardMatch !== null && $wildcardMatch->getEffect() !== AccessStatus::UNKNOWN) {
                return $wildcardMatch;
            }
            if ($wildcard->getEffect() !== AccessStatus::UNKNOWN) {
                return $wildcard;
            }
            if ($wildcardMatch !== null) {
                return $wildcardMatch;
            }
        }

        return $exactMatch;
    }

    /**
     * Restore node data without bypassing validation.
     *
     * @param array<string, mixed> $data
     */
    public function restore(array $data = []): static
    {
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                throw new PolicyException('Policy node name must be a string.');
            }
            $this->assertValidName($data['name']);
            if ($data['name'] !== $this->name) {
                throw new PolicyException('Policy node names are immutable after construction.');
            }
        }
        if (array_key_exists('allow', $data)) {
            $this->setAllow($this->parseStoredEffect($data['allow']));
        }
        if (array_key_exists('leaves', $data)) {
            if (!is_array($data['leaves'])) {
                throw new PolicyException('Policy leaves must be an array.');
            }
            $this->setLeaves($this->normalizeLeaves($data['leaves']));
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): void
    {
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                throw new PolicyException('Policy node name must be a string.');
            }
            $this->assertValidName($data['name']);
            $this->name = $data['name'];
        }
        if (array_key_exists('allow', $data)) {
            $this->setAllow($this->parseStoredEffect($data['allow']));
        }
        if (array_key_exists('leaves', $data)) {
            if (!is_array($data['leaves'])) {
                throw new PolicyException('Policy leaves must be an array.');
            }
            $this->setLeaves($this->normalizeLeaves($data['leaves']));
        }
    }

    /**
     * @param array<array-key, mixed> $leaves
     * @return array<array-key, PolicyNode>
     */
    private function normalizeLeaves(array $leaves): array
    {
        $normalized = [];
        foreach ($leaves as $name => $node) {
            $normalized[$name] = match (true) {
                $node instanceof PolicyNode => $node,
                is_array($node) => new PolicyNode($node),
                default => throw new PolicyException("Policy leaf '{$name}' must be a PolicyNode or an array."),
            };
        }

        return $normalized;
    }

    private function parseStoredEffect(mixed $value): AccessStatus
    {
        if (!is_string($value)) {
            throw new PolicyException('Policy effect must be a string.');
        }

        return AccessStatus::tryFrom($value)
            ?? throw new PolicyException("Invalid policy effect '{$value}'.");
    }

    /**
     * @param array<int, true> $active
     * @param array<int, true> $visited
     */
    private static function assertAcyclic(PolicyNode $node, array &$active, array &$visited): void
    {
        $id = spl_object_id($node);
        if (isset($active[$id])) {
            throw new PolicyException('Circular policy node reference.');
        }
        if (isset($visited[$id])) {
            return;
        }

        $active[$id] = true;
        foreach ($node->leaves as $child) {
            self::assertAcyclic($child, $active, $visited);
        }
        unset($active[$id]);
        $visited[$id] = true;
    }

    private function assertValidName(string $name): void
    {
        if ($name === '' || str_contains($name, '/')) {
            throw new PolicyException('Policy node name must be a non-empty path segment.');
        }
    }
}
