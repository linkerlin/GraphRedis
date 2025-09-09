<?php

namespace GraphRedis\Tests;

use PHPUnit\Framework\TestCase;
use GraphRedis\GraphRedis;

/**
 * GraphRedis 单元测试
 */
class GraphRedisTest extends TestCase
{
    private GraphRedis $graph;

    protected function setUp(): void
    {
        $this->graph = new GraphRedis();
        $this->graph->clear(); // 清空测试数据
    }

    protected function tearDown(): void
    {
        $this->graph->clear(); // 清理测试数据
    }

    public function testAddNode(): void
    {
        $nodeId = $this->graph->addNode(['name' => 'Test', 'age' => 25]);
        
        $this->assertIsInt($nodeId);
        $this->assertGreaterThan(0, $nodeId);
        $this->assertTrue($this->graph->nodeExists($nodeId));
    }

    public function testGetNode(): void
    {
        $props = ['name' => 'Alice', 'age' => 30, 'city' => 'Beijing'];
        $nodeId = $this->graph->addNode($props);
        
        $retrieved = $this->graph->getNode($nodeId);
        
        $this->assertEquals($props, $retrieved);
    }

    public function testUpdateNode(): void
    {
        $nodeId = $this->graph->addNode(['name' => 'Bob', 'age' => 25]);
        
        $this->graph->updateNode($nodeId, ['age' => 26, 'city' => 'Shanghai']);
        
        $updated = $this->graph->getNode($nodeId);
        $this->assertEquals('Bob', $updated['name']);
        $this->assertEquals('26', $updated['age']);
        $this->assertEquals('Shanghai', $updated['city']);
    }

    public function testAddEdge(): void
    {
        $node1 = $this->graph->addNode(['name' => 'Alice']);
        $node2 = $this->graph->addNode(['name' => 'Bob']);
        
        $this->graph->addEdge($node1, $node2, 2.5, ['type' => 'friend']);
        
        $this->assertTrue($this->graph->edgeExists($node1, $node2));
        
        $neighbors = $this->graph->neighbors($node1, 'out');
        $this->assertArrayHasKey($node2, $neighbors);
        $this->assertEquals(2.5, $neighbors[$node2]);
    }

    public function testGetEdge(): void
    {
        $node1 = $this->graph->addNode(['name' => 'Alice']);
        $node2 = $this->graph->addNode(['name' => 'Bob']);
        
        $edgeProps = ['type' => 'follows', 'since' => '2023-01-01'];
        $this->graph->addEdge($node1, $node2, 1.0, $edgeProps);
        
        $retrieved = $this->graph->getEdge($node1, $node2);
        $this->assertEquals($edgeProps, $retrieved);
    }

    public function testDelEdge(): void
    {
        $node1 = $this->graph->addNode(['name' => 'Alice']);
        $node2 = $this->graph->addNode(['name' => 'Bob']);
        
        $this->graph->addEdge($node1, $node2);
        $this->assertTrue($this->graph->edgeExists($node1, $node2));
        
        $this->graph->delEdge($node1, $node2);
        $this->assertFalse($this->graph->edgeExists($node1, $node2));
    }

    public function testDelNode(): void
    {
        $node1 = $this->graph->addNode(['name' => 'Alice']);
        $node2 = $this->graph->addNode(['name' => 'Bob']);
        $node3 = $this->graph->addNode(['name' => 'Charlie']);
        
        // 建立关系: Alice -> Bob -> Charlie
        $this->graph->addEdge($node1, $node2);
        $this->graph->addEdge($node2, $node3);
        
        // 删除 Bob 节点
        $this->graph->delNode($node2);
        
        $this->assertFalse($this->graph->nodeExists($node2));
        $this->assertFalse($this->graph->edgeExists($node1, $node2));
        $this->assertFalse($this->graph->edgeExists($node2, $node3));
    }

    public function testNeighbors(): void
    {
        $alice = $this->graph->addNode(['name' => 'Alice']);
        $bob = $this->graph->addNode(['name' => 'Bob']);
        $charlie = $this->graph->addNode(['name' => 'Charlie']);
        
        $this->graph->addEdge($alice, $bob, 1.0);
        $this->graph->addEdge($alice, $charlie, 2.0);
        $this->graph->addEdge($bob, $alice, 3.0);
        
        // 测试出边
        $outNeighbors = $this->graph->neighbors($alice, 'out');
        $this->assertCount(2, $outNeighbors);
        $this->assertArrayHasKey($bob, $outNeighbors);
        $this->assertArrayHasKey($charlie, $outNeighbors);
        
        // 测试入边
        $inNeighbors = $this->graph->neighbors($alice, 'in');
        $this->assertCount(1, $inNeighbors);
        $this->assertArrayHasKey($bob, $inNeighbors);
    }

    public function testShortestPath(): void
    {
        $alice = $this->graph->addNode(['name' => 'Alice']);
        $bob = $this->graph->addNode(['name' => 'Bob']);
        $charlie = $this->graph->addNode(['name' => 'Charlie']);
        $david = $this->graph->addNode(['name' => 'David']);
        
        // 建立路径: Alice -> Bob -> Charlie -> David
        $this->graph->addEdge($alice, $bob);
        $this->graph->addEdge($bob, $charlie);
        $this->graph->addEdge($charlie, $david);
        
        $path = $this->graph->shortestPath($alice, $david);
        
        $this->assertNotNull($path);
        [$distance, $pathNodes] = $path;
        $this->assertEquals(3, $distance);
        $this->assertEquals([$alice, $bob, $charlie, $david], $pathNodes);
    }

    public function testDfs(): void
    {
        $alice = $this->graph->addNode(['name' => 'Alice']);
        $bob = $this->graph->addNode(['name' => 'Bob']);
        $charlie = $this->graph->addNode(['name' => 'Charlie']);
        
        $this->graph->addEdge($alice, $bob);
        $this->graph->addEdge($alice, $charlie);
        
        $visited = $this->graph->dfs($alice);
        
        $this->assertContains($alice, $visited);
        $this->assertContains($bob, $visited);
        $this->assertContains($charlie, $visited);
        $this->assertEquals($alice, $visited[0]); // 起始节点应该是第一个
    }

    public function testGetStats(): void
    {
        $this->graph->addNode(['name' => 'Alice']);
        $this->graph->addNode(['name' => 'Bob']);
        $node1 = $this->graph->addNode(['name' => 'Charlie']);
        $node2 = $this->graph->addNode(['name' => 'David']);
        
        $this->graph->addEdge($node1, $node2);
        
        $stats = $this->graph->getStats();
        
        $this->assertEquals(4, $stats['nodes']);
        $this->assertEquals(1, $stats['edges']);
        $this->assertArrayHasKey('memory_usage', $stats);
    }

    public function testSelfPath(): void
    {
        $node = $this->graph->addNode(['name' => 'Self']);
        
        $path = $this->graph->shortestPath($node, $node);
        
        $this->assertNotNull($path);
        [$distance, $pathNodes] = $path;
        $this->assertEquals(0, $distance);
        $this->assertEquals([$node], $pathNodes);
    }

    public function testNoPath(): void
    {
        $node1 = $this->graph->addNode(['name' => 'Isolated1']);
        $node2 = $this->graph->addNode(['name' => 'Isolated2']);
        
        // 没有边连接
        $path = $this->graph->shortestPath($node1, $node2);
        
        $this->assertNull($path);
    }
}