<?php

namespace GraphRedis\Tests;

use PHPUnit\Framework\TestCase;
use GraphRedis\GraphRedis;

/**
 * Cypher导入导出数据库ID支持测试
 */
class CypherDatabaseIdTest extends TestCase
{
    private GraphRedis $graph;
    private array $testFiles = [];
    
    protected function setUp(): void
    {
        // 使用默认数据库10
        $this->graph = new GraphRedis();
        
        // 清理所有测试数据库
        $this->cleanupTestDatabases();
    }
    
    protected function tearDown(): void
    {
        // 清理生成的测试文件
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // 清理所有测试数据库
        $this->cleanupTestDatabases();
    }
    
    private function cleanupTestDatabases(): void
    {
        // 清理数据库 0, 5, 10
        $databases = [0, 5, 10];
        foreach ($databases as $db) {
            $redis = $this->graph->getRedis();
            $redis->select($db);
            $redis->flushDb();
        }
        // 恢复到默认数据库10
        $this->graph->getRedis()->select(10);
    }
    
    /**
     * 测试基本的数据库ID导出功能
     */
    public function testBasicDatabaseExport(): void
    {
        // 在数据库10中创建测试数据
        $nodeId1 = $this->graph->addNode(['name' => 'Alice', 'type' => 'user']);
        $nodeId2 = $this->graph->addNode(['name' => 'Bob', 'type' => 'admin']);
        $this->graph->addEdge($nodeId1, $nodeId2, 1.0, ['relation' => 'friend']);
        
        // 导出到文件
        $exportPath = __DIR__ . '/temp_db10_export.cypher';
        $this->testFiles[] = $exportPath;
        
        $exportStats = $this->graph->exportToCypher($exportPath, [], 10);
        
        // 验证导出统计
        $this->assertTrue(file_exists($exportPath));
        $this->assertEquals(10, $exportStats['database']);
        $this->assertEquals(2, $exportStats['nodes_exported']);
        $this->assertEquals(1, $exportStats['edges_exported']);
        $this->assertGreaterThan(0, $exportStats['file_size']);
    }
    
    /**
     * 测试空数据库导出
     */
    public function testEmptyDatabaseExport(): void
    {
        // 导出空数据库5
        $exportPath = __DIR__ . '/temp_empty_export.cypher';
        $this->testFiles[] = $exportPath;
        
        $exportStats = $this->graph->exportToCypher($exportPath, [], 5);
        
        // 验证导出统计
        $this->assertTrue(file_exists($exportPath));
        $this->assertEquals(5, $exportStats['database']);
        $this->assertEquals(0, $exportStats['nodes_exported']);
        $this->assertEquals(0, $exportStats['edges_exported']);
        
        // 验证文件内容
        $content = file_get_contents($exportPath);
        $this->assertStringContainsString('Nodes: 0, Edges: 0', $content);
    }
    
    /**
     * 测试跨数据库导入功能
     */
    public function testCrossDatabaseImport(): void
    {
        // 在数据库10中创建测试数据
        $nodeId1 = $this->graph->addNode(['name' => 'Charlie', 'age' => 30]);
        $nodeId2 = $this->graph->addNode(['name' => 'David', 'age' => 25]);
        $this->graph->addEdge($nodeId1, $nodeId2, 2.0, ['type' => 'colleague']);
        
        // 导出数据库10的数据
        $exportPath = __DIR__ . '/temp_cross_db_export.cypher';
        $this->testFiles[] = $exportPath;
        
        $this->graph->exportToCypher($exportPath, [], 10);
        
        // 导入到数据库5
        $importStats = $this->graph->importFromCypher($exportPath, [], 5);
        
        // 验证导入统计
        $this->assertEquals(5, $importStats['database']);
        $this->assertEquals(2, $importStats['nodes_created']);
        $this->assertEquals(1, $importStats['edges_created']);
        $this->assertTrue($importStats['success']);
        
        // 验证数据库5中的数据
        $db5ExportPath = __DIR__ . '/temp_db5_verification.cypher';
        $this->testFiles[] = $db5ExportPath;
        
        $db5ExportStats = $this->graph->exportToCypher($db5ExportPath, [], 5);
        $this->assertEquals(2, $db5ExportStats['nodes_exported']);
        $this->assertEquals(1, $db5ExportStats['edges_exported']);
    }
    
