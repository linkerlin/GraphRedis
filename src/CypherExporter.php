<?php

namespace GraphRedis;

/**
 * Cypher数据导出器 - 将GraphRedis数据导出为Cypher格式
 * 
 * 支持完整的图数据导出，包括节点、边和属性信息
 * 生成标准Cypher语句，兼容Neo4j等图数据库
 * 
 * @package GraphRedis
 * @author GraphRedis Team
 * @license MIT
 */
class CypherExporter
{
    private GraphRedis $graph;
    private array $exportStats = [];
    
    /**
     * CypherExporter constructor
     * 
     * @param GraphRedis $graph GraphRedis实例
     */
    public function __construct(GraphRedis $graph)
    {
        $this->graph = $graph;
    }
    
    /**
     * 导出完整图数据为Cypher格式文件
     * 
     * @param string $filePath 导出文件路径（.cypher扩展名）
     * @param array $options 导出选项
     * @return array 导出统计信息
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function exportToFile(string $filePath, array $options = []): array
    {
        // 验证文件路径
        if (!str_ends_with(strtolower($filePath), '.cypher')) {
            throw new \InvalidArgumentException("文件必须使用.cypher扩展名");
        }
        
        // 确保目录存在
        $directory = dirname($filePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException("无法创建目录: {$directory}");
        }
        
        // 生成Cypher内容
        $cypherContent = $this->generateCypherScript($options);
        
        // 写入文件
        if (file_put_contents($filePath, $cypherContent) === false) {
            throw new \RuntimeException("无法写入文件: {$filePath}");
        }
        
        $this->exportStats['file_path'] = $filePath;
        $this->exportStats['file_size'] = filesize($filePath);
        
        return $this->exportStats;
    }
    
    /**
     * 生成完整的Cypher脚本
     * 
     * @param array $options 导出选项
     * @return string Cypher脚本内容
     */
    public function generateCypherScript(array $options = []): string
    {
        $startTime = microtime(true);
        $cypher = [];
        
        // 添加文件头注释
        $cypher[] = $this->generateHeader($options);
        
        // 导出节点
        $nodesCypher = $this->exportNodes($options);
        if (!empty($nodesCypher)) {
            $cypher[] = "// ==================== 节点定义 ====================";
            $cypher[] = $nodesCypher;
        }
        
        // 导出边关系
        $edgesCypher = $this->exportEdges($options);
        if (!empty($edgesCypher)) {
            $cypher[] = "\n// ==================== 关系定义 ====================";
            $cypher[] = $edgesCypher;
        }
        
        // 添加统计信息
        $this->exportStats['export_time'] = microtime(true) - $startTime;
        $cypher[] = $this->generateFooter();
        
        return implode("\n", $cypher);
    }
    
    /**
     * 导出所有节点为CREATE语句
     * 
     * @param array $options 导出选项
     * @return string 节点CREATE语句
     */
    private function exportNodes(array $options = []): string
    {
        $nodes = [];
        $nodeCount = 0;
        $batchSize = $options['batch_size'] ?? 1000;
        
        // 获取节点ID计数器
        $counterKey = $this->graph->getRedis()->get('global:node_id') ?: 0;
        
        // 批量处理节点
        for ($nodeId = 1; $nodeId <= $counterKey; $nodeId++) {
            $nodeData = $this->graph->getNode($nodeId);
            if ($nodeData === null) {
                continue; // 跳过已删除的节点
            }
            
            $cypherNode = $this->formatNodeCreate($nodeId, $nodeData, $options);
            if ($cypherNode) {
                $nodes[] = $cypherNode;
                $nodeCount++;
                
                // 批量处理控制
                if ($nodeCount % $batchSize === 0) {
                    $nodes[] = "// 已处理 {$nodeCount} 个节点";
                }
            }
        }
        
        $this->exportStats['nodes_exported'] = $nodeCount;
        return implode("\n", $nodes);
    }
    
    /**
     * 导出所有边为MATCH + CREATE语句
     * 
     * @param array $options 导出选项
     * @return string 边CREATE语句
     */
    private function exportEdges(array $options = []): string
    {
        $edges = [];
        $edgeCount = 0;
        $processedEdges = []; // 避免重复处理
        
        // 扫描所有出边
        $pattern = 'edge:*:out';
        $edgeKeys = $this->graph->getRedis()->keys($pattern);
        
        foreach ($edgeKeys as $edgeKey) {
            // 解析节点ID
            if (!preg_match('/edge:(\d+):out/', $edgeKey, $matches)) {
                continue;
            }
            
            $fromNodeId = (int)$matches[1];
            
            // 获取该节点的所有出边
            $outEdges = $this->graph->getRedis()->zRange($edgeKey, 0, -1, true);
            
            foreach ($outEdges as $toNodeId => $weight) {
                $edgeId = "{$fromNodeId}:{$toNodeId}";
                
                // 避免重复处理
                if (isset($processedEdges[$edgeId])) {
                    continue;
                }
                $processedEdges[$edgeId] = true;
                
                // 获取边属性
                $edgeProps = $this->graph->getEdge($fromNodeId, $toNodeId) ?: [];
                $edgeProps['weight'] = $weight; // 添加权重属性
                
                $cypherEdge = $this->formatEdgeCreate($fromNodeId, $toNodeId, $edgeProps, $options);
                if ($cypherEdge) {
                    $edges[] = $cypherEdge;
                    $edgeCount++;
                }
            }
        }
        
        $this->exportStats['edges_exported'] = $edgeCount;
        return implode("\n", $edges);
    }
    
