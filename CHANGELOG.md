# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2023-12-09

### Added
- Initial release of GraphRedis
- Core graph database functionality with Redis backend
- Node management (create, read, update, delete)
- Edge management with weights and properties
- Graph traversal algorithms (DFS, BFS shortest path)
- Composer package support with PSR-4 autoloading
- Comprehensive unit tests with PHPUnit
- Demo examples and documentation
- Chinese documentation (README_CN.md)

### Features
- **Node Operations**: Add, get, update, delete nodes with custom properties
- **Edge Operations**: Create directed edges with weights and properties
- **Graph Algorithms**: BFS shortest path and DFS traversal
- **Pagination**: Support for paginated neighbor queries
- **Statistics**: Graph statistics and memory usage reporting
- **Error Handling**: Proper exception handling for Redis connection issues

### Development Tools
- PHPUnit test suite with 13 test cases
- PHPStan static analysis support
- PHP_CodeSniffer for code style checking
- Composer scripts for common tasks
- Comprehensive examples and documentation

### Requirements
- PHP 7.4+
- Redis server
- PHP Redis extension