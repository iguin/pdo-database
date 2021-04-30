<?php

require __DIR__ . '/Database.php';

$db = new Database();

// Select example

$db->set_table('gc_table_employees')
  ->select(array('id', 'name', 'cpf'))
  ->where(
    'AND',
    array(
      'relation'  => 'LIKE',
      'field'     => 'name',
      'value'     => 'ade'
    ),
  )
  ->order_by('name', 'ASC');

$result = $db->load_single();
var_dump($result);

// Update example

// $db->set_table('gc_table_employees')
//   ->update(array(
//     'name'          => 'Igor',
//     'registration'  => '00002',
//     'modified'      => time(),
//   ))
//   ->where('AND', array(
//     'relation'  => '=',
//     'field'     => 'id',
//     'value'     => 491,
//   ))
//   ->load();

// Pagination example
// $db->set_table('gc_table_employees')
//   ->select(array('id', 'name', 'cpf'))
//   ->pagination(3)
//   ->order_by('id', 'ASC');

// $result = $db->load();
// var_dump($result);
