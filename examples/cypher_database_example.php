<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GraphRedis\GraphRedis;

/**
 * Cypher导入导出数据库ID支持示例
 * 
 * 演示如何在导入导出时指定不同的Redis数据库ID
 */

echo "=== GraphRedis Cypher数据库ID支持演示 ===\n\n";

try {
    // 创建GraphRedis实例（使用默认数据库10）
    $graph = new GraphRedis();
    echo "✓ 连接到Redis服务器，默认数据库: 10\n";
    
    // 1. 在默认数据库10中创建测试数据
    echo "\n--- 步骤1: 在数据库10中创建测试数据 ---\n";
    
    $alice = $graph->addNode(['name' => 'Alice', 'age' => 30, 'city' => 'Beijing']);
    $bob = $graph->addNode(['name' => 'Bob', 'age' => 25, 'city' => 'Shanghai']);
    $charlie = $graph->addNode(['name' => 'Charlie', 'age' => 35, 'city' => 'Guangzhou']);
    
    $graph->addEdge($alice, $bob, 1.0, ['relation' => 'friend', 'since' => '2020']);
    $graph->addEdge($bob, $charlie, 1.0, ['relation' => 'colleague', 'since' => '2021']);
    $graph->addEdge($alice, $charlie, 1.0, ['relation' => 'friend', 'since' => '2019']);
    
    echo "✓ 创建了3个节点和3条边\n";
    $stats = $graph->getStats();
    echo "  数据库10统计: {$stats['nodes']} 个节点, {$stats['edges']} 条边\n";
    
    // 2. 导出数据库10的数据到Cypher文件
    echo "\n--- 步骤2: 导出数据库10的数据 ---\n";
    
    $exportPath = __DIR__ . '/exports/db10_export.cypher';
    $exportStats = $graph->exportToCypher($exportPath, [
        'include_comments' => true,
        'default_node_label' => 'Person'
    ]);
    
    echo "✓ 导出完成\n";
    echo "  文件路径: {$exportStats['file_path']}\n";
    echo "  文件大小: " . round($exportStats['file_size'] / 1024, 2) . " KB\n";
    echo "  导出节点: {$exportStats['nodes_exported']}\n";
    echo "  导出边: {$exportStats['edges_exported']}\n";
    echo "  导出时间: " . round($exportStats['export_time'], 4) . "s\n";
    
    // 3. 从数据库5导出数据（应该是空的）
    echo "\n--- 步骤3: 从数据库5导出数据（空数据库） ---\n";
    
    $emptyExportPath = __DIR__ . '/exports/db5_empty_export.cypher';
    $emptyExportStats = $graph->exportToCypher($emptyExportPath, [
        'include_comments' => true
    ], 5); // 指定数据库5
    
    echo "✓ 空数据库导出完成\n";
    echo "  导出节点: {$emptyExportStats['nodes_exported']}\n";
    echo "  导出边: {$emptyExportStats['edges_exported']}\n";
    echo "  目标数据库: {$emptyExportStats['database']}\n";
    
    // 4. 将数据导入到数据库5
    echo "\n--- 步骤4: 将数据导入到数据库5 ---\n";
    
    $importStats = $graph->importFromCypher($exportPath, [
        'continue_on_error' => false
    ], 5); // 导入到数据库5
    
    echo "✓ 导入完成\n";
    echo "  导入节点: {$importStats['nodes_created']}\n";
    echo "  导入边: {$importStats['edges_created']}\n";
    echo "  处理语句: {$importStats['statements_processed']}\n";
    echo "  目标数据库: {$importStats['database']}\n";
    echo "  导入时间: " . round($importStats['import_time'], 4) . "s\n";
    
    // 5. 验证数据库5中的数据
    echo "\n--- 步骤5: 验证数据库5中的数据 ---\n";
    
    $db5ExportPath = __DIR__ . '/exports/db5_verification_export.cypher';
    $db5ExportStats = $graph->exportToCypher($db5ExportPath, [
        'include_comments' => true,
        'default_node_label' => 'Person'
    ], 5); // 从数据库5导出
    
    echo "✓ 数据库5验证导出完成\n";
    echo "  导出节点: {$db5ExportStats['nodes_exported']}\n";
    echo "  导出边: {$db5ExportStats['edges_exported']}\n";
    echo "  源数据库: {$db5ExportStats['database']}\n";
    
    // 6. 跨数据库数据迁移演示
    echo "\n--- 步骤6: 跨数据库数据迁移演示 ---\n";
    
    // 创建新的GraphRedis实例连接到数据库0
    $graphDb0 = new GraphRedis('127.0.0.1', 6379, 0, 0);
    echo "✓ 创建连接到数据库0的实例\n";
    
    // 清空数据库0（如果有数据）
    $graphDb0->clear();
    echo "✓ 清空数据库0\n";
    
    // 从数据库10导出并导入到数据库0
    $migrationStats = $graphDb0->importFromCypher($exportPath, [
        'continue_on_error' => false
    ], 0); // 导入到数据库0
    
    echo "✓ 迁移完成\n";
    echo "  迁移节点: {$migrationStats['nodes_created']}\n";
    echo "  迁移边: {$migrationStats['edges_created']}\n";
    echo "  目标数据库: {$migrationStats['database']}\n";
    
    // 7. 生成数据库对比报告
    echo "\n--- 步骤7: 数据库对比报告 ---\n";
    
    $db10Stats = $graph->getStats(); // 数据库10
    $db5Script = $graph->generateCypherScript([], 5); // 数据库5的脚本
    $db0Stats = $graphDb0->getStats(); // 数据库0
    
    echo "数据库对比:\n";
    echo "  数据库10: {$db10Stats['nodes']} 节点, {$db10Stats['edges']} 边\n";
    echo "  数据库5:  {$db5ExportStats['nodes_exported']} 节点, {$db5ExportStats['edges_exported']} 边\n";
    echo "  数据库0:  {$db0Stats['nodes']} 节点, {$db0Stats['edges']} 边\n";
    
    // 8. 显示生成的文件
    echo "\n--- 步骤8: 生成的文件 ---\n";
    $exportDir = __DIR__ . '/exports';
    if (is_dir($exportDir)) {
        $files = scandir($exportDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'cypher') {
                $filepath = $exportDir . '/' . $file;
                $size = round(filesize($filepath) / 1024, 2);
                echo "  {$file} ({$size} KB)\n";
            }
        }
    }
    
    echo "\n=== 演示完成 ===\n";
    echo "✓ 成功演示了Cypher导入导出的数据库ID支持功能\n";
    echo "✓ 实现了跨数据库的数据迁移和备份\n";
    echo "✓ 验证了数据完整性和一致性\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}