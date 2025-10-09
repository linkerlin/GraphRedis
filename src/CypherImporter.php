<?php

namespace GraphRedis;

/**
 * Cypher数据导入器 - 从Cypher格式文件导入数据到GraphRedis
 * 
 * 支持解析标准Cypher语句并转换为GraphRedis操作
 * 包含完整的词法分析、语法解析和错误处理
 * 
 * @package GraphRedis
 * @author GraphRedis Team
 * @license MIT
 */
class CypherImporter
{
    private GraphRedis $graph;
    private array $nodeMapping = []; // 临时ID到实际ID的映射
    private array $importStats = [];
    private array $errors = [];
    
    /**
     * CypherImporter constructor
     * 
     * @param GraphRedis $graph GraphRedis实例
     */
    public function __construct(GraphRedis $graph)
    {
        $this->graph = $graph;
    }
    
    /**
     * 从Cypher文件导入数据
     * 
     * @param string $filePath Cypher文件路径
     * @param array $options 导入选项
     * @return array 导入统计信息
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function importFromFile(string $filePath, array $options = []): array
    {
        // 验证文件
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("文件不存在: {$filePath}");
        }
        
        if (!str_ends_with(strtolower($filePath), '.cypher')) {
            throw new \InvalidArgumentException("文件必须使用.cypher扩展名");
        }
        
        // 读取文件内容
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("无法读取文件: {$filePath}");
        }
        
        return $this->importFromString($content, $options);
    }
    
    /**
     * 从Cypher字符串导入数据
     * 
     * @param string $cypherContent Cypher内容
     * @param array $options 导入选项
     * @return array 导入统计信息
     */
    public function importFromString(string $cypherContent, array $options = []): array
    {
        $startTime = microtime(true);
        $this->resetState();
        
        try {
            // 预处理内容
            $statements = $this->preprocessContent($cypherContent);
            
            // 执行导入
            $this->executeStatements($statements, $options);
            
            $this->importStats['import_time'] = microtime(true) - $startTime;
            $this->importStats['success'] = true;
            
        } catch (\Exception $e) {
            $this->importStats['import_time'] = microtime(true) - $startTime;
            $this->importStats['success'] = false;
            $this->errors[] = $e->getMessage();
            
            if ($options['throw_on_error'] ?? true) {
                throw $e;
            }
        }
        
        return $this->importStats;
    }
    
    /**
     * 重置导入状态
     */
    private function resetState(): void
    {
        $this->nodeMapping = [];
        $this->importStats = [
            'nodes_created' => 0,
            'edges_created' => 0,
            'statements_processed' => 0,
            'errors' => 0
        ];
        $this->errors = [];
    }
    
    /**
     * 预处理Cypher内容
     * 
     * @param string $content 原始内容
     * @return array 处理后的语句数组
     */
    private function preprocessContent(string $content): array
    {
        // 移除注释
        $content = preg_replace('/\/\/.*$/m', '', $content);
        
        // 移除多行注释
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        
        // 处理多行语句：将MATCH...CREATE合并为单个语句
        $content = preg_replace('/\n(?=CREATE\s+\(\w+\)-\[)/m', ' ', $content);
        
        // 分割语句（以分号分割）
        $statements = preg_split('/;\s*/', $content);
        
        // 过滤空语句
        $statements = array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
        
        return array_map('trim', $statements);
    }
    
    /**
     * 执行Cypher语句
     * 
     * @param array $statements 语句数组
     * @param array $options 选项
     */
    private function executeStatements(array $statements, array $options = []): void
    {
        $continueOnError = $options['continue_on_error'] ?? false;
        
        foreach ($statements as $index => $statement) {
            try {
                $this->executeStatement($statement);
                $this->importStats['statements_processed']++;
                
            } catch (\Exception $e) {
                $this->importStats['errors']++;
                $this->errors[] = "语句 {$index}: {$e->getMessage()}";
                
                if (!$continueOnError) {
                    throw $e;
                }
            }
        }
    }
    
    /**
     * 执行单个Cypher语句
     * 
     * @param string $statement Cypher语句
     * @throws \RuntimeException
     */
    private function executeStatement(string $statement): void
    {
        $statement = trim($statement);
        if (empty($statement)) {
            return;
        }
        
        // 判断语句类型并执行
        if (preg_match('/^CREATE\s+\(/i', $statement)) {
            $this->executeCreateNode($statement);
        } elseif (preg_match('/^MATCH.*CREATE.*-\[/is', $statement)) {
            $this->executeCreateEdge($statement);
        } else {
            throw new \RuntimeException("不支持的Cypher语句: " . substr($statement, 0, 50));
        }
    }
    
