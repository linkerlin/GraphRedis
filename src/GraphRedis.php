<?php

namespace GraphRedis;

use Redis;
use SplQueue;
use SplStack;

/**
 * GraphRedis - A lightweight graph database using Redis as storage backend
 *
 * @package GraphRedis
 * @author Your Name
 * @license MIT
 */
class GraphRedis
{
    private Redis $redis;
    private int $pageSize = 100;   // neighbors 默认分页大小
    private int $database = 0;     // Redis database number

    /**
     * GraphRedis constructor
     *
     * @param string $host Redis host
     * @param int $port Redis port
     * @param int $timeout Connection timeout
     * @param int $database Redis database number (0-15)
     * @throws \RedisException
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, int $timeout = 0, int $database = 0)
    {
        if ($database < 0 || $database > 15) {
            throw new \InvalidArgumentException("Redis database number must be between 0 and 15, got {$database}");
        }

        $this->redis = new Redis();
        $this->database = $database;

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new \RedisException("Failed to connect to Redis server at {$host}:{$port}");
        }

        if ($database !== 0) {
            if (!$this->redis->select($database)) {
                throw new \RedisException("Failed to select Redis database {$database}");
            }
        }
    }

    /**
     * Get Redis instance
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * Set page size for neighbor queries
     *
     * @param int $size Page size
     * @return void
     */
    public function setPageSize(int $size): void
    {
        $this->pageSize = $size;
    }

    /* ---------- 节点相关 ---------- */

    /**
     * 创建节点，返回全局唯一 id
     *
     * @param array $prop Node properties
     * @return int Node ID
     */
    public function addNode(array $prop): int
    {
        $counterKey = $this->database === 0 ? 'global:node_id' : "global:node_id:db{$this->database}";
        $id = $this->redis->incr($counterKey);
        if (!empty($prop)) {
            $this->redis->hMSet("node:$id", $prop);
        }
        return $id;
    }

    /**
     * Get node by ID
     *
     * @param int $id Node ID
     * @return array|null Node properties or null if not found
     */
    public function getNode(int $id): ?array
    {
        $raw = $this->redis->hGetAll("node:$id");
        return $raw ?: null;
    }

    /**
     * 增量更新节点属性
     *
     * @param int $id Node ID
     * @param array $diff Properties to update
     * @return void
     */
    public function updateNode(int $id, array $diff): void
    {
        if (!empty($diff)) {
            $this->redis->hMSet("node:$id", $diff);
        }
    }

    /**
     * 级联删除节点 + 所有出入边
     *
     * @param int $id Node ID
     * @return void
     */
    public function delNode(int $id): void
    {
        // 先查询出入边列表（在事务外）
        $out = $this->redis->zRange("edge:$id:out", 0, -1);
        $in = $this->redis->zRange("edge:$id:in", 0, -1);

        // 开始事务删除
        $pipe = $this->redis->multi();

        // 1. 删出边
        if ($out) {
            foreach ($out as $to) {
                $pipe->del("edge_prop:$id:$to");
                $pipe->zRem("edge:$to:in", $id);
            }
        }
        $pipe->del("edge:$id:out");

        // 2. 删入边
        if ($in) {
            foreach ($in as $from) {
                $pipe->del("edge_prop:$from:$id");
                $pipe->zRem("edge:$from:out", $id);
            }
        }
        $pipe->del("edge:$id:in");

        // 3. 删节点本身
        $pipe->del("node:$id");
        $pipe->exec();
    }

    /**
     * Check if node exists
     *
     * @param int $id Node ID
     * @return bool
     */
    public function nodeExists(int $id): bool
    {
        return $this->redis->exists("node:$id") > 0;
    }

    /* ---------- 边相关 ---------- */

    /**
     * 添加/更新一条边（有向）
     *
     * @param int $from Source node ID
     * @param int $to Target node ID
     * @param float $weight Edge weight
     * @param array $prop Edge properties
     * @return void
     */
    public function addEdge(int $from, int $to, float $weight = 1.0, array $prop = []): void
    {
        $pipe = $this->redis->multi();
        $pipe->zAdd("edge:$from:out", $weight, $to);
        $pipe->zAdd("edge:$to:in", $weight, $from);
        if (!empty($prop)) {
            $pipe->hMSet("edge_prop:$from:$to", $prop);
        }
        $pipe->exec();
    }

    /**
     * Delete an edge
     *
     * @param int $from Source node ID
     * @param int $to Target node ID
     * @return void
     */
    public function delEdge(int $from, int $to): void
    {
        $pipe = $this->redis->multi();
        $pipe->zRem("edge:$from:out", $to);
        $pipe->zRem("edge:$to:in", $from);
        $pipe->del("edge_prop:$from:$to");
        $pipe->exec();
    }

    /**
     * Get edge properties
     *
     * @param int $from Source node ID
     * @param int $to Target node ID
     * @return array|null Edge properties or null if not found
     */
    public function getEdge(int $from, int $to): ?array
    {
        $raw = $this->redis->hGetAll("edge_prop:$from:$to");
        return $raw ?: null;
    }

