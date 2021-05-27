<?php

/**
 * MySQL database handle
 * 
 * @author Igor Horta
 * @see https://github.com/iguin/pdo-database 
 */

class Database
{
  private const DB_HOST           = 'localhost';
  private const DB_NAME           = 'gc_database';
  private const DB_CHARSET        = 'utf8';
  private const DB_USERNAME       = 'root';
  private const DB_PASSWORD       = '';
  private const PAGINATION_LIMIT  = 3;

  /** @var \PDO $conn */
  private $conn;

  /** @var \PDOStatement  */
  private $stmt;

  /** @var string $table the current table that will be used  */
  private $table;

  /** @var string $query the current query */
  private $query;

  /** @var array $parameters the query parameters */
  private $parameters;

  /** @var bool $returnResults */
  private $returnResults = false;

  /** @var array $whereClausuleMode the query where clausule mode */
  private $whereClausuleMode;

  /** @var array $whereClausuleParameters the query where clausule parameters */
  private $whereClausuleParameters;

  /** @var array $limitClausule */
  private $limitClausule;

  /** @var array $orderByClausule */
  private $orderByClausule;

  /** @var bool $queryStatus the query status */
  private $queryStatus;

  /** @var string $errorInfo the query error info */
  private $errorInfo;

  /** @var object $paginationInfos */
  private $paginationInfos;

  public function setTable(string $table): \Database
  {
    $this->table = $table;
    return $this;
  }

  public function getQueryStatus(): bool
  {
    return isset($this->queryStatus) ? $this->queryStatus : false;
  }

  public function getErrorInfo(): ?string
  {
    return isset($errorInfo) ? $this->errorInfo : null;
  }

  private function setPaginationInfo($key, $value): void
  {
    if (!isset($this->paginationInfos))
      $this->paginationInfos = new \stdClass;

    $this->paginationInfos->{$key} = $value;
  }

  private function setQuery(string $query): void
  {
    $this->query = $query;
  }

  private function getQuery(): string
  {
    $this->handleWhere();
    $this->handleOrderBy();
    $this->handleLimit();

    return $this->query;
  }

  /** Build a where clausule and return the code */
  private function buildWhereClausule(): ?string
  {
    if (isset($this->whereClausuleParameters) === false)
      return null;

    $mode = $this->whereClausuleMode;
    $params = $this->whereClausuleParameters;
    $where_items = array();

    foreach ($params as $item) {
      $relation = $item['relation'];
      $field = $item['field'];
      $value = $item['value'];

      $field_id = ':' . uniqid("{$field}_");

      $this->parameters[$field_id] = $value;

      $str = mb_strtolower($relation) === 'like'
        ? "{$field} {$relation} CONCAT('%', {$field_id}, '%')"
        : "{$field} {$relation} {$field_id}";

      array_push($where_items, $str);
    }

    $where = join(" {$mode} ", $where_items);

    return " WHERE {$where}";
  }

  /** Handle where clausule */
  private function handleWhere(): void
  {
    $where = $this->buildWhereClausule();

    if (is_null($where) === true)
      return;

    $this->query .= $where;
  }

  /** Handle limit clausule */
  private function handleLimit(): void
  {
    if (isset($this->limitClausule) === false)
      return;

    $limit = $this->limitClausule[0];

    // verify if the limit start was defined
    if (is_null($this->limitClausule[1]) === false) {
      $limit = "{$this->limitClausule[1]}, {$this->limitClausule[0]}";
    }

    $this->query .= " LIMIT {$limit}";
  }

  /** Handle limit clausule */
  private function handleOrderBy(): void
  {
    if (isset($this->orderByClausule) === false)
      return;

    $this->query .= " ORDER BY " . join(" ", $this->orderByClausule);
  }

  private function bindValues(): void
  {
    if (!isset($this->parameters))
      return;

    foreach ($this->parameters as $key => $value) {
      $this->stmt->bindValue($key, $value);
    }
  }

