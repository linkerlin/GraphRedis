# GraphRedis - 基于 Redis 的图数据库

# GraphRedis - 基于 Redis 的图数据库

一个纯 PHP 实现的轻量级图数据库，使用 Redis 作为存储后端，提供高性能的图数据存储与查询功能。

## 🗺️ 目录导航

- [🚀 项目特色](#-项目特色)
- [📋 系统要求](#-系统要求)
- [🔧 安装配置](#-安装配置)
- [📖 核心概念](#-核心概念)
- [🛠 API 文档](#-api-文档)
  - [初始化连接](#初始化连接)
  - [节点操作](#节点操作)
  - [边操作](#边操作)
  - [🔄 Cypher 数据导入导出](#-cypher-数据导入导出) **⭐ 新增功能**
  - [图遍历算法](#图遍历算法)
  - [数据库隔离管理](#数据库隔离管理)
- [🎯 实际应用场景](#-实际应用场景)
- [⚡ 性能优化建议](#-性能优化建议)
- [🔍 数据存储结构](#-数据存储结构)
- [🚨 注意事项](#-注意事项)
- [🛣 发展路线](#-发展路线)
- [📄 许可证](#-许可证)
- [🤝 贡献指南](#-贡献指南)
- [📞 联系方式](#-联系方式)

## 🚀 项目特色

- **轻量级设计**：单文件实现，无复杂依赖
- **高性能存储**：基于 Redis 内存数据库，读写速度快
- **丰富功能**：支持节点管理、边操作、图遍历算法
- **简单易用**：清晰的 API 设计，易于集成和使用
- **灵活扩展**：支持节点和边的自定义属性

## 📋 系统要求

- PHP 7.4+ (推荐 PHP 8.0+)
- Redis 服务器
- PHP Redis 扩展 (phpredis)

## 🔧 安装配置

### 1. 环境准备

```bash
# 安装 Redis (macOS)
brew install redis

# 启动 Redis 服务
brew services start redis

# 或者手动启动
redis-server
```

### 2. PHP Redis 扩展

```bash
# 通过 PECL 安装
pecl install redis

# 或者通过 Composer 安装 Predis (替代方案)
composer require predis/predis
```

### 3. 项目使用

```bash
git clone <repository-url>
cd GraphRedis
composer install
composer run demo  # 运行示例
```

## 📖 核心概念

### 节点 (Node)
- 图中的实体对象，具有唯一 ID
- 支持任意键值对属性存储
- 自动生成全局唯一标识符

### 边 (Edge)  
- 连接两个节点的有向关系
- 支持权重设置 (默认为 1.0)
- 可附加自定义属性描述关系

### 图遍历
- 支持深度优先搜索 (DFS)
- 支持广度优先搜索 (BFS) 最短路径
- 内置访问深度限制防止无限循环

## 🛠 API 文档

### 初始化连接

```php
// 默认连接本地 Redis （数据库 0）
$graph = new GraphRedis();

// 自定义 Redis 连接
$graph = new GraphRedis('192.168.1.100', 6380);

// 指定 Redis 数据库（Redis 有 16 个数据库，编号 0-15）
$graph = new GraphRedis('127.0.0.1', 6379, 0, 5); // 使用数据库 5

// 完整参数示例
$graph = new GraphRedis(
    host: '127.0.0.1',      // Redis 主机
    port: 6379,             // Redis 端口
    timeout: 2,             // 连接超时（秒）
    database: 3             // 数据库编号（0-15）
);
```

### 节点操作

#### 创建节点
```php
// 创建用户节点
$userId = $graph->addNode([
    'name' => '张三',
    'age' => 25,
    'city' => '北京',
    'occupation' => '程序员'
]);
echo "创建的用户 ID: $userId";
```

#### 查询节点
```php
// 获取节点信息
$user = $graph->getNode($userId);
print_r($user);
/*
输出:
Array
(
    [name] => 张三
    [age] => 25
    [city] => 北京
    [occupation] => 程序员
)
*/
```

#### 更新节点
```php
// 增量更新节点属性
$graph->updateNode($userId, [
    'age' => 26,
    'title' => '高级开发工程师'
]);
```

#### 删除节点
```php
// 级联删除节点及其所有关联边
$graph->delNode($userId);
```

### 边操作

#### 创建关系
```php
// 创建几个用户
$alice = $graph->addNode(['name' => 'Alice', 'role' => 'Manager']);
$bob = $graph->addNode(['name' => 'Bob', 'role' => 'Developer']);
$charlie = $graph->addNode(['name' => 'Charlie', 'role' => 'Designer']);

// 建立关注关系 (有向边)
$graph->addEdge($alice, $bob, 1.0, [
    'type' => 'follows',
    'since' => '2023-01-01'
]);

// 建立协作关系 (更高权重)
$graph->addEdge($bob, $charlie, 2.5, [
    'type' => 'collaborates',
    'project' => 'WebApp'
]);
```

#### 删除关系
```php
// 删除指定的边
$graph->delEdge($alice, $bob);
```

#### 查询邻居
```php
// 获取出边邻居 (Alice 关注的人)
$following = $graph->neighbors($alice, 'out');
print_r($following);

// 获取入边邻居 (关注 Bob 的人) 
$followers = $graph->neighbors($bob, 'in');

// 分页查询 (第2页，每页10条)
$neighbors = $graph->neighbors($alice, 'out', 2, 10);
```

### Cypher 数据导入导出 🔄

#### 功能概述
GraphRedis 提供完整的 Cypher 格式数据导入导出功能，类似于关系型数据库的 SQL 导入导出。支持将图数据导出为标准 Cypher 脚本文件（.cypher），并可从 Cypher 文件重新导入数据。

#### 核心特性
- ✅ **标准兼容**：生成兼容 Neo4j 等图数据库的标准 Cypher 语法
- ✅ **完整数据**：支持节点、边、属性的完整导出和导入
- ✅ **数据库隔离**：支持指定 Redis 数据库 ID 进行导入导出操作
- ✅ **跨库迁移**：实现不同 Redis 数据库间的数据迁移和备份
- ✅ **错误处理**：完善的异常处理和数据验证机制
- ✅ **ID映射**：智能的 ID 映射机制，确保关系正确重建

#### 基本使用

```php
// 1. 导出图数据为 Cypher 格式
$exportStats = $graph->exportToCypher('/path/to/export.cypher', [
    'include_comments' => true,        // 包含注释说明
    'default_node_label' => 'Person',  // 默认节点标签
    'batch_size' => 1000               // 批处理大小
]);

echo "导出完成：";
echo "- 节点数量: {$exportStats['nodes_exported']}";
echo "- 边数量: {$exportStats['edges_exported']}";
echo "- 文件大小: {$exportStats['file_size']} bytes";
echo "- 导出时间: " . round($exportStats['export_time'], 4) . "s";

// 2. 清空当前数据库
$graph->clear();

// 3. 从 Cypher 文件导入数据
$importStats = $graph->importFromCypher('/path/to/export.cypher', [
    'continue_on_error' => false,  // 遇到错误是否继续
    'throw_on_error' => true       // 是否抛出异常
]);

echo "导入完成：";
echo "- 创建节点: {$importStats['nodes_created']}";
echo "- 创建边: {$importStats['edges_created']}";
echo "- 处理语句: {$importStats['statements_processed']}";
echo "- 导入时间: " . round($importStats['import_time'], 4) . "s";
```

#### 字符串操作

```php
// 生成 Cypher 脚本字符串
$cypherScript = $graph->generateCypherScript([
    'include_comments' => true,
    'default_node_label' => 'Entity'
]);
echo $cypherScript;

// 从字符串直接导入
$cypherContent = '
CREATE (n1:Person {name: "Alice", age: 28, __id: 1});
CREATE (n2:Person {name: "Bob", age: 32, __id: 2});
MATCH (from {__id: 1}), (to {__id: 2})
CREATE (from)-[r:FRIEND {since: "2020", weight: 1.0}]->(to);
';

$importStats = $graph->importFromCypherString($cypherContent, [
    'continue_on_error' => false
]);
```

#### 🔒 Redis 数据库隔离支持

##### 核心优势
- **多环境隔离**：开发、测试、生产环境数据完全分离
- **跨库数据迁移**：在不同 Redis 数据库间快速迁移数据
- **定向备份恢复**：对特定数据库进行精确备份和恢复
- **业务模块隔离**：不同业务使用不同数据库，避免数据污染

##### 指定数据库导出

```php
// 在数据库 10 中创建测试数据
$graph = new GraphRedis('127.0.0.1', 6379, 0, 10);
$alice = $graph->addNode(['name' => 'Alice', 'role' => 'Developer']);
$bob = $graph->addNode(['name' => 'Bob', 'role' => 'Designer']);
$graph->addEdge($alice, $bob, 1.0, ['relation' => 'colleague']);

// 导出数据库 10 的数据
$exportStats = $graph->exportToCypher('db10_export.cypher', [
    'include_comments' => true
], 10); // 显式指定源数据库 10

echo "导出源数据库: {$exportStats['database']}";
echo "导出节点: {$exportStats['nodes_exported']}";
echo "导出边: {$exportStats['edges_exported']}";

// 导出空数据库（如数据库 5）
$emptyStats = $graph->exportToCypher('empty_db5.cypher', [], 5);
echo "空数据库导出 - 节点: {$emptyStats['nodes_exported']}, 边: {$emptyStats['edges_exported']}";
```

##### 跨数据库导入

```php
// 将数据库 10 的数据导入到数据库 5
$importStats = $graph->importFromCypher('db10_export.cypher', [
    'continue_on_error' => false
], 5); // 导入到数据库 5

echo "导入目标数据库: {$importStats['database']}";
echo "创建节点: {$importStats['nodes_created']}";
echo "创建边: {$importStats['edges_created']}";

// 验证数据库 5 中的数据
$verifyStats = $graph->exportToCypher('db5_verification.cypher', [], 5);
echo "验证 - 数据库 5 节点: {$verifyStats['nodes_exported']}, 边: {$verifyStats['edges_exported']}";
```

##### 跨数据库迁移示例

```php
// 场景：从开发环境（数据库 0）迁移到测试环境（数据库 1）
$devGraph = new GraphRedis('127.0.0.1', 6379, 0, 0);  // 开发环境
$testGraph = new GraphRedis('127.0.0.1', 6379, 0, 1); // 测试环境

// 步骤 1：从开发环境导出数据
$devGraph->exportToCypher('dev_to_test_migration.cypher', [
    'include_comments' => true,
    'default_node_label' => 'DevNode'
], 0); // 从数据库 0 导出

// 步骤 2：清空测试环境（可选）
$testGraph->clear();

// 步骤 3：导入到测试环境
$migrationStats = $testGraph->importFromCypher('dev_to_test_migration.cypher', [
    'continue_on_error' => false
], 1); // 导入到数据库 1

echo "迁移完成：";
echo "- 迁移节点: {$migrationStats['nodes_created']}";
echo "- 迁移边: {$migrationStats['edges_created']}";
echo "- 目标数据库: {$migrationStats['database']}";

// 步骤 4：验证迁移结果
$devStats = $devGraph->getStats();
$testStats = $testGraph->getStats();
echo "\n数据一致性验证：";
echo "- 开发环境: {$devStats['nodes']} 节点, {$devStats['edges']} 边";
echo "- 测试环境: {$testStats['nodes']} 节点, {$testStats['edges']} 边";
```

##### 多环境数据备份策略

```php
// 生产环境备份脚本示例
function backupEnvironment($envName, $database, $backupDir) {
    $graph = new GraphRedis('127.0.0.1', 6379, 0, $database);
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = "{$backupDir}/{$envName}_backup_{$timestamp}.cypher";
    
    $stats = $graph->exportToCypher($backupFile, [
        'include_comments' => true,
        'default_node_label' => ucfirst($envName) . 'Node'
    ], $database);
    
    return [
        'env' => $envName,
        'database' => $database,
        'file' => $backupFile,
        'nodes' => $stats['nodes_exported'],
        'edges' => $stats['edges_exported'],
        'size' => $stats['file_size']
    ];
}

// 执行备份
$backups = [
    backupEnvironment('production', 2, '/backups'),
    backupEnvironment('staging', 1, '/backups'),
    backupEnvironment('development', 0, '/backups')
];

foreach ($backups as $backup) {
    echo "备份 {$backup['env']} 环境 (DB:{$backup['database']})：";
    echo "- 文件: {$backup['file']}";
    echo "- 数据: {$backup['nodes']} 节点, {$backup['edges']} 边";
    echo "- 大小: " . round($backup['size'] / 1024, 2) . " KB\n";
}
```

#### 数据格式示例

导出的 Cypher 文件格式：

```cypher
// =====================================================
// GraphRedis Cypher Export
// Generated: 2025-10-10 10:30:15
// Source Database: 10
// Nodes: 3, Edges: 3
// Memory Usage: 1.82M
// =====================================================

// ==================== 节点定义 ====================
CREATE (n1:Person {name: "Alice", age: 28, role: "Developer", __id: 1}); // 节点ID: 1
CREATE (n2:Person {name: "Bob", age: 32, role: "Designer", __id: 2}); // 节点ID: 2
CREATE (n3:Person {name: "Charlie", age: 25, role: "Manager", __id: 3}); // 节点ID: 3

// ==================== 关系定义 ====================
MATCH (from {__id: 1}), (to {__id: 2})
CREATE (from)-[r:COLLEAGUE {since: "2020", weight: 1.0}]->(to); // 边: 1 -> 2

MATCH (from {__id: 2}), (to {__id: 3})
CREATE (from)-[r:REPORTS_TO {since: "2023", weight: 0.8}]->(to); // 边: 2 -> 3

MATCH (from {__id: 1}), (to {__id: 3})
CREATE (from)-[r:COLLABORATES {project: "WebApp", weight: 0.9}]->(to); // 边: 1 -> 3

// =====================================================
// Export completed successfully
// Exported Nodes: 3, Exported Edges: 3
// Export Time: 0.0028s
// =====================================================
```

#### 错误处理和调试

```php
try {
    // 导入时的错误处理
    $importStats = $graph->importFromCypher($filePath, [
        'continue_on_error' => true,  // 遇到错误继续处理
        'throw_on_error' => false     // 不抛出异常
    ], 5);
    
    if (!$importStats['success']) {
        echo "导入失败，错误数量: {$importStats['errors']}\n";
        
        // 获取详细错误信息
        $importer = new GraphRedis\CypherImporter($graph);
        $errors = $importer->getErrors();
        foreach ($errors as $error) {
            echo "错误详情: {$error}\n";
        }
    } else {
        echo "导入成功完成\n";
    }
    
} catch (InvalidArgumentException $e) {
    echo "参数错误: " . $e->getMessage();
} catch (RuntimeException $e) {
    echo "运行时错误: " . $e->getMessage();
}

// 数据库ID验证
try {
    // 尝试使用无效的数据库ID
    $graph->exportToCypher('test.cypher', [], 16); // Redis只有0-15
} catch (InvalidArgumentException $e) {
    echo "数据库ID错误: " . $e->getMessage();
    // 输出: 数据库ID错误: Redis database number must be between 0 and 15, got 16
}
```

#### 性能优化建议

```php
// 大数据量导出时的优化配置
$largeExportOptions = [
    'include_comments' => false,      // 减少文件大小
    'batch_size' => 5000,            // 增大批处理大小
    'default_node_label' => 'Node'   // 使用简短标签
];

// 分批导入大文件
function importLargeFile($graph, $filePath, $database = null) {
    $content = file_get_contents($filePath);
    $statements = explode(';', $content);
    $batchSize = 100;
    $batches = array_chunk($statements, $batchSize);
    
    $totalStats = ['nodes_created' => 0, 'edges_created' => 0];
    
    foreach ($batches as $i => $batch) {
        echo "处理批次 " . ($i + 1) . "/" . count($batches) . "...\n";
        
        $batchContent = implode(';', $batch);
        $stats = $graph->importFromCypherString($batchContent, [
            'continue_on_error' => true
        ], $database);
        
        $totalStats['nodes_created'] += $stats['nodes_created'];
        $totalStats['edges_created'] += $stats['edges_created'];
    }
    
    return $totalStats;
}
```

### 图遍历算法

#### 最短路径查询 (BFS)
```php
// 查找 Alice 到 Charlie 的最短路径
$result = $graph->shortestPath($alice, $charlie, 6);

if ($result) {
    [$distance, $path] = $result;
    echo "最短距离: $distance 步";
    echo "路径: " . implode(' -> ', $path);
} else {
    echo "无法找到路径";
}
```

#### 深度优先遍历 (DFS)
```php
// 从 Alice 开始进行 DFS 遍历
$visitOrder = $graph->dfs($alice, 5);
echo "DFS 访问顺序: ";
print_r($visitOrder);
```

### 数据库隔离管理

Redis 提供 16 个数据库（编号 0-15），GraphRedis 支持指定数据库实现数据隔离。

#### 基本使用
```php
// 开发环境使用数据库 0
$devGraph = new GraphRedis('127.0.0.1', 6379, 0, 0);

// 测试环境使用数据库 1  
$testGraph = new GraphRedis('127.0.0.1', 6379, 0, 1);

// 生产环境使用数据库 2
$prodGraph = new GraphRedis('127.0.0.1', 6379, 0, 2);
```

#### 多应用隔离
```php
// 用户关系图 - 数据库 5
$userGraph = new GraphRedis('127.0.0.1', 6379, 0, 5);
$alice = $userGraph->addNode(['name' => 'Alice', 'type' => 'user']);

// 产品关系图 - 数据库 6
$productGraph = new GraphRedis('127.0.0.1', 6379, 0, 6);
$iphone = $productGraph->addNode(['name' => 'iPhone', 'type' => 'product']);

// 公司组织架构 - 数据库 7
$companyGraph = new GraphRedis('127.0.0.1', 6379, 0, 7);
$ceo = $companyGraph->addNode(['name' => 'CEO', 'role' => 'executive']);

// 数据完全隔离，Alice 只存在于用户图中
echo $userGraph->nodeExists($alice) ? '用户图中有 Alice' : '用户图中无 Alice';
echo $productGraph->nodeExists($alice) ? '产品图中有 Alice' : '产品图中无 Alice';
```

#### 数据库选择最佳实践
- **数据库 0-2**：环境隔离（开发/测试/生产）
- **数据库 3-7**：业务模块隔离（用户/产品/订单等）
- **数据库 8-15**：临时数据或实验性功能

#### 错误处理
```php
try {
    // 无效的数据库编号会抛出异常
    $graph = new GraphRedis('127.0.0.1', 6379, 0, 16);
} catch (InvalidArgumentException $e) {
    echo "错误: " . $e->getMessage();
    // 输出: 错误: Redis database number must be between 0 and 15, got 16
}
```

## 🎯 实际应用场景

### 社交网络分析
```php
$graph = new GraphRedis();

// 创建社交网络用户
$users = [];
$userNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve'];

foreach ($userNames as $name) {
    $users[$name] = $graph->addNode([
        'name' => $name,
        'joinDate' => date('Y-m-d'),
        'followers' => rand(10, 1000)
    ]);
}

// 建立关注关系网络
$graph->addEdge($users['Alice'], $users['Bob'], 1, ['type' => 'friend']);
$graph->addEdge($users['Bob'], $users['Charlie'], 1, ['type' => 'friend']);
$graph->addEdge($users['Alice'], $users['Diana'], 1, ['type' => 'follow']);
$graph->addEdge($users['Diana'], $users['Eve'], 1, ['type' => 'follow']);
$graph->addEdge($users['Charlie'], $users['Eve'], 1, ['type' => 'friend']);

// 分析 Alice 的社交圈
echo "Alice 的直接关注列表:\n";
$aliceFollowing = $graph->neighbors($users['Alice']);
foreach ($aliceFollowing as $userId => $weight) {
    $user = $graph->getNode($userId);
    echo "- {$user['name']} (权重: $weight)\n";
}

// 计算社交距离
$path = $graph->shortestPath($users['Alice'], $users['Eve']);
if ($path) {
    echo "\nAlice 到 Eve 的社交距离: {$path[0]} 步\n";
}
```

### 推荐系统
```php
$graph = new GraphRedis();

// 创建产品节点
$products = [
    'laptop' => $graph->addNode(['name' => '笔记本电脑', 'category' => '电子产品', 'price' => 5999]),
    'mouse' => $graph->addNode(['name' => '无线鼠标', 'category' => '配件', 'price' => 199]),
    'keyboard' => $graph->addNode(['name' => '机械键盘', 'category' => '配件', 'price' => 399]),
    'monitor' => $graph->addNode(['name' => '显示器', 'category' => '电子产品', 'price' => 1299])
];

// 创建用户
$user1 = $graph->addNode(['name' => 'User1', 'age' => 28]);
$user2 = $graph->addNode(['name' => 'User2', 'age' => 35]);

// 记录用户行为 (购买、浏览、收藏)
$graph->addEdge($user1, $products['laptop'], 10, ['action' => 'purchase', 'date' => '2023-10-01']);
$graph->addEdge($user1, $products['mouse'], 8, ['action' => 'purchase', 'date' => '2023-10-01']);
$graph->addEdge($user1, $products['monitor'], 5, ['action' => 'view', 'date' => '2023-10-05']);

$graph->addEdge($user2, $products['laptop'], 9, ['action' => 'purchase', 'date' => '2023-09-28']);
$graph->addEdge($user2, $products['keyboard'], 7, ['action' => 'purchase', 'date' => '2023-09-28']);

// 基于购买行为的产品推荐
function getRecommendations($graph, $userId, $maxDepth = 3) {
    $visited = $graph->dfs($userId, $maxDepth);
    $recommendations = [];
    
    foreach ($visited as $nodeId) {
        if ($nodeId !== $userId) {
            $node = $graph->getNode($nodeId);
            if (isset($node['category'])) { // 是产品节点
                $recommendations[] = $node;
            }
        }
    }
    
    return $recommendations;
}

echo "为 User1 推荐的产品:\n";
$recs = getRecommendations($graph, $user1);
foreach ($recs as $product) {
    echo "- {$product['name']} ({$product['category']}) - ¥{$product['price']}\n";
}
```

### 知识图谱
```php
$graph = new GraphRedis();

// 创建概念节点
$concepts = [
    'php' => $graph->addNode(['name' => 'PHP', 'type' => 'language']),
    'redis' => $graph->addNode(['name' => 'Redis', 'type' => 'database']),
    'graph_db' => $graph->addNode(['name' => '图数据库', 'type' => 'concept']),
    'nosql' => $graph->addNode(['name' => 'NoSQL', 'type' => 'category']),
    'memory_db' => $graph->addNode(['name' => '内存数据库', 'type' => 'concept'])
];

// 建立知识关系
$graph->addEdge($concepts['php'], $concepts['redis'], 1, ['relation' => '可以连接']);
$graph->addEdge($concepts['redis'], $concepts['nosql'], 1, ['relation' => '属于']);
$graph->addEdge($concepts['redis'], $concepts['memory_db'], 1, ['relation' => '是一种']);
$graph->addEdge($concepts['graph_db'], $concepts['nosql'], 1, ['relation' => '属于']);

// 知识推理：找到相关概念
echo "与 Redis 相关的概念:\n";
$related = $graph->neighbors($concepts['redis']);
foreach ($related as $conceptId => $weight) {
    $concept = $graph->getNode($conceptId);
    echo "- {$concept['name']} ({$concept['type']})\n";
}
```

## ⚡ 性能优化建议

### Redis 配置优化
```bash
# redis.conf 关键配置
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 使用模式建议
```php
// 1. 批量操作使用事务
$pipe = $graph->redis->multi();
for ($i = 0; $i < 1000; $i++) {
    $pipe->hSet("node:$i", ['batch' => 'insert']);
}
$pipe->exec();

// 2. 合理设置分页大小
$neighbors = $graph->neighbors($nodeId, 'out', 1, 50); // 避免一次查询过多

// 3. 限制遍历深度
$path = $graph->shortestPath($from, $to, 4); // 限制在4度以内
```

## 🔍 数据存储结构

GraphRedis 使用以下 Redis 数据结构：

```
node:{id}           -> Hash    # 节点属性
edge:{id}:out       -> ZSet    # 出边列表 (目标ID => 权重)
edge:{id}:in        -> ZSet    # 入边列表 (源ID => 权重)  
edge_prop:{from}:{to} -> Hash  # 边属性
global:node_id      -> String  # 节点ID计数器
```

## 🚨 注意事项

1. **内存管理**: Redis 是内存数据库，需要监控内存使用
2. **持久化**: 配置适当的 Redis 持久化策略
3. **并发控制**: 当前实现不支持事务，高并发场景需要额外处理
4. **数据备份**: 定期备份 Redis 数据文件
5. **网络延迟**: 大量操作时考虑使用 pipeline 批处理

## 🛣 发展路线

- [ ] 添加图算法库 (PageRank, 社区发现等)
- [ ] 支持无向图操作
- [ ] 增加索引功能
- [ ] 实现分布式存储
- [ ] 添加 Web 管理界面
- [ ] 性能监控和分析工具

## 📄 许可证

本项目采用 Apache 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🤝 贡献指南

欢迎提交 Issues 和 Pull Requests！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📞 联系方式

如有问题或建议，请通过 Issues 页面联系我们。

![ae55f8b030c0aa87b4c4303f8a02db7d.png](https://s2.loli.net/2025/09/09/q69j14Xr3NOMelV.png)