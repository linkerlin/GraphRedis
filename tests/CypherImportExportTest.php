<?php

namespace GraphRedis\Tests;

use PHPUnit\Framework\TestCase;
use GraphRedis\GraphRedis;

/**
 * Cypher导入导出功能测试
 */
class CypherImportExportTest extends TestCase
{
    private GraphRedis $graph;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->graph = new GraphRedis();
        $this->graph->clear();
        $this->tempDir = sys_get_temp_dir() . '/graphredis_test';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->graph->clear();
        $this->cleanupTempFiles();
    }

    private function cleanupTempFiles(): void
    {
        $files = glob($this->tempDir . '/*.cypher');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testBasicExportImport(): void
    {
        // 创建测试数据
        $alice = $this->graph->addNode(['name' => 'Alice', 'age' => 30]);
        $bob = $this->graph->addNode(['name' => 'Bob', 'age' => 25]);
        $this->graph->addEdge($alice, $bob, 1.5, ['type' => 'FRIEND']);

        // 导出
        $exportPath = $this->tempDir . '/test_basic.cypher';
        $exportStats = $this->graph->exportToCypher($exportPath);

        $this->assertEquals(2, $exportStats['nodes_exported']);
        $this->assertEquals(1, $exportStats['edges_exported']);
        $this->assertFileExists($exportPath);

        // 清空并导入
        $this->graph->clear();
        $importStats = $this->graph->importFromCypher($exportPath);

        $this->assertTrue($importStats['success']);
        $this->assertEquals(2, $importStats['nodes_created']);
        $this->assertEquals(1, $importStats['edges_created']);

        // 验证数据
        $stats = $this->graph->getStats();
        $this->assertEquals(2, $stats['nodes']);
        $this->assertEquals(1, $stats['edges']);
    }

    public function testCypherScriptGeneration(): void
    {
        // 创建节点
        $node1 = $this->graph->addNode(['name' => 'Test', 'value' => 123]);
        $node2 = $this->graph->addNode(['name' => 'Node', 'active' => true]);
        $this->graph->addEdge($node1, $node2, 2.0, ['relation' => 'test']);

        // 生成脚本
        $script = $this->graph->generateCypherScript();

        $this->assertStringContainsString('CREATE', $script);
        $this->assertStringContainsString('MATCH', $script);
        $this->assertStringContainsString('Test', $script);
        $this->assertStringContainsString('Node', $script);
    }

    public function testImportFromString(): void
    {
        $cypherContent = '
            CREATE (n1:Person {name: "Alice", age: 30, __id: 1});
            CREATE (n2:Person {name: "Bob", age: 25, __id: 2});
            MATCH (from {__id: 1}), (to {__id: 2})
            CREATE (from)-[r:FRIEND {weight: 1.0}]->(to);
        ';

        $importStats = $this->graph->importFromCypherString($cypherContent);

        $this->assertTrue($importStats['success']);
        $this->assertEquals(2, $importStats['nodes_created']);
        $this->assertEquals(1, $importStats['edges_created']);
    }

    public function testInvalidFileExtension(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('文件必须使用.cypher扩展名');

        $this->graph->exportToCypher('/tmp/test.txt');
    }

    public function testNonExistentImportFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('文件不存在');

        $this->graph->importFromCypher('/non/existent/file.cypher');
    }
}