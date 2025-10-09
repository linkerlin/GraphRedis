<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GraphRedis\GraphRedis;

/**
 * Cypher 导入导出快速使用示例
 */

echo "=== Cypher 导入导出快速示例 ===\n\n";

try {
    $graph = new GraphRedis();
    $graph->clear();
    
    // 1. 创建示例数据
    echo "1. 创建示例数据...\n";
    $alice = $graph->addNode(['name' => 'Alice', 'role' => 'Developer']);
    $bob = $graph->addNode(['name' => 'Bob', 'role' => 'Designer']);
    $charlie = $graph->addNode(['name' => 'Charlie', 'role' => 'Manager']);
    
    $graph->addEdge($alice, $bob, 0.9, ['type' => 'COLLABORATES_WITH', 'project' => 'WebApp']);
    $graph->addEdge($bob, $charlie, 0.7, ['type' => 'REPORTS_TO', 'since' => '2023-01-01']);
    $graph->addEdge($alice, $charlie, 0.8, ['type' => 'REPORTS_TO', 'since' => '2022-06-15']);
    
    echo "✓ 创建了3个节点和3条边\n\n";
    
    // 2. 导出数据
    echo "2. 导出为Cypher格式...\n";
    $exportPath = __DIR__ . '/quick_example.cypher';
    
    $exportStats = $graph->exportToCypher($exportPath, [
        'default_node_label' => 'Employee',
        'include_comments' => true
    ]);
    
    echo "✓ 导出完成: {$exportStats['file_path']}\n";
    echo "  - 节点: {$exportStats['nodes_exported']}\n";
    echo "  - 边: {$exportStats['edges_exported']}\n";
    echo "  - 文件大小: {$exportStats['file_size']} bytes\n\n";
    
    // 3. 显示导出内容
    echo "3. 导出内容:\n";
    echo str_repeat('-', 50) . "\n";
    echo file_get_contents($exportPath);
    echo str_repeat('-', 50) . "\n\n";
    
    // 4. 清空并重新导入
    echo "4. 清空数据库并重新导入...\n";
    $graph->clear();
    
    $importStats = $graph->importFromCypher($exportPath);
    
    echo "✓ 导入完成:\n";
    echo "  - 节点: {$importStats['nodes_created']}\n";
    echo "  - 边: {$importStats['edges_created']}\n";
    echo "  - 耗时: " . round($importStats['import_time'] * 1000, 2) . "ms\n\n";
    
    // 5. 验证数据
    echo "5. 验证导入的数据...\n";
    $stats = $graph->getStats();
    echo "✓ 最终统计: 节点 {$stats['nodes']}, 边 {$stats['edges']}\n";
    
    // 显示重建的数据
    for ($i = 1; $i <= 3; $i++) {
        $node = $graph->getNode($i);
        if ($node) {
            echo "  - 节点 {$i}: {$node['name']} ({$node['role']})\n";
        }
    }
    
    // 6. 清理临时文件
    unlink($exportPath);
    echo "\n✓ 清理完成\n";
    
    echo "\n=== 示例成功完成 ===\n";
    echo "ℹ️  详细文档请查看: CYPHER_GUIDE.md\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "请确保 Redis 服务正在运行\n";
}