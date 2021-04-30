<?php

/**
 * 
 * MySQL database handle
 * 
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

  /** @var object $pagination_infos */
  private $pagination_infos;

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

  private function set_pagination_info($key, $value): void
  {
    if (!isset($this->pagination_infos))
      $this->pagination_infos = new \stdClass;

    $this->pagination_infos->{$key} = $value;
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

  /** Build a where clausule and return the code */
  private function build_where_clausule(): ?string
  {
    if (isset($this->where_clausule_parameters) === false)
      return null;

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

    return " WHERE {$where}";
  }

  /** Handle where clausule */
  private function handle_where(): void
  {
    $where = $this->build_where_clausule();

    if (is_null($where) === true)
      return;

    $this->query .= $where;
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

  /** Build a insert query */
  public function insert(array $fields): \Database
  {
    $values = array();

    foreach ($fields as $column => $value) {
      $column_id = ':' . uniqid("{$column}_");

      $this->parameters[$column_id] = $value;
      array_push($values, $column_id);
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
      $column_id = ':' . uniqid("{$column}_");

      $this->parameters[$column_id] = $value;

      array_push($columns, "{$column} = {$column_id}");
    }

    $columns = join(', ', $columns);

    $this->query = "UPDATE {$this->table} SET {$columns}";

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

  /** Get the total rows  */
  private function get_total_rows(): int
  {
    $where = $this->build_where_clausule();

    $query = "SELECT COUNT(`id`) as total FROM {$this->table}";

    if (is_null($where) === false) {
      $query .= $where;
    }

    $result = $this->load_raw_query($query);

    return (count($result) === 0) ? 0 : intval($result[0]->total);
  }

  public function pagination(int $page): \Database
  {
    $real_page = $page <= 1 ? 0 : --$page;

    $this->set_pagination_info('total_rows', $this->get_total_rows());
    $this->set_pagination_info('total_pages', ceil($this->get_total_rows() / self::PAGINATION_LIMIT));

    $start = $real_page * self::PAGINATION_LIMIT;

    $this->limit(self::PAGINATION_LIMIT, $start);

    return $this;
  }

  /** Define the query order by clausule parameters */
  public function order_by(string $fields, string $mode): \Database
  {
    $this->order_by_clausule = array($fields, $mode);
    return $this;
  }

  /** Load a raw query */
  private function load_raw_query(string $query, ?int $mode = null, ?array $options = null)
  {
    $this->stmt = $this->get_conn()->prepare($query);
    $this->bind_values();

    unset($this->parameters);

    $status = $this->stmt->execute();

    $this->query_status = $status;

    if ($status === false) {
      $this->error_info = $this->stmt->errorInfo();
    }

    // get mode
    $mode = $mode ?? \PDO::FETCH_CLASS;

    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetchAll($mode, ...$options);

    return $result;
  }

  private function build_fetch(): void
  {
    $query = $this->get_query();

    $this->stmt = $this->get_conn()->prepare($query);
    $this->bind_values();
    $status = $this->stmt->execute();

    $this->query_status = $status;

    if ($status === false) {
      $this->error_info = $this->stmt->errorInfo();
    }
  }

  public function load(?int $mode = null, ?array $options = null)
  {
    $this->build_fetch();

    // get mode
    $mode = $mode ?? \PDO::FETCH_CLASS;
    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetchAll($mode, ...$options);

    return $this->return_results === true
      ? $result
      : $this->query_status;
  }

  public function load_single(?int $mode = null, ?array $options = null)
  {
    $this->build_fetch();

    // get mode
    $mode = $mode ?? \PDO::FETCH_ASSOC;
    
    $options = is_null($options) ? array() : $options;

    $result = $this->stmt->fetch($mode);

    return $this->return_results === true
      ? $result
      : $this->query_status;
  }
}
