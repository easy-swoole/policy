<?php

declare(strict_types=1);

namespace EasySwoole\Policy\Test;

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
        $policy->addPath('/user/add', PolicyNode::EFFECT_ALLOW);
        $policy->addPath('/user/update', PolicyNode::EFFECT_ALLOW);
        $policy->addPath('/user/delete', PolicyNode::EFFECT_DENY);
        $policy->addPath('/user/*', PolicyNode::EFFECT_DENY);

        self::assertSame(PolicyNode::EFFECT_ALLOW, $policy->check('/user/add'));
        self::assertSame(PolicyNode::EFFECT_DENY, $policy->check('/user/delete'));
        self::assertSame(PolicyNode::EFFECT_DENY, $policy->check('/user/other/deep'));
        self::assertSame(PolicyNode::EFFECT_UNKNOWN, $policy->check('/unknown'));
    }

    public function testFailedExactBranchFallsBackToWildcard(): void
    {
        $policy = new Policy();
        $policy->addPath('/api/*', PolicyNode::EFFECT_ALLOW);
        $policy->addPath('/api/order/charge', PolicyNode::EFFECT_DENY);
        $policy->addPath('/api/order/info', PolicyNode::EFFECT_DENY);

        self::assertSame(PolicyNode::EFFECT_DENY, $policy->check('/api/order/charge'));
        self::assertSame(PolicyNode::EFFECT_ALLOW, $policy->check('/api/order/other'));
    }

    public function testNodeCannotConsumeTheSameSegmentTwice(): void
    {
        $policy = new Policy();
        $policy->addPath('/foo', PolicyNode::EFFECT_ALLOW);

        self::assertSame(PolicyNode::EFFECT_UNKNOWN, $policy->check('/foo/foo'));
    }

    public function testIntermediateWildcardMatchesExactlyOneSegment(): void
    {
        $policy = new Policy();
        $policy->addPath('/a/*/c', PolicyNode::EFFECT_ALLOW);

        self::assertSame(PolicyNode::EFFECT_ALLOW, $policy->check('/a/x/c'));
        self::assertSame(PolicyNode::EFFECT_UNKNOWN, $policy->check('/a/x/y/c'));
    }

    public function testMultipleWildcardsMatchTheDocumentedRoute(): void
    {
        $policy = new Policy();
        $policy->addPath('/Api/Admin/Merchant/*/*', PolicyNode::EFFECT_ALLOW);

        self::assertSame(
            PolicyNode::EFFECT_ALLOW,
            $policy->check('/Api/Admin/Merchant/Merchant/getMerchantList')
        );
    }

    public function testWildcardEffectRemainsFallbackWhenItHasChildren(): void
    {
        $policy = new Policy();
        $policy->addPath('/api/*', PolicyNode::EFFECT_ALLOW);
        $policy->addPath('/api/*/private', PolicyNode::EFFECT_DENY);

        self::assertSame(PolicyNode::EFFECT_ALLOW, $policy->check('/api/foo'));
        self::assertSame(PolicyNode::EFFECT_DENY, $policy->check('/api/foo/private'));
        self::assertSame(PolicyNode::EFFECT_ALLOW, $policy->check('/api/foo/other'));
    }

    public function testArrayHydrationRecursivelyRestoresNodes(): void
    {
        $node = new PolicyNode([
            'name' => '*',
            'allow' => PolicyNode::EFFECT_UNKNOWN,
            'leaves' => [
                'api' => [
                    'name' => 'api',
                    'allow' => PolicyNode::EFFECT_ALLOW,
                    'leaves' => [],
                ],
            ],
        ]);

        self::assertSame(PolicyNode::EFFECT_ALLOW, $node->search('/api')?->getEffect());
        self::assertSame('api', $node->toArray()['leaves']['api']['name']);
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

    public function testInvalidEffectIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PolicyNode('test'))->setAllow('invalid');
    }

    public function testArrayFilterPropagatesToChildNodes(): void
    {
        $node = new PolicyNode('root');
        $node->addChild('child')->setAllow(PolicyNode::EFFECT_ALLOW);

        $filtered = $node->toArray(SplBean::FILTER_NOT_EMPTY);

        self::assertSame('allow', $filtered['leaves']['child']['allow']);
        self::assertArrayNotHasKey('leaves', $filtered['leaves']['child']);
    }
}
