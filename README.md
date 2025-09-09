# GraphRedis

A Graph Database with Redis!

GraphRedis is a PHP library that provides graph database functionality using Redis as the storage backend. It supports nodes, edges, and basic graph operations with a simple and intuitive API.

## Installation

Install GraphRedis using Composer:

```bash
composer require linkerlin/graph-redis
```

## Requirements

- PHP 7.4 or higher
- Redis extension for PHP
- Redis server

## Usage

### Basic Example

```php
<?php

require_once 'vendor/autoload.php';

use GraphRedis\GraphRedis;

// Create a new GraphRedis instance
$graph = new GraphRedis();

// Connect to Redis (optional, uses default localhost:6379)
$graph->connect('127.0.0.1', 6379);

// Add nodes
$graph->addNode('user1', ['name' => 'John Doe', 'age' => 30]);
$graph->addNode('user2', ['name' => 'Jane Smith', 'age' => 25]);

// Add edge between nodes
$graph->addEdge('user1', 'user2', 'FRIENDS_WITH', ['since' => '2020-01-01']);

// Get node data
$user1 = $graph->getNode('user1');
print_r($user1);

// Get outgoing edges
$edges = $graph->getOutgoingEdges('user1');
print_r($edges);

// Close connection
$graph->close();
```

### Advanced Usage

#### Custom Redis Instance

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$graph = new GraphRedis($redis, 'my_graph:');
```

#### Working with Relationships

```php
// Add different types of relationships
$graph->addEdge('user1', 'company1', 'WORKS_AT', ['position' => 'Developer']);
$graph->addEdge('user2', 'company1', 'WORKS_AT', ['position' => 'Manager']);

// Get incoming relationships
$incoming = $graph->getIncomingEdges('company1');
```

## API Reference

### GraphRedis Class

#### Constructor

```php
new GraphRedis($redis = null, $keyPrefix = 'graph:')
```

- `$redis`: Optional Redis instance. If not provided, a new Redis instance will be created.
- `$keyPrefix`: Key prefix for Redis keys (default: 'graph:')

#### Methods

##### Connection

- `connect($host = '127.0.0.1', $port = 6379, $timeout = 0.0)`: Connect to Redis server
- `close()`: Close Redis connection

##### Node Operations

- `addNode($nodeId, array $properties = [])`: Add a node to the graph
- `getNode($nodeId)`: Get node properties
- `removeNode($nodeId)`: Remove a node and all its edges

##### Edge Operations

- `addEdge($fromNodeId, $toNodeId, $relationship = 'CONNECTED_TO', array $properties = [])`: Add an edge between nodes
- `getOutgoingEdges($nodeId)`: Get outgoing edges for a node
- `getIncomingEdges($nodeId)`: Get incoming edges for a node

## Testing

Run tests using PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
