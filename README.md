
Zend Framework 2 Mysql Tree TableGateway
======

介绍
------

实现MYSQL树形方式的几个TableGateway

使用
------

1.嵌套集(NestedTable)

数据库参考KpTree/data/nested

    namespace KpTest\Model\TestTable;
    use KpTree\Model\NestedTable;
    class TestTable extends NestedTable{
        // 设置数据库名
        protected $table = 'nested';
        // 设定左 字段
        protected $lColumn = 'l';
        // 设定右字段
        protected $rColumn = 'r';
        // 设定深度字段
        protected $depthColumn = 'depth';
    }
