<?php
namespace wpfly;
/**
 * Mysql链式调用PDO封装类，简化基本使用
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
    private $_limit_length = 0;
    private $_limit_begin = 0;
    private $_data = array ();
    private $_exec_affected_rows = 0;
    private function __construct()
    {
        if(!class_exists('PDO'))
        {
            throw new \Exception('Please check whether installed the pdo extension.');
        }
    }
    private function __clone()
    {
    }
    /**
     * 数据库操作实例
     *
     * @return MysqlHelper
     */
    public static function get_instance($id = 0, $force = false)
    {
        if (!isset(self::$_instance[$id]) || $force)
        {
            self::$_instance[$id] = new self();
        }
        return self::$_instance[$id];
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
        // PDO配置，ATTR_EMULATE_PREPARES = false 禁用模拟预处理，启用真正预处理，是防注入的关键。 
        $init_command[] = "SET NAMES {$this->_config['charset']};";
        $options = array (
                \PDO::ATTR_PERSISTENT => $this->_config['persistent'],
                \PDO::MYSQL_ATTR_INIT_COMMAND => implode('', $init_command),
                \PDO::ATTR_EMULATE_PREPARES => false, 
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
       );
        $dsn = "mysql:host={$this->_config['host']};port={$this->_config['port']};dbname={$this->_config['database']}";
        $this->_connecttion = new \PDO($dsn, $this->_config['username'], $this->_config['password'], $options);
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
        $this->_limit_length = 0;
        $this->_limit_begin = 0;
        $this->_data = array ();
        $this->_pdo_statement = null;
        $this->_exec_affected_rows = 0;
    }
    /**
     * 查询链select部分
     *
     * @param string $table
     * @param string|array $field
     * @return MysqlHelper
     */
    function select($table, $field = '*', $where_str = '', $where_parameter = null)
    {
        $this->init('select');
        $field_str = is_array($field) ? '`' . implode('`,`', $field) . '`' : $field;
        $this->_sql = 'SELECT ' . $field_str . ' FROM `' . $this->_config['prefix'] . $table . '`';
        if (!empty($where_str))
        {
            $this->where($where_str, $where_parameter);
        }
        return $this;
    }
    /**
     * 查询链insert部分，支持单行和多行
     *
     * @param string $table
     * @param array $data
     * @return MysqlHelper
     */
    function insert($table, $data)
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
            $this->_sql = 'INSERT INTO `' . $this->_config['prefix'] . $table . '`(`' . implode('`,`', $fields) . '`) VALUES' . $values_all;

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
            $this->_sql = 'INSERT INTO `' . $this->_config['prefix'] . $table . '`(`' . implode('`,`', $fields) . '`) VALUES(' . $values . ')';
            $this->_data = $data;
        }
        return $this;
    }
    /**
     * 查询链replace部分，只支持单行
     *
     * @param string $table
     * @param array $data
     * @return MysqlHelper
     */
    function replace($table, $data)
    {
        $this->init('replace');
        $fields = array_keys($data);
        $values = substr(str_repeat('?,', count($fields)), 0, - 1);
        $this->_sql = 'REPLACE INTO `' . $this->_config['prefix'] . $table . '`(`' . implode('`,`', $fields) . '`) VALUES(' . $values . ')';
        $this->_data = $data;
        return $this;
    }
    /**
     * 查询链update部分
     *
     * @param string $table
     * @param array $data
     * @return MysqlHelper
     */
    function update($table, $data, $where_str = '', $where_parameter = null)
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
        $this->_sql = 'UPDATE `' . $this->_config['prefix'] . $table . '` SET ' . implode(',', $fields);
        if (!empty($where_str))
        {
            $this->where($where_str, $where_parameter);
        }
        return $this;
    }
    /**
     * 查询链delete部分
     *
     * @param string $table
     * @return MysqlHelper
     */
    function delete($table, $where_str = '', $where_parameter = null)
    {
        $this->init('delete');
        $this->_sql = 'DELETE FROM `' . $this->_config['prefix'] . $table . '`';
        if (!empty($where_str))
        {
            $this->where($where_str, $where_parameter);
        }
        return $this;
    }
    /**
     * 查询链where部分
     *
     * @param string $str
     * @param mixed $parameter
     * @return MysqlHelper
     */
    function where($str = '', $parameter = null)
    {
        if (!empty($this->_where))
        {
            return $this;
        }
        $this->_where = " WHERE $str";
        if ($parameter !== null)
        {
            if (is_array($parameter))
            {
                // 根据传递的参数数目，补齐in语句省略的“?”。如果需要自动补齐，只支持一个in语句。
                $c1 = substr_count($this->_where, '?');
                $c2 = count($parameter);
                if($c2 > $c1)
                {
                    $replace = 'in(' . substr(str_repeat('?,', $c2 - $c1 + 1), 0, - 1) . ')';
                    $this->_where = str_replace('in(?)', $replace, $this->_where);
                }
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
        return $this;
    }
    /**
     * 查询链order部分
     *
     * @param string $str
     * @return MysqlHelper
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
     * @return MysqlHelper
     */
    function limit($length = 10, $begin = 0)
    {
        $this->_limit_length = $length;
        $this->_limit_begin = $begin;
        return $this;
    }
    /**
     * 直接sql语句查询，预处理方式
     *
     * @param string $sql
     * @param mixed $parameter
     * @return MysqlHelper
     */
    function sql($sql, $parameter = null)
    {
        $this->init('sql');
        if ($parameter !== null)
        {
            if (is_array($parameter))
            {                
                // 根据传递的参数数目，补齐in语句省略的“?”。如果需要自动补齐，只支持一个in语句。
                $c1 = substr_count($sql, '?');
                $c2 = count($parameter);
                if($c2 > $c1)
                {
                    $replace = 'in(' . substr(str_repeat('?,', $c2 - $c1 + 1), 0, - 1) . ')';
                    $sql = str_replace('in(?)', $replace, $sql);
                }
                $this->_data = $parameter;
            }
            else
            {
                $this->_data = [$parameter];
            }
        }
        // 自动为表名加前缀。有此需要时，请在表名前面加下划线，并用反单引号将下划线及表名包围
        $sql = str_replace('`_', '`' . $this->_config['prefix'], $sql);
        $this->_sql = $sql;
        return $this;
    }
    /**
     * 不带参数的快捷查询，非预处理方式，注意防范sql注入
     * 虽然支持全部语句，返回结果集，主要用于查询
     *
     * @param string $command
     * @return MysqlHelper
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
     * 不带参数的快捷执行，非预处理方式，注意防范sql注入
     * 支持除select语句外的其他语句，只返回影响行数，主要用于插入、更新、删除
     *
     * @param string $command
     * @return MysqlHelper
     */
    function exec($command)
    {
        $this->init('exec');
        // 自动为表名加前缀。有此需要时，请在表名前面加下划线，并用反单引号将下划线及表名包围
        $command = str_replace('`_', '`' . $this->_config['prefix'], $command);
        $this->_sql = $command;
        return $this;
    }
    /**
     * 获取预处理sql语句
     *
     * @return string
     */
    function get_sql()
    {
        switch ($this->_sql_type)
        {
            case 'select' :
                $limit = ($this->_limit_length > 0) ? " LIMIT {$this->_limit_begin},{$this->_limit_length}" : "";
        	    $sql = $this->_sql . $this->_where . $this->_order . $limit;
        	    break;
        	case 'update' :
        	case 'delete' :
                $limit = ($this->_limit_length > 0) ? " LIMIT {$this->_limit_length}" : "";
        	    $sql = $this->_sql . $this->_where . $this->_order . $limit;
                break;
            default:
                $sql = $this->_sql;
        }
        return $sql;
    }
    /**
     * 获取预处理提交参数
     *
     * @return string
     */
    function get_parameters()
    {
        return $this->_data;
    }
    /**
     * 执行查询
     *
     */
    function execute()
    {
        if (empty($this->_connecttion))
        {
            throw new \Exception('The database is not connected.');
        }
        $sql = $this->get_sql();
        if (empty($sql))
        {
            throw new \Exception('Can not find SQL statement.');
        }
        if ($this->_sql_type == 'query')
        {
            $this->_pdo_statement = $this->_connecttion->query($sql);
            if (!$this->_pdo_statement)
            {
                $err = $this->_connecttion->errorInfo();
                throw new \Exception("query错误[{$err[0]}/{$err[1]}/{$err[2]}/{$sql}]");
            }
        }
        elseif ($this->_sql_type == 'exec')
        {
            $result = $this->_connecttion->exec($sql);
            if($result === false)
            {
                $err = $this->_connecttion->errorInfo();
                throw new \Exception("exec错误[{$err[0]}/{$err[1]}/{$err[2]}/{$sql}]");
            }
            $this->_exec_affected_rows = $result;
        }
        else
        {
            // 预处理
            $this->_pdo_statement = $this->_connecttion->prepare($sql);
            if (!$this->_pdo_statement)
            {
                $err = $this->_connecttion->errorInfo();
                throw new \Exception("预处理错误[{$err[0]}/{$err[1]}/{$err[2]}/{$sql}]");
            }
            // 参数绑定
            $i = 1;
            foreach ($this->_data as $data)
            {
                if (!$this->_pdo_statement->bindValue($i, $data))
                {
                    $err = $this->_pdo_statement->errorInfo();
                    throw new \Exception("参数绑定错误[{$err[0]}/{$err[1]}/{$err[2]}/{$sql}]");
                }
                ++ $i;
            }
            // 提交数据并执行
            if (!$this->_pdo_statement->execute())
            {
                $err = $this->_pdo_statement->errorInfo();
                throw new \Exception("execute错误[{$err[0]}/{$err[1]}/{$err[2]}/{$sql}]");
            }
        }
    }
    /**
     * 返回数据列表的二维关联数组
     *
     * @return array{array{}}
     */
    function fetch_all()
    {
        if($this->_sql_type == 'exec')
        {
            throw new \Exception("The exec operation does not support to obtain the result set.");
        }
        $this->execute();
        return $this->_pdo_statement->fetchAll();
    }
    /**
     * 返回数据行的一维关联数组
     *
     * @return array{}
     */
    function fetch_row()
    {
        if($this->_sql_type == 'exec')
        {
            throw new \Exception("The exec operation does not support to obtain the result set.");
        }
        $this->execute();
        $rs = $this->_pdo_statement->fetch();
        return $rs === false ? array() : $rs;
    }
    /**
     * 返回第1行第1列的值
     * 失败返回false，注意区分数据表中包含的false结果。
     *
     * @return mixed false
     */
    function fetch_cell($column_name = null)
    {
        $row = $this->fetch_row();
        if(empty($row)) return false;
        if(is_null($column_name))
        {
            return current($row);
        }
        else
        {
            if(empty($row[$column_name])) return false;
            return $row[$column_name];
        }
    }
    /**
     * 返回插入数据的id
     *
     * @return string false
     */
    function last_id()
    {
        $this->execute();
        return $this->_connecttion->lastInsertId();
    }
    /**
     * 返回实际受影响的行数
     *
     * @return number false
     */
    function affected_rows()
    {
        $this->execute();
        return $this->_sql_type == 'exec' ? $this->_exec_affected_rows : $this->_pdo_statement->rowCount();
    }
    /**
     * 查询的唯一标识，可用作缓存key
     *
     * @return string
     */
    function get_hash()
    {
        return sha1($this->get_sql() . json_encode($this->_data));
    }
}
