# GraphRedis

A lightweight graph database implementation using Redis as storage backend.
[【中文介绍】](README_CN.md)

## Installation

### Via Composer

```bash
composer require graphredis/graphredis
```

### Manual Installation

```bash
git clone https://github.com/yourname/GraphRedis.git
cd GraphRedis
composer install
```

## Requirements

- PHP 7.4+
- Redis server
- PHP Redis extension

## Quick Start

```php
require_once 'vendor/autoload.php';

use GraphRedis\GraphRedis;

// Create connection
$graph = new GraphRedis();

// Add nodes
$bob = $graph->addNode(['name' => 'Bob', 'age' => 32]);
$alice = $graph->addNode(['name' => 'Alice', 'age' => 28]);

// Add edge
$graph->addEdge($bob, $alice, 1.0, ['type' => 'friend']);

// Query neighbors
$friends = $graph->neighbors($bob);

// Find shortest path
$path = $graph->shortestPath($bob, $alice);
```

## Usage

### Run Demo

```bash
composer run demo
```

### Run Tests

```bash
composer run test
```

### Code Quality

```bash
# Static analysis
composer run phpstan

# Code style check
composer run cs-check

# Code style fix
composer run cs-fix
```

## Documentation

For detailed documentation in Chinese, see [README_CN.md](README_CN.md).

## License

Apache License - see [LICENSE](LICENSE) file for details.
[【中文介绍】](README_CN.md)
![ae55f8b030c0aa87b4c4303f8a02db7d.png](https://s2.loli.net/2025/09/09/q69j14Xr3NOMelV.png)