    /**
     * 格式化节点CREATE语句
     * 
     * @param int $nodeId 节点ID
     * @param array $properties 节点属性
     * @param array $options 格式化选项
     * @return string Cypher CREATE语句
     */
    private function formatNodeCreate(int $nodeId, array $properties, array $options = []): string
    {
        $includeComments = $options['include_comments'] ?? true;
        $nodeLabel = $options['default_node_label'] ?? 'Node';
        
        // 处理节点标签
        $label = $properties['__label'] ?? $nodeLabel;
        unset($properties['__label']); // 移除内部标签属性
        
        // 添加内部ID作为属性
        $properties['__id'] = $nodeId;
        
        // 构建属性字符串
        $propsStr = $this->formatProperties($properties);
        
        $cypher = "CREATE (n{$nodeId}:{$label}";
        if ($propsStr) {
            $cypher .= " {{$propsStr}}";
        }
        $cypher .= ")";
        
        // 添加注释
        if ($includeComments) {
            $cypher .= "; // 节点ID: {$nodeId}";
        }
        
        return $cypher;
    }
    
    /**
     * 格式化边CREATE语句
     * 
     * @param int $fromId 源节点ID
     * @param int $toId 目标节点ID
     * @param array $properties 边属性
     * @param array $options 格式化选项
     * @return string Cypher CREATE语句
     */
    private function formatEdgeCreate(int $fromId, int $toId, array $properties, array $options = []): string
    {
        $includeComments = $options['include_comments'] ?? true;
        $defaultRelType = $options['default_relationship_type'] ?? 'CONNECTED_TO';
        
        // 处理关系类型
        $relType = $properties['type'] ?? $properties['__type'] ?? $defaultRelType;
        unset($properties['type'], $properties['__type']);
        
        // 构建属性字符串
        $propsStr = $this->formatProperties($properties);
        
        $cypher = "MATCH (from {{__id: {$fromId}}}), (to {{__id: {$toId}}})";
        $cypher .= "\nCREATE (from)-[r:{$relType}";
        if ($propsStr) {
            $cypher .= " {{$propsStr}}";
        }
        $cypher .= "]->(to)";
        
        // 添加注释
        if ($includeComments) {
            $cypher .= "; // 边: {$fromId} -> {$toId}";
        }
        
        return $cypher;
    }
    
    /**
     * 格式化属性为Cypher属性字符串
     * 
     * @param array $properties 属性数组
     * @return string 格式化的属性字符串
     */
    private function formatProperties(array $properties): string
    {
        if (empty($properties)) {
            return '';
        }
        
        $props = [];
        foreach ($properties as $key => $value) {
            $escapedKey = $this->escapeIdentifier($key);
            $escapedValue = $this->escapeValue($value);
            $props[] = "{$escapedKey}: {$escapedValue}";
        }
        
        return implode(', ', $props);
    }
    
    /**
     * 转义Cypher标识符
     * 
     * @param string $identifier 标识符
     * @return string 转义后的标识符
     */
    private function escapeIdentifier(string $identifier): string
    {
        // 如果包含特殊字符，用反引号包围
        if (preg_match('/[^a-zA-Z0-9_]/', $identifier)) {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }
        return $identifier;
    }
    
    /**
     * 转义Cypher值
     * 
     * @param mixed $value 值
     * @return string 转义后的值
     */
    private function escapeValue($value): string
    {
        if ($value === null) {
            return 'null';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        if (is_array($value)) {
            $items = array_map([$this, 'escapeValue'], $value);
            return '[' . implode(', ', $items) . ']';
        }
        
        // 字符串转义
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            (string)$value
        );
        
        return '"' . $escaped . '"';
    }
    
    /**
     * 生成文件头注释
     * 
     * @param array $options 选项
     * @return string 头部注释
     */
    private function generateHeader(array $options = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $stats = $this->graph->getStats();
        
        return "// ====================================================================
// GraphRedis Cypher Export
// Generated: {$timestamp}
// Nodes: {$stats['nodes']}, Edges: {$stats['edges']}
// Memory Usage: {$stats['memory_usage']}
// ====================================================================

";
    }
    
    /**
     * 生成文件尾注释
     * 
     * @return string 尾部注释
     */
    private function generateFooter(): string
    {
        $stats = $this->exportStats;
        $exportTime = round($stats['export_time'] ?? 0, 4);
        $nodeCount = $stats['nodes_exported'] ?? 0;
        $edgeCount = $stats['edges_exported'] ?? 0;
        
        return "\n// ====================================================================
// Export completed successfully
// Exported Nodes: {$nodeCount}
// Exported Edges: {$edgeCount}
// Export Time: {$exportTime}s
// ====================================================================";
    }
    
    /**
     * 获取导出统计信息
     * 
     * @return array 统计信息
     */
    public function getExportStats(): array
    {
        return $this->exportStats;
    }
}