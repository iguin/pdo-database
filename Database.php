<?php

use function PHPSTORM_META\type;

/**
 * 
 * MySQL database handle
 * 
 */

class Database
{
  private const DB_HOST     = 'localhost';
  private const DB_NAME     = 'gc_database';
  private const DB_CHARSET  = 'utf8';
  private const DB_USERNAME = 'root';
  private const DB_PASSWORD = '';

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

  /** @var bool $return_results */
  private $return_results = false;

  /** @var array $where_clausule_mode the query where clausule mode */
  private $where_clausule_mode;

  /** @var array $where_clausule_parameters the query where clausule parameters */
  private $where_clausule_parameters;

  /** @var array $limit_clausule */
  private $limit_clausule;

  /** @var array $order_by_clausule */
  private $order_by_clausule;

  /** @var bool $query_status the query status */
  private $query_status;

  /** @var string $error_info the query error info */
  private $error_info;

  public function set_table(string $table): \Database
  {
    $this->table = $table;
    return $this;
  }

  public function get_query_status(): bool
  {
    return isset($this->query_status) ? $this->query_status : false;
  }

  public function get_error_info(): ?string
  {
    return isset($error_info) ? $this->error_info : null;
  }

  private function set_query(string $query): void
  {
    $this->query = $query;
  }

  private function get_query(): string
  {
    $this->handle_where();
    $this->handle_order_by();
    $this->handle_limit();

    return $this->query;
  }

  /** Handle where clausule */
  private function handle_where(): void
  {
    if (isset($this->where_clausule_parameters) === false)
      return;

    $mode = $this->where_clausule_mode;
    $params = $this->where_clausule_parameters;
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

    $this->query .= " WHERE {$where}";
  }

  /** Handle limit clausule */
  private function handle_limit(): void
  {
    if (isset($this->limit_clausule) === false)
      return;

    $limit = $this->limit_clausule[0];

    // verify if the limit start was defined
    if (is_null($this->limit_clausule[1]) === false) {
      $limit = "{$this->limit_clausule[1]}, {$this->limit_clausule[0]}";
    }

    $this->query .= " LIMIT {$limit}";
  }

  /** Handle limit clausule */
  private function handle_order_by(): void
  {
    if (isset($this->order_by_clausule) === false)
      return;

    $this->query .= " ORDER BY " . join(" ", $this->order_by_clausule);
  }

  private function bind_values(): void
  {
    if (!isset($this->parameters))
      return;

    foreach ($this->parameters as $key => $value) {
      $this->stmt->bindValue($key, $value);
    }
  }

  /**  Try to get the database connection */
  private function get_conn(): \PDO
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
    $this->set_query($query);
    $this->return_results = true;

    return $this;
  }

  /** Define the where clausule parameters */
  public function where(string $mode, ...$where_parameters): \Database
  {
    $this->where_clausule_mode = $mode;
    $this->where_clausule_parameters = $where_parameters;
    return $this;
  }

  /** Define the query limit */
  public function limit(int $limit, ?int $start = null): \Database
  {
    $this->limit_clausule = array($limit, $start);
    return $this;
  }

  /** Define the query order by clausule parameters */
  public function order_by(string $fields, string $mode): \Database
  {
    $this->order_by_clausule = array($fields, $mode);
    return $this;
  }

  public function load()
  {
    $query = $this->get_query();

    $this->stmt = $this->get_conn()->prepare($query);
    $this->bind_values();
    $status = $this->stmt->execute();

    $this->query_status = $status;

    if ($status === false)
      $this->error_info = $this->stmt->errorInfo();

    $result = $this->stmt->fetchAll(\PDO::FETCH_CLASS);

    return $result;
  }
}
