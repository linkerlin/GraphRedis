// ====================================================================
// GraphRedis Cypher Export
// Generated: 2025-10-09 16:31:50
// Nodes: 6, Edges: 6
// Memory Usage: 1.82M
// ====================================================================


// ==================== 节点定义 ====================
CREATE (n1:Employee {name: "Alice", role: "Developer", __id: 1}); // 节点ID: 1
CREATE (n2:Employee {name: "Bob", role: "Designer", __id: 2}); // 节点ID: 2
CREATE (n3:Employee {name: "Charlie", role: "Manager", __id: 3}); // 节点ID: 3
CREATE (n4:Person {name: "Alice", age: 30, city: "Beijing", __id: 4}); // 节点ID: 4
CREATE (n5:Person {name: "Bob", age: 25, city: "Shanghai", __id: 5}); // 节点ID: 5
CREATE (n6:Person {name: "Charlie", age: 35, city: "Guangzhou", __id: 6}); // 节点ID: 6

// ==================== 关系定义 ====================
MATCH (from {{__id: 5}}), (to {{__id: 6}})
CREATE (from)-[r:CONNECTED_TO {relation: "colleague", since: 2021, weight: 1}]->(to); // 边: 5 -> 6
MATCH (from {{__id: 2}}), (to {{__id: 3}})
CREATE (from)-[r:REPORTS_TO {since: "2023-01-01", weight: 0.7}]->(to); // 边: 2 -> 3
MATCH (from {{__id: 4}}), (to {{__id: 5}})
CREATE (from)-[r:CONNECTED_TO {relation: "friend", since: 2020, weight: 1}]->(to); // 边: 4 -> 5
MATCH (from {{__id: 4}}), (to {{__id: 6}})
CREATE (from)-[r:CONNECTED_TO {relation: "friend", since: 2019, weight: 1}]->(to); // 边: 4 -> 6
MATCH (from {{__id: 1}}), (to {{__id: 3}})
CREATE (from)-[r:REPORTS_TO {since: "2022-06-15", weight: 0.8}]->(to); // 边: 1 -> 3
MATCH (from {{__id: 1}}), (to {{__id: 2}})
CREATE (from)-[r:COLLABORATES_WITH {project: "WebApp", weight: 0.9}]->(to); // 边: 1 -> 2

// ====================================================================
// Export completed successfully
// Exported Nodes: 6
// Exported Edges: 6
// Export Time: 0.0017s
// ====================================================================