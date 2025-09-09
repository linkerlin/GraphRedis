<?php

namespace GraphRedis;

use Redis;
use Exception;

/**
 * GraphRedis - A Graph Database with Redis!
 * 
 * This class provides graph database functionality using Redis as the storage backend.
 * It supports nodes, edges, and basic graph operations.
 */
class GraphRedis
{
    /** @var Redis */
    private $redis;
    
    /** @var string */
    private $keyPrefix;
    
    /**
     * Constructor
     * 
     * @param Redis|null $redis Redis instance (optional, will create new one if not provided)
     * @param string $keyPrefix Key prefix for Redis keys
     */
    public function __construct($redis = null, $keyPrefix = 'graph:')
    {
        $this->redis = $redis ?: new Redis();
        $this->keyPrefix = $keyPrefix;
    }
    
    /**
     * Connect to Redis server
     * 
     * @param string $host Redis host
     * @param int $port Redis port
     * @param float $timeout Connection timeout
     * @return bool True on success
     * @throws Exception If connection fails
     */
    public function connect($host = '127.0.0.1', $port = 6379, $timeout = 0.0)
    {
        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new Exception("Failed to connect to Redis server at {$host}:{$port}");
        }
        
        return true;
    }
    
    /**
     * Add a node to the graph
     * 
     * @param string $nodeId Unique node identifier
     * @param array $properties Node properties
     * @return bool True on success
     */
    public function addNode($nodeId, array $properties = [])
    {
        $nodeKey = $this->keyPrefix . 'nodes:' . $nodeId;
        return $this->redis->hMSet($nodeKey, $properties);
    }
    
    /**
     * Get a node from the graph
     * 
     * @param string $nodeId Node identifier
     * @return array|null Node properties or null if not found
     */
    public function getNode($nodeId)
    {
        $nodeKey = $this->keyPrefix . 'nodes:' . $nodeId;
        $node = $this->redis->hGetAll($nodeKey);
        
        return empty($node) ? null : $node;
    }
    
    /**
     * Add an edge between two nodes
     * 
     * @param string $fromNodeId Source node ID
     * @param string $toNodeId Target node ID
     * @param string $relationship Relationship type
     * @param array $properties Edge properties
     * @return bool True on success
     */
    public function addEdge($fromNodeId, $toNodeId, $relationship = 'CONNECTED_TO', array $properties = [])
    {
        // Add edge to outgoing edges of source node
        $outgoingKey = $this->keyPrefix . 'edges:out:' . $fromNodeId;
        $edgeData = [
            'to' => $toNodeId,
            'relationship' => $relationship,
            'properties' => json_encode($properties)
        ];
        
        // Add edge to incoming edges of target node
        $incomingKey = $this->keyPrefix . 'edges:in:' . $toNodeId;
        $reverseEdgeData = [
            'from' => $fromNodeId,
            'relationship' => $relationship,
            'properties' => json_encode($properties)
        ];
        
        $edgeId = $fromNodeId . ':' . $toNodeId . ':' . $relationship;
        
        return $this->redis->hMSet($outgoingKey, [$edgeId => json_encode($edgeData)]) &&
               $this->redis->hMSet($incomingKey, [$edgeId => json_encode($reverseEdgeData)]);
    }
    
    /**
     * Get outgoing edges for a node
     * 
     * @param string $nodeId Node identifier
     * @return array Array of outgoing edges
     */
    public function getOutgoingEdges($nodeId)
    {
        $outgoingKey = $this->keyPrefix . 'edges:out:' . $nodeId;
        $edges = $this->redis->hGetAll($outgoingKey);
        
        $result = [];
        foreach ($edges as $edgeId => $edgeDataJson) {
            $edgeData = json_decode($edgeDataJson, true);
            $result[] = [
                'id' => $edgeId,
                'to' => $edgeData['to'],
                'relationship' => $edgeData['relationship'],
                'properties' => json_decode($edgeData['properties'], true)
            ];
        }
        
        return $result;
    }
    
    /**
     * Get incoming edges for a node
     * 
     * @param string $nodeId Node identifier
     * @return array Array of incoming edges
     */
    public function getIncomingEdges($nodeId)
    {
        $incomingKey = $this->keyPrefix . 'edges:in:' . $nodeId;
        $edges = $this->redis->hGetAll($incomingKey);
        
        $result = [];
        foreach ($edges as $edgeId => $edgeDataJson) {
            $edgeData = json_decode($edgeDataJson, true);
            $result[] = [
                'id' => $edgeId,
                'from' => $edgeData['from'],
                'relationship' => $edgeData['relationship'],
                'properties' => json_decode($edgeData['properties'], true)
            ];
        }
        
        return $result;
    }
    
    /**
     * Remove a node and all its edges
     * 
     * @param string $nodeId Node identifier
     * @return bool True on success
     */
    public function removeNode($nodeId)
    {
        $nodeKey = $this->keyPrefix . 'nodes:' . $nodeId;
        $outgoingKey = $this->keyPrefix . 'edges:out:' . $nodeId;
        $incomingKey = $this->keyPrefix . 'edges:in:' . $nodeId;
        
        // Remove the node itself
        $this->redis->del($nodeKey);
        
        // Remove all outgoing and incoming edges
        $this->redis->del($outgoingKey);
        $this->redis->del($incomingKey);
        
        return true;
    }
    
    /**
     * Close the Redis connection
     */
    public function close()
    {
        $this->redis->close();
    }
}