  /**  Try to get the database connection */
  private function getConn(): \PDO
  {
    try {
      $dsn = 'mysql:host=' . self::DB_HOST . ';dbname=' . self::DB_NAME . ';charset=' . self::DB_CHARSET;
      $conn = new \PDO($dsn, self::DB_USERNAME, self::DB_PASSWORD);
      $this->conn = $conn;
      return $conn;
    } catch (\PDOException $e) {
      throw new \Exception("Database connection error\n{$e->getMessage()}");
    }
  }

  /** Build a select query */
  public function select(array $fields): \Database
  {
    $query = "SELECT " . join(', ', $fields) . " FROM {$this->table}";
    $this->setQuery($query);
    $this->returnResults = true;

    return $this;
  }

  /** Build a insert query */
  public function insert(array $fields): \Database
  {
    $values = array();

    foreach ($fields as $column => $value) {
      $columnId = ':' . uniqid("{$column}_");

      $this->parameters[$columnId] = $value;
      array_push($values, $columnId);
    }

    $columns = join(', ', array_keys($fields));
    $values = join(', ', $values);

    $this->query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";

    return $this;
  }

  /** Build a update query */
  public function update(array $fields): \Database
  {
    $columns = array();

    foreach ($fields as $column => $value) {
      $columnId = ':' . uniqid("{$column}_");

      $this->parameters[$columnId] = $value;

      array_push($columns, "{$column} = {$columnId}");
    }

    $columns = join(', ', $columns);

    $this->query = "UPDATE {$this->table} SET {$columns}";

    return $this;
  }

  /** Define the where clausule parameters */
  public function where(string $mode, ...$whereParameters): \Database
  {
    $this->whereClausuleMode = $mode;
    $this->whereClausuleParameters = $whereParameters;
    return $this;
  }

  /** Define the query limit */
  public function limit(int $limit, ?int $start = null): \Database
  {
    $this->limitClausule = array($limit, $start);
    return $this;
  }

  /** Get the total rows  */
  private function getTotalRows(): int
  {
    $where = $this->buildWhereClausule();

    $query = "SELECT COUNT(`id`) as total FROM {$this->table}";

    if (is_null($where) === false) {
      $query .= $where;
    }

    $result = $this->loadRawQuery($query);

    return (count($result) === 0) ? 0 : intval($result[0]->total);
  }

  public function pagination(int $page): \Database
  {
    $realPage = $page <= 1 ? 0 : --$page;

    $this->setPaginationInfo('total_rows', $this->getTotalRows());
    $this->setPaginationInfo('total_pages', ceil($this->getTotalRows() / self::PAGINATION_LIMIT));

    $start = $realPage * self::PAGINATION_LIMIT;

    $this->limit(self::PAGINATION_LIMIT, $start);

    return $this;
  }

  /** Define the query order by clausule parameters */
  public function order_by(string $fields, string $mode): \Database
  {
    $this->orderByClausule = array($fields, $mode);
    return $this;
  }

  /** Load a raw query */
  private function loadRawQuery(string $query, ?int $mode = null, ?array $options = null)
  {
    $this->stmt = $this->getConn()->prepare($query);
    $this->bindValues();

    unset($this->parameters);

    $status = $this->stmt->execute();

    $this->queryStatus = $status;

    if ($status === false) {
      $this->errorInfo = $this->stmt->errorInfo();
    }

    // get mode
    $mode = $mode ?? \PDO::FETCH_CLASS;

    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetchAll($mode, ...$options);

    return $result;
  }

  private function buildFetch(): void
  {
    $query = $this->getQuery();

    $this->stmt = $this->getConn()->prepare($query);
    $this->bindValues();
    $status = $this->stmt->execute();

    $this->queryStatus = $status;

    if ($status === false) {
      $this->errorInfo = $this->stmt->errorInfo();
    }
  }

  public function load(?int $mode = null, ?array $options = null)
  {
    $this->buildFetch();

    // get mode
    $mode = $mode ?? \PDO::FETCH_CLASS;
    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetchAll($mode, ...$options);

    return $this->returnResults === true
      ? $result
      : $this->queryStatus;
  }

  public function loadSingle(?int $mode = null, ?array $options = null)
  {
    $this->buildFetch();

    // get mode
    $mode = $mode ?? \PDO::FETCH_ASSOC;

    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetch($mode);

    return $this->returnResults === true
      ? $result
      : $this->queryStatus;
  }
}
