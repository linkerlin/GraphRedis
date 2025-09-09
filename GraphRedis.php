<?php
/**
 * 纯 PHP + Redis 手工图数据库
 * php GraphRedis.php   // 会跑一段 demo
 */
class GraphRedis
{
    private \Redis $redis;
    private int $pageSize = 100;   // neighbors 默认分页大小

    public function __construct(string $host = '127.0.0.1', int $port = 6379)
    {
        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
    }

    /* ---------- 节点相关 ---------- */

    /** 创建节点，返回全局唯一 id */
    public function addNode(array $prop): int
    {
        $id = $this->redis->incr('global:node_id');
        $this->redis->hSet("node:$id", $prop);
        return $id;
    }

    public function getNode(int $id): ?array
    {
        $raw = $this->redis->hGetAll("node:$id");
        return $raw ?: null;
    }

    /** 增量更新 */
    public function updateNode(int $id, array $diff): void
    {
        if ($diff) {
            $this->redis->hSet("node:$id", $diff);
        }
    }

    /** 级联删除节点 + 所有出入边 */
    public function delNode(int $id): void
    {
        $pipe = $this->redis->multi();
        // 1. 删出边
        $out = $pipe->zRange("edge:$id:out", 0, -1);
        foreach ($out as $to) {
            $pipe->del("edge_prop:$id:$to");
            $pipe->zRem("edge:$to:in", $id);
        }
        $pipe->del("edge:$id:out");

        // 2. 删入边
        $in = $pipe->zRange("edge:$id:in", 0, -1);
        foreach ($in as $from) {
            $pipe->del("edge_prop:$from:$id");
            $pipe->zRem("edge:$from:out", $id);
        }
        $pipe->del("edge:$id:in");

        // 3. 删节点本身
        $pipe->del("node:$id");
        $pipe->exec();
    }

    /* ---------- 边相关 ---------- */

    /** 添加/更新一条边（有向） */
    public function addEdge(int $from, int $to, float $weight = 1.0, array $prop = []): void
    {
        $pipe = $this->redis->multi();
        $pipe->zAdd("edge:$from:out", $weight, $to);
        $pipe->zAdd("edge:$to:in", $weight, $from);
        if ($prop) {
            $pipe->hSet("edge_prop:$from:$to", $prop);
        }
        $pipe->exec();
    }

    public function delEdge(int $from, int $to): void
    {
        $pipe = $this->redis->multi();
        $pipe->zRem("edge:$from:out", $to);
        $pipe->zRem("edge:$to:in", $from);
        $pipe->del("edge_prop:$from:$to");
        $pipe->exec();
    }

    /** 取邻居，支持分页，默认出边 */
    public function neighbors(int $id, string $dir = 'out', int $page = 1, ?int $size = null): array
    {
        $size = $size ?: $this->pageSize;
        $key  = "edge:$id:$dir";
        $start = ($page - 1) * $size;
        $stop  = $start + $size - 1;
        return $this->redis->zRange($key, $start, $stop, true); // true 返回 [id=>score]
    }

    /* ---------- 遍历算法 ---------- */

    /** BFS 最短路径，返回 [距离, 路径数组] 或 null */
    public function shortestPath(int $from, int $to, int $maxDepth = 6): ?array
    {
        if ($from === $to) return [0, [$from]];
        $q = new SplQueue();
        $q->enqueue([$from, 0, [$from]]);
        $seen = [$from => true];

        while (!$q->isEmpty()) {
            [$id, $depth, $path] = $q->dequeue();
            if ($depth >= $maxDepth) continue;

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

    /** 非递归 DFS，只返回访问顺序 */
    public function dfs(int $start, int $maxDepth = 6): array
    {
        $stack = new SplStack();
        $stack->push([$start, 0]);
        $seen = [$start => true];
        $order = [];

        while (!$stack->isEmpty()) {
            [$id, $d] = $stack->pop();
            $order[] = $id;
            if ($d >= $maxDepth) continue;

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
}

/* --------------------------------------------------
 * 下面是一段 Demo：建图 → 遍历 → 最短路径
 * php GraphRedis.php
 * -------------------------------------------------- */
if (PHP_SAPI === 'cli') {
    $g = new GraphRedis();

    // 1. 造 5 个用户
    $bob   = $g->addNode(['name' => 'Bob',   'age' => 32]);
    $alice = $g->addNode(['name' => 'Alice', 'age' => 28]);
    $tom   = $g->addNode(['name' => 'Tom',   'age' => 25]);
    $jack  = $g->addNode(['name' => 'Jack',  'age' => 30]);
    $anni  = $g->addNode(['name' => 'Anni',  'age' => 27]);

    // 2. 加关注关系（有向边）
    $g->addEdge($bob,   $alice, 1, ['label' => 'follow']);
    $g->addEdge($alice, $tom,   1, ['label' => 'follow']);
    $g->addEdge($tom,   $jack,  1, ['label' => 'follow']);
    $g->addEdge($jack,  $anni,  1, ['label' => 'follow']);
    $g->addEdge($bob,   $jack,  2, ['label' => 'colleague']); // 权重 2

    // 3. 查邻居
    echo "Bob 的关注列表：\n";
    print_r($g->neighbors($bob));

    // 4. DFS 遍历
    echo "从 Bob 开始的 DFS 顺序：\n";
    print_r($g->dfs($bob));

    // 5. 最短路径
    $path = $g->shortestPath($bob, $anni);
    echo "Bob -> Anni 最短路径：\n";
    print_r($path);

    // 6. 级联删除演示
    // $g->delNode($tom);
}
