<?php
require '../src/MysqlHelper.php';

// CREATE DATABASE `test_helper`CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; 
// CREATE TABLE `test_helper`.`test_talbe`( `id` INT NOT NULL AUTO_INCREMENT, `title` VARCHAR(50) NOT NULL DEFAULT '', `content` VARCHAR(2000) NOT NULL DEFAULT '', `createtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`) );

use wpfly\MysqlHelper;

MysqlHelper::get_instance()->connect([    
    'database' => 'test_helper',
    'username' => 'root',
    'password' => '',
    'prefix' => '',
]);

function useCase1()
{
    $db = MysqlHelper::get_instance();
    $data = [
        ['title'=>'标题1','content'=>'内容1'],
        ['title'=>'标题2','content'=>'内容2'],
    ];
    var_dump($db->insert('test_talbe', $data)->affected_rows());
    var_dump($db->select('test_talbe')->fetch_all());
}

ob_start();
try{
    echo "用例1：\r\n";
    useCase1();
}catch(Exception $e)
{
    echo $e->getMessage();
}
$out = ob_get_clean();
$out = str_replace("\r\n", "<br/>", $out);
$out = str_replace("\n", "<br/>", $out);
header("Content-Type: text/html;charset=utf-8");
echo $out;