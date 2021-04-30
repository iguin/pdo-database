<?php

require __DIR__ . '/Database.php';

$db = new Database();

$db
  ->set_table('gc_table_employees')
  ->select(array('id', 'name', 'cpf'))
  ->where(
    'AND',
    array(
      'relation' => '=',
      'field' => 'cpf',
      'value' => '48566870697',
    ),
  );

$result = $db->load();

var_dump($result);
