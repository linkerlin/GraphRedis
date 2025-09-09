<?php

namespace GraphRedis\Tests;

use PHPUnit\Framework\TestCase;
use GraphRedis\GraphRedis;

/**
 * Basic tests for GraphRedis functionality
 */
class GraphRedisTest extends TestCase
{
    /** @var GraphRedis */
    private $graph;
    
    protected function setUp(): void
    {
        $this->graph = new GraphRedis(null, 'test:graph:');
    }
    
    protected function tearDown(): void
    {
        if ($this->graph) {
            $this->graph->close();
        }
    }
    
    public function testCanCreateGraphRedisInstance()
    {
        $this->assertInstanceOf(GraphRedis::class, $this->graph);
    }
    
    public function testNodeOperations()
    {
        // Test adding a node
        $nodeId = 'user1';
        $properties = ['name' => 'John Doe', 'age' => 30];
        
        // Note: These tests will only pass if Redis is available
        // For now, we'll just test that the methods exist and are callable
        $this->assertTrue(method_exists($this->graph, 'addNode'));
        $this->assertTrue(method_exists($this->graph, 'getNode'));
        $this->assertTrue(method_exists($this->graph, 'removeNode'));
    }
    
    public function testEdgeOperations()
    {
        // Test that edge methods exist
        $this->assertTrue(method_exists($this->graph, 'addEdge'));
        $this->assertTrue(method_exists($this->graph, 'getOutgoingEdges'));
        $this->assertTrue(method_exists($this->graph, 'getIncomingEdges'));
    }
    
    public function testConnectionMethods()
    {
        // Test that connection methods exist
        $this->assertTrue(method_exists($this->graph, 'connect'));
        $this->assertTrue(method_exists($this->graph, 'close'));
    }
}