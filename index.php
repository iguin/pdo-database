<?php

require __DIR__ . '/Database.php';

$db = new Database();

$db
  ->set_table('gc_table_employees')
  ->select(array('id', 'name', 'cpf'))
  ->where(
    'AND',
    array(
      'relation'  => 'LIKE',
      'field'     => 'name',
      'value'     => 'ad'
    ),
    array(
      'relation'  => '>',
      'field'     => 'registration',
      'value'     => 300
    ),
  )
  ->limit(2, 1)
  ->order_by('name', 'ASC');

$result = $db->load();

var_dump($result);
