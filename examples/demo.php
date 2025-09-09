<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GraphRedis\GraphRedis;

/**
 * GraphRedis 示例演示
 * 
 * 运行命令: composer run demo
 * 或者: php examples/demo.php
 */

try {
    echo "=== GraphRedis 演示开始 ===\n\n";
    
    $g = new GraphRedis();
    
    // 清空之前的数据
    $g->clear();

    // 1. 创建用户节点
    echo "1. 创建用户节点...\n";
    $bob   = $g->addNode(['name' => 'Bob',   'age' => 32, 'city' => '北京']);
    $alice = $g->addNode(['name' => 'Alice', 'age' => 28, 'city' => '上海']);
    $tom   = $g->addNode(['name' => 'Tom',   'age' => 25, 'city' => '广州']);
    $jack  = $g->addNode(['name' => 'Jack',  'age' => 30, 'city' => '深圳']);
    $anni  = $g->addNode(['name' => 'Anni',  'age' => 27, 'city' => '杭州']);

    printf("创建了 5 个用户: Bob(%d), Alice(%d), Tom(%d), Jack(%d), Anni(%d)\n\n", 
           $bob, $alice, $tom, $jack, $anni);

    // 2. 建立关注关系（有向边）
    echo "2. 建立关注关系...\n";
    $g->addEdge($bob,   $alice, 1, ['label' => 'follow', 'since' => '2023-01-01']);
    $g->addEdge($alice, $tom,   1, ['label' => 'follow', 'since' => '2023-02-15']);
    $g->addEdge($tom,   $jack,  1, ['label' => 'follow', 'since' => '2023-03-10']);
    $g->addEdge($jack,  $anni,  1, ['label' => 'follow', 'since' => '2023-04-20']);
    $g->addEdge($bob,   $jack,  2, ['label' => 'colleague', 'since' => '2022-06-01']); // 权重 2

    echo "建立了关注链: Bob -> Alice -> Tom -> Jack -> Anni\n";
    echo "以及同事关系: Bob -> Jack (权重 2)\n\n";

    // 3. 查询节点信息
    echo "3. 查询用户信息...\n";
    $bobInfo = $g->getNode($bob);
    printf("Bob 的信息: %s, %d岁, 来自%s\n", $bobInfo['name'], $bobInfo['age'], $bobInfo['city']);
    
    // 4. 查询邻居关系
    echo "\n4. 查询关注关系...\n";
    $bobFollowing = $g->neighbors($bob);
    echo "Bob 的关注列表: ";
    foreach ($bobFollowing as $userId => $weight) {
        $user = $g->getNode($userId);
        printf("%s(权重:%s) ", $user['name'], $weight);
    }
    echo "\n";

    // 查询粉丝
    $jackFollowers = $g->neighbors($jack, 'in');
    echo "Jack 的粉丝列表: ";
    foreach ($jackFollowers as $userId => $weight) {
        $user = $g->getNode($userId);
        printf("%s(权重:%s) ", $user['name'], $weight);
    }
    echo "\n\n";

    // 5. DFS 遍历
    echo "5. 深度优先遍历...\n";
    $dfsOrder = $g->dfs($bob);
    echo "从 Bob 开始的 DFS 访问顺序: ";
    foreach ($dfsOrder as $nodeId) {
        $user = $g->getNode($nodeId);
        echo $user['name'] . " ";
    }
    echo "\n\n";

    // 6. 最短路径查询
    echo "6. 最短路径查询...\n";
    $path = $g->shortestPath($bob, $anni);
    if ($path) {
        [$distance, $pathNodes] = $path;
        echo "Bob -> Anni 最短路径 (距离: {$distance}): ";
        foreach ($pathNodes as $nodeId) {
            $user = $g->getNode($nodeId);
            echo $user['name'] . " ";
        }
        echo "\n";
    } else {
        echo "Bob 到 Anni 没有路径\n";
    }

    // 7. 边属性查询
    echo "\n7. 查询边属性...\n";
    $edgeProps = $g->getEdge($bob, $alice);
    if ($edgeProps) {
        printf("Bob -> Alice 关系: %s, 开始时间: %s\n", 
               $edgeProps['label'], $edgeProps['since']);
    }

    // 8. 图统计信息
    echo "\n8. 图统计信息...\n";
    $stats = $g->getStats();
    printf("节点数: %d, 边数: %d, 内存使用: %s\n", 
           $stats['nodes'], $stats['edges'], $stats['memory_usage']);

    // 9. 社交网络分析示例
    echo "\n9. 社交网络分析示例...\n";
    
    // 计算每个用户的影响力 (出度 + 入度)
    echo "用户影响力排行:\n";
    $users = [$bob => 'Bob', $alice => 'Alice', $tom => 'Tom', $jack => 'Jack', $anni => 'Anni'];
    $influence = [];
    
    foreach ($users as $userId => $name) {
        $outDegree = count($g->neighbors($userId, 'out'));
        $inDegree = count($g->neighbors($userId, 'in'));
        $influence[$name] = $outDegree + $inDegree;
    }
    
    arsort($influence);
    foreach ($influence as $name => $score) {
        printf("  %s: %d\n", $name, $score);
    }

    // 10. 推荐好友 (通过共同关注)
    echo "\n10. 推荐好友 (基于共同关注)...\n";
    
    // 为 Bob 推荐好友：找到 Bob 关注的人所关注的人
    $bobFollows = array_keys($g->neighbors($bob, 'out'));
    $recommendations = [];
    
    foreach ($bobFollows as $followedId) {
        $secondLevel = array_keys($g->neighbors($followedId, 'out'));
        foreach ($secondLevel as $candidateId) {
            // 排除 Bob 自己和已经关注的人
            if ($candidateId !== $bob && !in_array($candidateId, $bobFollows)) {
                $candidate = $g->getNode($candidateId);
                if ($candidate) {
                    $recommendations[$candidateId] = $candidate['name'];
                }
            }
        }
    }
    
    if ($recommendations) {
        echo "为 Bob 推荐的好友: " . implode(', ', array_unique($recommendations)) . "\n";
    } else {
        echo "暂无推荐好友\n";
    }

    echo "\n=== GraphRedis 演示结束 ===\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "请确保 Redis 服务正在运行 (redis-server)\n";
}