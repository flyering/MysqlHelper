<?php
namespace wpfly;
/**
 * PDO链式调用封装类，简化基本查询
 * 预处理方式真正防止SQL注入
 * 使用更多PDO功能， 可以通过get_connecttion()返回PDO对象自行实现
 *
 * @author wpfly
 * @link https://www.wpfly.cn/
 *
 * @version 1.5
 */
class MysqlHelper
{
    private static $_instance = null;
    private $_error_msg = '';
    private $_config = array (
            'host' => '127.0.0.1',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'prefix' => '',
            'charset' => 'utf8',
            'port' => '3306',
            'persistent' => false
   );
    private $_connecttion = null;
    private $_pdo_statement = null;
    private $_is_select = false;
    private $_sql_type = '';
    private $_sql = '';
    private $_where = '';
    private $_order = '';
    private $_limit = '';
    private $_data = array ();
    private $_fetch_error = false;
    private $_exec_affected_rows = 0;
    private function __construct()
    {
    }
    private function __clone()
    {
    }
    /**
     * 数据库操作实例
     *
     * @return MysqlHelper
     */
    public static function get_instance($server_id = 0, $force = false)
    {
        if (!isset(self::$_instance[$server_id]) || $force)
        {
            self::$_instance[$server_id] = new self();
        }
        return self::$_instance[$server_id];
    }
    /**
     * 连接数据库
     *
     * @param array $config
     */
    function connect($config = array())
    {
        if (is_array($config))
        {
            foreach ($config as $k => $v)
            {
                $k = strtolower($k);
                if (isset($this->_config[$k]))
                {
                    $this->_config[$k] = $v;
                }
            }
        }
        if(empty($this->_config['database']))
        {
            throw new \Exception('Database name cannot be empty.');
        }
        // PDO配置，ATTR_EMULATE_PREPARES = false 禁用预处理模拟，是防注入的关键。 
        $init_command[] = "SET NAMES {$this->_config['charset']};";
        $options = array (
                PDO::ATTR_PERSISTENT => $this->_config['persistent'],
                PDO::MYSQL_ATTR_INIT_COMMAND => implode('', $init_command),
                PDO::ATTR_EMULATE_PREPARES => false, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
       );
        $dsn = "mysql:host={$this->_config['host']};port={$this->_config['port']};dbname={$this->_config['database']}";
        $this->_connecttion = new PDO($dsn, $this->_config['username'], $this->_config['password'], $options);
    }
    /**
     * 获取PDO实例，以便自己实现复杂查询
     *
     * @return PDO null
     */
    function get_connecttion()
    {
        return $this->_connecttion;
    }
    /**
     * 初始化链式调用的缓存
     */
    private function init($sql_type)
    {
        $this->_sql_type = $sql_type;
        $this->_sql = '';
        $this->_where = '';
        $this->_order = '';
        $this->_limit = '';
        $this->_data = array ();
        $this->_pdo_statement = null;
        $this->_exec_affected_rows = 0;
        $this->_fetch_error = false;
    }
    /**
     * 查询链select部分
     *
     * @param string $talbe
     * @param string|array $field
     * @return PDOHelper
     */
    function select($talbe, $field = '*', $where_str = '', $parameter = null)
    {
        $this->init('select');
        $field_str = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
        $this->_sql = 'SELECT ' . $field_str . ' FROM `' . $this->_config['prefix'] . $talbe . '`';
        if (! empty($where_str))
        {
            $this->where($where_str, $parameter);
        }
        return $this;
    }
    /**
     * 查询链insert部分，支持单行和多行
     *
     * @param string $talbe
     * @param array $data
     * @return PDOHelper
     */
    function insert($talbe, $data)
    {
        $this->init('insert');
        $first = current($data);
        if (is_array($first))
        {
            // 多行插入
            $fields = array_keys($first);
            $field_count = count($fields);
            $values = substr(str_repeat('?,', $field_count), 0, - 1);
            $values_all = substr(str_repeat('(' . $values . '),', count($data)), 0, - 1);
            $this->_sql = 'INSERT INTO `' . $this->_config['prefix'] . $talbe . '`(`' . implode('`,`', $fields) . '`) VALUES' . $values_all;

            foreach ($data as $item)
            {
                if (count($item) != $field_count)
                {
                    throw new \Exception('Input data error.');
                }
                foreach ($item as $v)
                {
                    $this->_data[] = $v;
                }
            }
        }
        else
        {
            // 单行插入
            $fields = array_keys($data);
            $values = substr(str_repeat('?,', count($fields)), 0, - 1);
            $this->_sql = 'INSERT INTO `' . $this->_config['prefix'] . $talbe . '`(`' . implode('`,`', $fields) . '`) VALUES(' . $values . ')';
            $this->_data = $data;
        }
        return $this;
    }
    /**
     * 查询链replace部分，只支持单行
     *
     * @param string $talbe
     * @param array $data
     * @return PDOHelper
     */
    function replace($talbe, $data)
    {
        $this->init('replace');
        $fields = array_keys($data);
        $values = substr(str_repeat('?,', count($fields)), 0, - 1);
        $this->_sql = 'REPLACE INTO `' . $this->_config['prefix'] . $talbe . '`(`' . implode('`,`', $fields) . '`) VALUES(' . $values . ')';
        $this->_data = $data;
        return $this;
    }
    /**
     * 查询链update部分
     *
     * @param string $talbe
     * @param array $data
     * @return PDOHelper
     */
    function update($talbe, $data, $where_str = '', $parameter = null)
    {
        $this->init('update');
        $fields = array ();
        foreach ($data as $k => $v)
        {
            if (strpos($k, '@') === 0)
            {
                $k = substr($k, 1);
                $fields[] = "`$k`=$v";
            }
            else
            {
                $fields[] = "`$k`=?";
                $this->_data[] = $v;
            }
        }
        $this->_sql = 'UPDATE `' . $this->_config['prefix'] . $talbe . '` SET ' . implode(',', $fields);
        if (! empty($where_str))
        {
            $this->where($where_str, $parameter);
        }
        return $this;
    }
    /**
     * 查询链delete部分
     *
     * @param string $talbe
     * @return PDOHelper
     */
    function delete($talbe, $where_str = '', $parameter = null)
    {
        $this->init('delete');
        $this->_sql = 'DELETE FROM `' . $this->_config['prefix'] . $talbe . '`';
        if (! empty($where_str))
        {
            $this->where($where_str, $parameter);
        }
        return $this;
    }
    /**
     * 查询链where部分
     *
     * @param string $str
     * @param mixed $parameter
     * @return PDOHelper
     */
    function where($where_str = '', $parameter = null)
    {
        if (! empty($this->_where))
        {
            return $this;
        }
        if ($parameter !== null)
        {
            if (is_array($parameter))
            {
                // 根据实际传递的参数数目，替换in语句中的？，只能有一个in语句
                $c1 = substr_count($where_str, '?');
                $c2 = count($parameter);
                $replace = 'in(' . substr(str_repeat('?,', $c2 - $c1 + 1), 0, - 1) . ')';
                $where_str = str_replace('in(?)', $replace, $where_str);
                foreach ($parameter as $v)
                {
                    $this->_data[] = $v;
                }
            }
            else
            {
                $this->_data[] = $parameter;
            }
        }
        $this->_where = " WHERE $where_str";
        return $this;
    }
    /**
     * 查询链order部分
     *
     * @param string $str
     * @return PDOHelper
     */
    function order($str)
    {
        $this->_order = " ORDER BY $str";
        return $this;
    }
    /**
     * 查询链limit部分
     *
     * @param number $length
     * @param number $begin
     * @return PDOHelper
     */
    function limit($length = 10, $begin = 0)
    {
        $this->_limit = " LIMIT $begin,$length";
        return $this;
    }
    /**
     * 直接sql语句查询
     *
     * @param string $sql
     * @param mixed $parameter
     * @return PDOHelper
     */
    function sql($sql, $parameter = null)
    {
        $this->init('sql');
        if ($parameter !== null)
        {
            if (is_array($parameter))
            {
                $this->_data = $parameter;
                // 根据实际传递的参数数目，替换in语句中的？，只能有一个in语句
                $c1 = substr_count($sql, '?');
                $c2 = count($parameter);
                $replace = 'in(' . substr(str_repeat('?,', $c2 - $c1 + 1), 0, - 1) . ')';
                $sql = str_replace('in(?)', $replace, $sql);
            }
            else
            {
                $this->_data[] = $parameter;
            }
        }
        // 自动为表名加前缀。有此需要时，请在表名前面加下划线，并用反单引号将下划线及表名包围
        $sql = str_replace('`_', '`' . $this->_config['prefix'], $sql);
        $this->_sql = $sql;
        return $this;
    }
    /**
     * 不带参数的便捷查询，非预处理方式，注意防范sql注入
     * 虽然支持全部语句，但返回结果集，主要用于查询
     *
     * @param string $command
     * @return PDOHelper
     */
    function query($command)
    {
        $this->init('query');
        // 自动为表名加前缀。有此需要时，请在表名前面加下划线，并用反单引号将下划线及表名包围
        $command = str_replace('`_', '`' . $this->_config['prefix'], $command);
        $this->_sql = $command;
        return $this;
    }
    /**
     * 不带参数的便捷执行，非预处理方式，注意防范sql注入
     * 支持除select语句外的其他语句，但只返回影响行数，主要用于插入、更新、删除
     *
     * @param string $command
     * @return PDOHelper
     */
    function exec($command)
    {
        $this->init('exec');
        // 自动为表名加前缀。有此需要时，请在表名前面加下划线，并用反单引号将下划线及表名包围
        $command = str_replace('`_', '`' . $this->_config['prefix'], $command);
        $this->_sql = $command;
        return $this;
    }
    private function combine_sql()
    {
        switch ($this->_sql_type)
        {
        	case 'select' :
        	    $this->_sql .= $this->_where . $this->_order . $this->_limit;
        	    $this->_sql_type = 'sql';
        	    break;
        	case 'insert' :
        	case 'replace' :
        	    $this->_sql_type = 'sql';
        	    break;
        	case 'update' :
        	case 'delete' :
        	    $this->_sql .= $this->_where;
        	    $this->_sql_type = 'sql';
        	    break;
        }
    }
    /**
     * 执行查询
     *
     * @return boolean
     */
    function execute()
    {
        if (! in_array($this->_sql_type, array (
                'sql',
                'query',
                'exec'
       )))
        {
            $this->combine_sql();
        }
        if (empty($this->_sql))
        {
            throw new \Exception('Can not find SQL statement.');
        }
        if (! $this->_connecttion)
        {
            throw new \Exception('Connection cannot be use.');
        }
        if ($this->_sql_type == 'sql')
        {
            // 预处理
            $this->_pdo_statement = $this->_connecttion->prepare($this->_sql);
            if (! $this->_pdo_statement)
            {
                $err = $this->_connecttion->errorInfo();
                throw new \Exception("准备错误[{$err[0]}/{$err[1]}/{$err[2]}/{$this->_sql}]");
            }
            // 绑定参数
            $i = 1;
            foreach ($this->_data as $data)
            {
                if (! $this->_pdo_statement->bindValue($i, $data))
                {
                    $err = $this->_pdo_statement->errorInfo();
                    throw new \Exception("绑定错误[{$err[0]}/{$err[1]}/{$err[2]}/{$this->_sql}]");
                }
                ++ $i;
            }
            // 提交数据并执行
            if ($this->_pdo_statement->execute())
            {
                return true;
            }
            else
            {
                $err = $this->_pdo_statement->errorInfo();
                throw new \Exception("查询错误[{$err[0]}/{$err[1]}/{$err[2]}/{$this->_sql}]");
            }
        }
        elseif ($this->_sql_type == 'query')
        {
            if ($this->_pdo_statement = $this->_connecttion->query($this->_sql))
            {
                return true;
            }
            else
            {
                $err = $this->_connecttion->errorInfo();
                throw new \Exception("查询错误[{$err[0]}/{$err[1]}/{$err[2]}/{$this->_sql}]");
            }
        }
        elseif ($this->_sql_type == 'exec')
        {
            $this->_exec_affected_rows = $this->_connecttion->exec($this->_sql);
            return $this->_exec_affected_rows === false ? false : true;
        }
        return false;
    }
    /**
     * 返回数据列表的二维关联数组
     *
     * @return array{array{}} false
     */
    function fetch_all()
    {
        if ($this->_sql_type != 'exec' && $this->execute())
        {
            return $this->_pdo_statement->fetchAll();
        }
        else
        {
            return false;
        }
    }
    /**
     * 返回数据行的一维关联数组
     *
     * @return array{} false
     */
    function fetch_row()
    {
        if ($this->_sql_type != 'exec' && $this->execute())
        {
            $rs = $this->_pdo_statement->fetch();
            if ($rs === false)
            {
                $this->_fetch_error = true;
                return array ();
            }
            return $rs;
        }
        else
        {
            return false;
        }
    }
    /**
     * 返回第1行第1列的值
     * 执行错误或者查询结果为空时返回false，查询结果的内容请勿包含false
     * 可通过is_cell_empty()判断false返回值是否由空查询结果造成
     *
     * @return mixed false
     */
    function fetch_cell()
    {
        if ($this->_sql_type != 'exec' && $this->execute())
        {
            $rs = $this->_pdo_statement->fetchColumn();
            if ($rs === false)
            {
                $this->_fetch_error = true;
            }
            return $rs;
        }
        else
        {
            return false;
        }
    }
    /**
     * 返回插入数据的id
     *
     * @return string false
     */
    function last_id()
    {
        if ($this->execute())
        {
            return $this->_connecttion->lastInsertId();
        }
        else
        {
            return false;
        }
    }
    /**
     * 返回实际受影响的行数
     *
     * @return number false
     */
    function affected_rows()
    {
        if ($this->execute())
        {
            return $this->_sql_type == 'exec' ? $this->_exec_affected_rows : $this->_pdo_statement->rowCount();
        }
        else
        {
            return false;
        }
    }
    /**
     * 是否数据填充错误
     * 可以用于判断：只获取单元格内容时，返回值false是否由空查询结果造成
     *
     * @return boolean
     */
    function is_fetch_error()
    {
        return $this->_fetch_error;
    }
    /**
     * 查询的唯一标识，可用作缓存key
     *
     * @return string
     */
    function get_hash()
    {
        $this->combine_sql();
        return sha1($this->_sql . json_encode($this->_data));
    }
    /**
     * 获取预处理提交的SQL语句
     *
     * @return string
     */
    function get_sql()
    {
        return $this->_sql;
    }
    /**
     * 获取预处理提交的参数
     *
     * @return string
     */
    function get_parameters()
    {
        return $this->_data;
    }
}