    /**
     * Check if edge exists
     *
     * @param int $from Source node ID
     * @param int $to Target node ID
     * @return bool
     */
    public function edgeExists(int $from, int $to): bool
    {
        return $this->redis->zScore("edge:$from:out", $to) !== false;
    }

    /**
     * 取邻居，支持分页，默认出边
     *
     * @param int $id Node ID
     * @param string $dir Direction: 'out' or 'in'
     * @param int $page Page number (1-based)
     * @param int|null $size Page size
     * @return array Array of [id => weight]
     */
    public function neighbors(int $id, string $dir = 'out', int $page = 1, ?int $size = null): array
    {
        $size = $size ?: $this->pageSize;
        $key  = "edge:$id:$dir";
        $start = ($page - 1) * $size;
        $stop  = $start + $size - 1;
        return $this->redis->zRange($key, $start, $stop, true); // true 返回 [id=>score]
    }

    /* ---------- 遍历算法 ---------- */

    /**
     * BFS 最短路径，返回 [距离, 路径数组] 或 null
     *
     * @param int $from Source node ID
     * @param int $to Target node ID
     * @param int $maxDepth Maximum search depth
     * @return array|null [distance, path] or null if no path found
     */
    public function shortestPath(int $from, int $to, int $maxDepth = 6): ?array
    {
        if ($from === $to) {
            return [0, [$from]];
        }

        $q = new SplQueue();
        $q->enqueue([$from, 0, [$from]]);
        $seen = [$from => true];

        while (!$q->isEmpty()) {
            [$id, $depth, $path] = $q->dequeue();
            if ($depth >= $maxDepth) {
                continue;
            }

            foreach ($this->neighbors($id, 'out', 1, 100) as $next => $weight) {
                if ($next == $to) {
                    return [$depth + 1, array_merge($path, [$next])];
                }
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $q->enqueue([$next, $depth + 1, array_merge($path, [$next])]);
                }
            }
        }
        return null;
    }

    /**
     * 非递归 DFS，只返回访问顺序
     *
     * @param int $start Starting node ID
     * @param int $maxDepth Maximum search depth
     * @return array Array of visited node IDs in order
     */
    public function dfs(int $start, int $maxDepth = 6): array
    {
        $stack = new SplStack();
        $stack->push([$start, 0]);
        $seen = [$start => true];
        $order = [];

        while (!$stack->isEmpty()) {
            [$id, $d] = $stack->pop();
            $order[] = $id;
            if ($d >= $maxDepth) {
                continue;
            }

            // 逆序推栈，保证顺序友好
            $neighbors = array_keys($this->neighbors($id, 'out', 1, 100));
            foreach (array_reverse($neighbors) as $next) {
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $stack->push([$next, $d + 1]);
                }
            }
        }
        return $order;
    }

    /**
     * Get graph statistics
     *
     * @return array Statistics about the graph
     */
    public function getStats(): array
    {
        $counterKey = $this->database === 0 ? 'global:node_id' : "global:node_id:db{$this->database}";
        $nodeCount = $this->redis->get($counterKey) ?: 0;
        $edgeCount = 0;

        // Count edges by scanning all node edge lists
        $pattern = 'edge:*:out';
        $keys = $this->redis->keys($pattern);
        foreach ($keys as $key) {
            $edgeCount += $this->redis->zCard($key);
        }

        return [
            'nodes' => (int) $nodeCount,
            'edges' => $edgeCount,
            'memory_usage' => $this->redis->info('memory')['used_memory_human'] ?? 'N/A'
        ];
    }

    /**
     * Clear all graph data
     *
     * @return void
     */
    public function clear(): void
    {
        $this->redis->flushDb();
    }

    /* ---------- Cypher 导入导出 ---------- */

    /**
     * 导出图数据为Cypher格式
     *
     * @param string $filePath 导出文件路径
     * @param array $options 导出选项
     * @return array 导出统计信息
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function exportToCypher(string $filePath, array $options = []): array
    {
        $exporter = new CypherExporter($this);
        return $exporter->exportToFile($filePath, $options);
    }

    /**
     * 从Cypher文件导入图数据
     *
     * @param string $filePath Cypher文件路径
     * @param array $options 导入选项
     * @return array 导入统计信息
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function importFromCypher(string $filePath, array $options = []): array
    {
        $importer = new CypherImporter($this);
        return $importer->importFromFile($filePath, $options);
    }

    /**
     * 生成Cypher脚本字符串
     *
     * @param array $options 导出选项
     * @return string Cypher脚本内容
     */
    public function generateCypherScript(array $options = []): string
    {
        $exporter = new CypherExporter($this);
        return $exporter->generateCypherScript($options);
    }

    /**
     * 从Cypher字符串导入数据
     *
     * @param string $cypherContent Cypher内容
     * @param array $options 导入选项
     * @return array 导入统计信息
     */
    public function importFromCypherString(string $cypherContent, array $options = []): array
    {
        $importer = new CypherImporter($this);
        return $importer->importFromString($cypherContent, $options);
    }
}
