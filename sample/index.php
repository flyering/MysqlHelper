<?php
require '../src/MysqlHelper.php';

/* 数据库结构

CREATE DATABASE `test_helper`CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; 
CREATE TABLE `test_table1`( `id` INT NOT NULL AUTO_INCREMENT, `sn` int(11) NOT NULL DEFAULT '0', `title` VARCHAR(50) NOT NULL DEFAULT '', `content` VARCHAR(2000) NOT NULL DEFAULT '', `createtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `sn` (`sn`) );

*/

use wpfly\MysqlHelper;

MysqlHelper::get_instance()->connect([    
    'database' => 'test_helper',
    'username' => 'root',
    'password' => '',
    'prefix' => 'test_',
]);

function useCase1()
{
    $db = MysqlHelper::get_instance();
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
    ];
    var_dump($db->insert('table1', $data)->affected_rows());
    $data = ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'];
    var_dump($db->insert('table1', $data)->last_id());
}

function useCase2()
{
    $db = MysqlHelper::get_instance();    
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
        ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'],
        ['sn'=>4, 'title'=>'标题4', 'content'=>'内容4'],
        ['sn'=>5, 'title'=>'标题5', 'content'=>'内容5'],
    ];
    $db->insert('table1', $data)->execute();

    $data = ['sn'=>1, 'title'=>'标题5', 'content'=>'内容5'];
    var_dump($db->replace('table1', $data)->affected_rows());
    $data = ['sn'=>2, 'title'=>'标题6', 'content'=>'内容6'];
    var_dump($db->replace('table1', $data)->affected_rows());
}

function useCase3()
{
    $db = MysqlHelper::get_instance();    
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
        ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'],
        ['sn'=>4, 'title'=>'标题4', 'content'=>'内容4'],
        ['sn'=>5, 'title'=>'标题5', 'content'=>'内容5'],
    ];
    $db->insert('table1', $data)->execute();

    var_dump($db->update('table1', ['content'=>'内容0'])->affected_rows());
    var_dump($db->update('table1', ['@sn'=>'sn+10'])->affected_rows());
    var_dump($db->update('table1', ['title'=>'标题A'])->where('id=?', 3)->affected_rows());
    var_dump($db->update('table1', ['title'=>'标题B'])->order('id desc')->limit(2)->affected_rows());
}

function useCase4()
{
    $db = MysqlHelper::get_instance();    
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
        ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'],
        ['sn'=>4, 'title'=>'标题4', 'content'=>'内容4'],
        ['sn'=>5, 'title'=>'标题5', 'content'=>'内容5'],
    ];
    $db->insert('table1', $data)->execute();

    var_dump($db->delete('table1')->where('id>? and id<?', [1,4])->affected_rows());
}

function useCase5()
{
    $db = MysqlHelper::get_instance();    
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
        ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'],
        ['sn'=>4, 'title'=>'标题4', 'content'=>'内容4'],
        ['sn'=>5, 'title'=>'标题5', 'content'=>'内容5'],
    ];
    $db->insert('table1', $data)->execute();

    var_dump($db->select('table1')->fetch_all());
    var_dump($db->select('table1')->order('id desc')->fetch_row());
    var_dump($db->select('table1')->where('id>=?', 4)->fetch_cell('title'));
    var_dump($db->select('table1', "title,content")->where('id in(?)', [1,4])->fetch_all());
    var_dump($db->get_sql());
    var_dump($db->get_parameters());
    var_dump($db->get_hash());
}

function useCase6()
{
    $db = MysqlHelper::get_instance();    
    $db->exec('TRUNCATE TABLE `_table1`')->execute();
    $data = [
        ['sn'=>1, 'title'=>'标题1', 'content'=>'内容1'],
        ['sn'=>2, 'title'=>'标题2', 'content'=>'内容2'],
        ['sn'=>3, 'title'=>'标题3', 'content'=>'内容3'],
        ['sn'=>4, 'title'=>'标题4', 'content'=>'内容4'],
        ['sn'=>5, 'title'=>'标题5', 'content'=>'内容5'],
    ];
    $db->insert('table1', $data)->execute();
    
    var_dump($db->query('select * from `_table1` where id in(1,3,4)')->fetch_all());    
    var_dump($db->exec('delete from `_table1` where id=2')->affected_rows());
    var_dump($db->sql('select * from `_table1` where id in(?)', [1,3,4])->fetch_all());
    var_dump($db->sql('delete from `_table1` where id=?', 5)->affected_rows());
}

ob_start();
try{
    useCase6();
}catch(Exception $e)
{
    echo $e->getMessage();
}
$out = ob_get_clean();
$out = str_replace("\r\n", "<br/>", $out);
$out = str_replace("\n", "<br/>", $out);
header("Content-Type: text/html;charset=utf-8");
echo $out;