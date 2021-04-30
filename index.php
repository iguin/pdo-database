<?php

require __DIR__ . '/Database.php';

$db = new Database();


$db->set_table('gc_table_employees')
  ->insert(array(
    'registration'  => '0001',
    'name'          => 'Teste',
    'cpf'           => '01520292651',
    'password'      => password_hash('teste@123', PASSWORD_DEFAULT),
    'created'       => time(),
  ))
  ->load();