    /**
     * 执行CREATE节点语句
     * 
     * @param string $statement CREATE语句
     * @throws \RuntimeException
     */
    private function executeCreateNode(string $statement): void
    {
        // 解析CREATE (var:Label {props}) 格式
        $pattern = '/CREATE\s+\(\s*(\w+):(\w+)\s*(?:\{([^}]*)\})?\s*\)/i';
        
        if (!preg_match($pattern, $statement, $matches)) {
            throw new \RuntimeException("无法解析CREATE节点语句: {$statement}");
        }
        
        $varName = $matches[1];
        $label = $matches[2];
        $propsStr = $matches[3] ?? '';
        
        // 解析属性
        $properties = $this->parseProperties($propsStr);
        
        // 处理内部ID映射
        $originalId = null;
        if (isset($properties['__id'])) {
            $originalId = $properties['__id'];
            unset($properties['__id']);
        }
        
        // 添加标签作为属性
        $properties['__label'] = $label;
        
        // 创建节点
        $nodeId = $this->graph->addNode($properties);
        
        // 建立ID映射
        if ($originalId !== null) {
            $this->nodeMapping[$originalId] = $nodeId;
        }
        $this->nodeMapping[$varName] = $nodeId;
        
        $this->importStats['nodes_created']++;
    }
    
    /**
     * 执行CREATE边语句
     * 
     * @param string $statement MATCH + CREATE语句
     * @throws \RuntimeException
     */
    private function executeCreateEdge(string $statement): void
    {
        // 先尝试直接解析单行语句
        if (preg_match('/MATCH.*CREATE/is', $statement)) {
            // 分离 MATCH 和 CREATE 部分
            if (preg_match('/(MATCH.*?)\s+(CREATE.*)/is', $statement, $matches)) {
                $matchPart = trim($matches[1]);
                $createPart = trim($matches[2]);
                
                // 解析MATCH语句获取节点ID
                $nodeIds = $this->parseMatchStatement($matchPart);
                
                // 解析CREATE语句获取关系信息
                $edgeInfo = $this->parseCreateEdgeStatement($createPart);
                
                // 创建边
                $fromId = $nodeIds[$edgeInfo['from_var']];
                $toId = $nodeIds[$edgeInfo['to_var']];
                $weight = $edgeInfo['properties']['weight'] ?? 1.0;
                
                // 移除weight属性，单独处理
                unset($edgeInfo['properties']['weight']);
                
                // 添加关系类型
                $edgeInfo['properties']['__type'] = $edgeInfo['rel_type'];
                
                $this->graph->addEdge($fromId, $toId, $weight, $edgeInfo['properties']);
                
                $this->importStats['edges_created']++;
                return;
            }
        }
        
        throw new \RuntimeException("无法解析边语句: {$statement}");
    }
    
    /**
     * 解析MATCH语句
     * 
     * @param string $matchStatement MATCH语句
     * @return array 变量到节点ID的映射
     * @throws \RuntimeException
     */
    private function parseMatchStatement(string $matchStatement): array
    {
        // MATCH (from {__id: 1}), (to {__id: 2})
        $pattern = '/MATCH\s+\(\s*(\w+)\s*\{\{?__id:\s*(\d+)\}?\}\s*\),\s*\(\s*(\w+)\s*\{\{?__id:\s*(\d+)\}?\}\s*\)/i';
        
        if (!preg_match($pattern, $matchStatement, $matches)) {
            throw new \RuntimeException("无法解析MATCH语句: {$matchStatement}");
        }
        
        $fromVar = $matches[1];
        $fromOriginalId = (int)$matches[2];
        $toVar = $matches[3];
        $toOriginalId = (int)$matches[4];
        
        // 查找实际的节点ID
        $fromId = $this->nodeMapping[$fromOriginalId] ?? null;
        $toId = $this->nodeMapping[$toOriginalId] ?? null;
        
        if ($fromId === null || $toId === null) {
            throw new \RuntimeException("找不到节点ID映射: from={$fromOriginalId}, to={$toOriginalId}");
        }
        
        return [
            $fromVar => $fromId,
            $toVar => $toId
        ];
    }
    