    /**
     * 测试Cypher脚本生成的数据库ID支持
     */
    public function testCypherScriptGenerationWithDatabaseId(): void
    {
        // 在数据库10中创建数据
        $nodeId = $this->graph->addNode(['name' => 'Test', 'value' => 100]);
        
        // 生成数据库10的脚本
        $script10 = $this->graph->generateCypherScript([], 10);
        
        // 生成数据库5的脚本（空）
        $script5 = $this->graph->generateCypherScript([], 5);
        
        // 验证脚本内容
        $this->assertStringContainsString('CREATE (n1:', $script10);
        $this->assertStringContainsString('name: "Test"', $script10);
        $this->assertStringNotContainsString('CREATE (n1:', $script5);
        
        // 验证统计信息
        $this->assertStringContainsString('Nodes: 1', $script10);
        $this->assertStringContainsString('Nodes: 0', $script5);
    }
    
    /**
     * 测试字符串导入的数据库ID支持
     */
    public function testImportFromStringWithDatabaseId(): void
    {
        // 创建测试Cypher内容
        $cypherContent = '
        CREATE (n1:Person {name: "Eve", age: 28, __id: 1});
        CREATE (n2:Person {name: "Frank", age: 32, __id: 2});
        MATCH (from {{__id: 1}}), (to {{__id: 2}})
        CREATE (from)-[r:KNOWS {since: "2023", weight: 1}]->(to);
        ';
        
        // 导入到数据库0
        $importStats = $this->graph->importFromCypherString($cypherContent, [], 0);
        
        // 验证导入统计
        $this->assertEquals(0, $importStats['database']);
        $this->assertEquals(2, $importStats['nodes_created']);
        $this->assertEquals(1, $importStats['edges_created']);
        $this->assertTrue($importStats['success']);
        
        // 验证数据库0中的数据
        $db0Script = $this->graph->generateCypherScript([], 0);
        $this->assertStringContainsString('name: "Eve"', $db0Script);
        $this->assertStringContainsString('name: "Frank"', $db0Script);
    }
    
    /**
     * 测试数据库ID验证
     */
    public function testDatabaseIdValidation(): void
    {
        $exportPath = __DIR__ . '/temp_validation_test.cypher';
        $this->testFiles[] = $exportPath;
        
        // 测试无效的数据库ID
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis database number must be between 0 and 15');
        
        $this->graph->exportToCypher($exportPath, [], 16);
    }
    
    /**
     * 测试数据库切换后的恢复
     */
    public function testDatabaseRestoration(): void
    {
        // 获取当前数据库ID（应该是10）
        $reflection = new \ReflectionClass($this->graph);
        $databaseProperty = $reflection->getProperty('database');
        $databaseProperty->setAccessible(true);
        $originalDatabase = $databaseProperty->getValue($this->graph);
        
        $this->assertEquals(10, $originalDatabase);
        
        // 执行导出操作到数据库5
        $exportPath = __DIR__ . '/temp_restoration_test.cypher';
        $this->testFiles[] = $exportPath;
        
        $this->graph->exportToCypher($exportPath, [], 5);
        
        // 验证操作后数据库ID被恢复
        $currentDatabase = $databaseProperty->getValue($this->graph);
        $this->assertEquals(10, $currentDatabase);
    }
    
    /**
     * 测试导入导出的数据一致性
     */
    public function testDataConsistency(): void
    {
        // 创建复杂的测试数据
        $nodes = [];
        for ($i = 0; $i < 5; $i++) {
            $nodes[] = $this->graph->addNode([
                'name' => "Node{$i}",
                'value' => $i * 10,
                'type' => $i % 2 === 0 ? 'even' : 'odd'
            ]);
        }
        
        // 创建边
        for ($i = 0; $i < 4; $i++) {
            $this->graph->addEdge($nodes[$i], $nodes[$i + 1], 1.5, [
                'relation' => 'next',
                'weight_factor' => $i + 1
            ]);
        }
        
        // 导出数据
        $exportPath = __DIR__ . '/temp_consistency_test.cypher';
        $this->testFiles[] = $exportPath;
        
        $exportStats = $this->graph->exportToCypher($exportPath, [], 10);
        
        // 导入到新数据库
        $importStats = $this->graph->importFromCypher($exportPath, [], 3);
        
        // 验证数据一致性
        $this->assertEquals($exportStats['nodes_exported'], $importStats['nodes_created']);
        $this->assertEquals($exportStats['edges_exported'], $importStats['edges_created']);
        
        // 重新导出验证
        $verifyPath = __DIR__ . '/temp_verify_export.cypher';
        $this->testFiles[] = $verifyPath;
        
        $verifyStats = $this->graph->exportToCypher($verifyPath, [], 3);
        
        $this->assertEquals($exportStats['nodes_exported'], $verifyStats['nodes_exported']);
        $this->assertEquals($exportStats['edges_exported'], $verifyStats['edges_exported']);
    }
}