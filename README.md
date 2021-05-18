# MysqlHelper

#### 介绍

Mysql链式调用PDO封装类，简化基本使用。预处理方式真正防止SQL注入。

#### 安装方法

$ composer require wpfly/mysqlhelper

#### 使用说明

use wpfly\MysqlHelper;

MysqlHelper::get_instance()->connect([    

    'database' => 'test_helper',

    'username' => 'root',

    'password' => '',

    'prefix' => 'test_',

]);

$db = MysqlHelper::get_instance();  

$db->select('table1')->fetch_all();

更多使用示例参看 sample/index.php

#### 参与贡献

1.  Fork 本仓库
2.  在Fork出的仓库提交代码
3.  新建 Pull Request