    /**
     * 解析CREATE边语句
     * 
     * @param string $createStatement CREATE边语句
     * @return array 边信息
     * @throws \RuntimeException
     */
    private function parseCreateEdgeStatement(string $createStatement): array
    {
        // CREATE (from)-[r:REL_TYPE {props}]->(to)
        $pattern = '/CREATE\s+\(\s*(\w+)\s*\)-\[\s*\w*:(\w+)\s*(?:\{\{?([^}]*)\}?\})?\s*\]->\(\s*(\w+)\s*\)/i';
        
        if (!preg_match($pattern, $createStatement, $matches)) {
            throw new \RuntimeException("无法解析CREATE边语句: {$createStatement}");
        }
        
        $fromVar = $matches[1];
        $relType = $matches[2];
        $propsStr = $matches[3] ?? '';
        $toVar = $matches[4];
        
        return [
            'from_var' => $fromVar,
            'to_var' => $toVar,
            'rel_type' => $relType,
            'properties' => $this->parseProperties($propsStr)
        ];
    }
    
    /**
     * 解析属性字符串
     * 
     * @param string $propsStr 属性字符串
     * @return array 属性数组
     */
    private function parseProperties(string $propsStr): array
    {
        $properties = [];
        
        if (empty(trim($propsStr))) {
            return $properties;
        }
        
        // 简单的属性解析（支持基本类型）
        $pairs = $this->splitPropertyPairs($propsStr);
        
        foreach ($pairs as $pair) {
            if (preg_match('/^\s*`?([^`:]+)`?\s*:\s*(.+)\s*$/', $pair, $matches)) {
                $key = trim($matches[1]);
                $value = $this->parsePropertyValue(trim($matches[2]));
                $properties[$key] = $value;
            }
        }
        
        return $properties;
    }
    
    /**
     * 分割属性对
     * 
     * @param string $propsStr 属性字符串
     * @return array 属性对数组
     */
    private function splitPropertyPairs(string $propsStr): array
    {
        $pairs = [];
        $current = '';
        $inString = false;
        $depth = 0;
        
        for ($i = 0; $i < strlen($propsStr); $i++) {
            $char = $propsStr[$i];
            
            if ($char === '"' && ($i === 0 || $propsStr[$i-1] !== '\\')) {
                $inString = !$inString;
            }
            
            if (!$inString) {
                if ($char === '[') {
                    $depth++;
                } elseif ($char === ']') {
                    $depth--;
                } elseif ($char === ',' && $depth === 0) {
                    $pairs[] = $current;
                    $current = '';
                    continue;
                }
            }
            
            $current .= $char;
        }
        
        if (!empty($current)) {
            $pairs[] = $current;
        }
        
        return $pairs;
    }
    
    /**
     * 解析属性值
     * 
     * @param string $valueStr 值字符串
     * @return mixed 解析后的值
     */
    private function parsePropertyValue(string $valueStr): mixed
    {
        $valueStr = trim($valueStr);
        
        // null值
        if ($valueStr === 'null') {
            return null;
        }
        
        // 布尔值
        if ($valueStr === 'true') {
            return true;
        }
        if ($valueStr === 'false') {
            return false;
        }
        
        // 数字
        if (is_numeric($valueStr)) {
            return strpos($valueStr, '.') !== false ? (float)$valueStr : (int)$valueStr;
        }
        
        // 数组
        if (preg_match('/^\[(.+)\]$/', $valueStr, $matches)) {
            $items = $this->splitPropertyPairs($matches[1]);
            return array_map([$this, 'parsePropertyValue'], $items);
        }
        
        // 字符串（移除引号）
        if (preg_match('/^"(.*)"/s', $valueStr, $matches)) {
            return $this->unescapeString($matches[1]);
        }
        
        return $valueStr;
    }
    
    /**
     * 反转义字符串
     * 
     * @param string $str 转义的字符串
     * @return string 原始字符串
     */
    private function unescapeString(string $str): string
    {
        return str_replace(
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            ['\\', '"', "\n", "\r", "\t"],
            $str
        );
    }
    
    /**
     * 验证导入数据
     * 
     * @return array 验证结果
     */
    public function validateImport(): array
    {
        $stats = $this->graph->getStats();
        
        return [
            'nodes_in_graph' => $stats['nodes'],
            'edges_in_graph' => $stats['edges'],
            'nodes_created' => $this->importStats['nodes_created'],
            'edges_created' => $this->importStats['edges_created'],
            'node_mapping_count' => count($this->nodeMapping),
            'errors' => $this->errors
        ];
    }
    
    /**
     * 获取导入统计信息
     * 
     * @return array 统计信息
     */
    public function getImportStats(): array
    {
        return $this->importStats;
    }
    
    /**
     * 获取错误信息
     * 
     * @return array 错误列表
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * 获取节点ID映射
     * 
     * @return array ID映射
     */
    public function getNodeMapping(): array
    {
        return $this->nodeMapping;
    }
}