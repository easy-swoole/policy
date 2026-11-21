<?php

declare(strict_types=1);

namespace EasySwoole\Policy\Test;

use EasySwoole\Policy\AccessStatus;
use EasySwoole\Policy\Policy;
use EasySwoole\Policy\PolicyNode;
use EasySwoole\Spl\SplBean;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    public function testExactRulesAndTerminalWildcard(): void
    {
        $policy = new Policy();
        $policy->addPath('/user/add', AccessStatus::ALLOW);
        $policy->addPath('/user/update', AccessStatus::ALLOW);
        $policy->addPath('/user/delete', AccessStatus::DENY);
        $policy->addPath('/user/*', AccessStatus::DENY);

        self::assertSame(AccessStatus::ALLOW, $policy->check('/user/add'));
        self::assertSame(AccessStatus::DENY, $policy->check('/user/delete'));
        self::assertSame(AccessStatus::DENY, $policy->check('/user/other/deep'));
        self::assertSame(AccessStatus::UNKNOWN, $policy->check('/unknown'));
    }

    public function testFailedExactBranchFallsBackToWildcard(): void
    {
        $policy = new Policy();
        $policy->addPath('/api/*', AccessStatus::ALLOW);
        $policy->addPath('/api/order/charge', AccessStatus::DENY);
        $policy->addPath('/api/order/info', AccessStatus::DENY);

        self::assertSame(AccessStatus::DENY, $policy->check('/api/order/charge'));
        self::assertSame(AccessStatus::ALLOW, $policy->check('/api/order/other'));
    }

    public function testNodeCannotConsumeTheSameSegmentTwice(): void
    {
        $policy = new Policy();
        $policy->addPath('/foo', AccessStatus::ALLOW);

        self::assertSame(AccessStatus::UNKNOWN, $policy->check('/foo/foo'));
    }

    public function testIntermediateWildcardMatchesExactlyOneSegment(): void
    {
        $policy = new Policy();
        $policy->addPath('/a/*/c', AccessStatus::ALLOW);

        self::assertSame(AccessStatus::ALLOW, $policy->check('/a/x/c'));
        self::assertSame(AccessStatus::UNKNOWN, $policy->check('/a/x/y/c'));
    }

    public function testMultipleWildcardsMatchTheDocumentedRoute(): void
    {
        $policy = new Policy();
        $policy->addPath('/Api/Admin/Merchant/*/*', AccessStatus::ALLOW);

        self::assertSame(
            AccessStatus::ALLOW,
            $policy->check('/Api/Admin/Merchant/Merchant/getMerchantList')
        );
    }

    public function testWildcardEffectRemainsFallbackWhenItHasChildren(): void
    {
        $policy = new Policy();
        $policy->addPath('/api/*', AccessStatus::ALLOW);
        $policy->addPath('/api/*/private', AccessStatus::DENY);

        self::assertSame(AccessStatus::ALLOW, $policy->check('/api/foo'));
        self::assertSame(AccessStatus::DENY, $policy->check('/api/foo/private'));
        self::assertSame(AccessStatus::ALLOW, $policy->check('/api/foo/other'));
    }

    public function testArrayHydrationRecursivelyRestoresNodes(): void
    {
        $node = new PolicyNode([
            'name' => '*',
            'allow' => AccessStatus::UNKNOWN->value,
            'leaves' => [
                'api' => [
                    'name' => 'api',
                    'allow' => AccessStatus::ALLOW->value,
                    'leaves' => [],
                ],
            ],
        ]);

        self::assertSame(AccessStatus::ALLOW, $node->search('/api')?->getEffect());
        self::assertSame('api', $node->toArray()['leaves']['api']['name']);
        self::assertSame('allow', $node->toArray()['leaves']['api']['allow']);

        $node->restore(['allow' => 'deny']);
        self::assertSame(AccessStatus::DENY, $node->getEffect());
    }

    public function testNumericPathSegmentsSurviveArrayRoundTrip(): void
    {
        $policy = new Policy();
        $policy->addPath('/users/123', AccessStatus::ALLOW);
        $policy->addPath('/users/0123', AccessStatus::DENY);

        $data = $policy->toArray();
        $restored = new PolicyNode($data);

        self::assertSame($data, $restored->toArray());
        self::assertSame(AccessStatus::ALLOW, $restored->search('/users/123')?->getEffect());
        self::assertSame(AccessStatus::DENY, $restored->search('/users/0123')?->getEffect());
    }

    public function testArrayHydrationRejectsInvalidEffect(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PolicyNode(['allow' => 'invalid']);
    }

    public function testRestoreCannotChangeNodeName(): void
    {
        $node = new PolicyNode('original');

        $this->expectException(InvalidArgumentException::class);
        $node->restore(['name' => 'renamed']);
    }

    public function testEmptyPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Policy())->addPath('');
    }

    public function testEmptyPathSegmentIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Policy())->addPath('/api//order');
    }

    public function testSetAllowRejectsString(): void
    {
        $this->expectException(\TypeError::class);

        (new PolicyNode('test'))->setAllow('allow');
    }

    public function testSetLeavesRejectsSelfReference(): void
    {
        $node = new PolicyNode('node');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular policy node reference.');

        $node->setLeaves(['node' => $node]);
    }

    public function testSetLeavesRejectsAnAncestorWithoutReplacingExistingChildren(): void
    {
        $root = new PolicyNode('root');
        $child = $root->addChild('child');
        $existing = $child->addChild('existing');

        try {
            $child->setLeaves(['root' => $root]);
            self::fail('Expected a circular reference to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Circular policy node reference.', $exception->getMessage());
        }

        self::assertSame(['existing' => $existing], $child->getLeaves());
    }

    public function testArrayFilterPropagatesToChildNodes(): void
    {
        $node = new PolicyNode('root');
        $node->addChild('child')->setAllow(AccessStatus::ALLOW);

        $filtered = $node->toArray(SplBean::FILTER_NOT_EMPTY);

        self::assertSame('allow', $filtered['leaves']['child']['allow']);
        self::assertArrayNotHasKey('leaves', $filtered['leaves']['child']);
    }
}
