<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GraphRedis\GraphRedis;

/**
 * Cypher导入导出功能演示
 */

try {
    echo "=== GraphRedis Cypher 导入导出演示 ===\n\n";
    
    // 1. 创建测试数据
    echo "1. 准备测试数据...\n";
    $graph = new GraphRedis();
    $graph->clear();
    
    // 创建节点
    $alice = $graph->addNode(['name' => 'Alice', 'age' => 28, 'city' => '北京']);
    $bob = $graph->addNode(['name' => 'Bob', 'age' => 32, 'city' => '上海']);
    $charlie = $graph->addNode(['name' => 'Charlie', 'age' => 25, 'city' => '深圳']);
    
    // 创建关系
    $graph->addEdge($alice, $bob, 0.8, ['type' => 'FRIEND', 'since' => '2020-01-15']);
    $graph->addEdge($bob, $charlie, 0.6, ['type' => 'COLLEAGUE', 'since' => '2022-03-10']);
    $graph->addEdge($charlie, $alice, 0.7, ['type' => 'MENTOR', 'since' => '2021-09-01']);
    
    $stats = $graph->getStats();
    echo "创建了 {$stats['nodes']} 个节点和 {$stats['edges']} 条边\n\n";
    
    // 2. 导出为Cypher格式
    echo "2. 导出为Cypher格式...\n";
    $exportPath = __DIR__ . '/test_export.cypher';
    
    $exportStats = $graph->exportToCypher($exportPath);
    echo "导出完成: 节点 {$exportStats['nodes_exported']}, 边 {$exportStats['edges_exported']}\n";
    
    // 3. 显示部分导出内容
    echo "\n3. 导出内容预览:\n";
    $content = file_get_contents($exportPath);
    $lines = array_slice(explode("\n", $content), 0, 15);
    echo implode("\n", $lines) . "\n...[已截断]\n\n";
    
    // 4. 导入测试
    echo "4. 导入测试...\n";
    $graph->clear();
    
    $importStats = $graph->importFromCypher($exportPath);
    echo "导入完成: 节点 {$importStats['nodes_created']}, 边 {$importStats['edges_created']}\n";
    
    // 5. 验证结果
    echo "\n5. 验证导入结果...\n";
    $afterStats = $graph->getStats();
    echo "导入后统计: 节点 {$afterStats['nodes']}, 边 {$afterStats['edges']}\n";
    
    // 验证节点
    for ($i = 1; $i <= 3; $i++) {
        $node = $graph->getNode($i);
        if ($node) {
            echo "- 节点 {$i}: {$node['name']} ({$node['age']}岁)\n";
        }
    }
    
    // 清理
    unlink($exportPath);
    echo "\n=== 演示完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}