<?php
require '../src/MysqlHelper.php';

use wpfly\MysqlHelper;

// CREATE DATABASE `test_helper`CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; 
// CREATE TABLE `test_helper`.`test_talbe`( `id` INT NOT NULL AUTO_INCREMENT, `title` VARCHAR(50) NOT NULL DEFAULT '', `content` VARCHAR(2000) NOT NULL DEFAULT '', `createtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`) );

$db = MysqlHelper::get_instance();
$db->connect([    
    'database' => 'test_helper',
    'username' => 'root',
    'password' => '',
    'prefix' => '',
]);
$data = [
    ['title'=>'标题1','content'=>'内容1'],
    ['title'=>'标题2','content'=>'内容2'],
];
var_dump($db->insert('test_talbe', $data)->affected_rows());
var_dump($db->select('test_talbe')->fetch_all());


