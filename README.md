1.概述
--------
实现MYSQL树形的几个TableGateway

2.安装
--------
[github下载](https://github.com/kittencup/KpTree.git) 或者 `composer require "kittencup/kp-tree": "dev-master"`

```
#application.config.php
return [
	'modules' => [
        // ...
        'KpTree',
    ],
];
```

3.设置
--------

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

2.路径枚举(PathEnumTable)

数据库参考KpTree/data/pathEnum

    namespace KpTest\Model\TestTable;
    use KpTree\Model\PathEnumTable;
    class TestTable extends PathEnumTable{
        // 数据库名
        protected $table = 'pathEnum';
        // 设定路径字段
        protected $pathColumn = 'path';
        // 设定路径分隔符
        protected $pathDelimiter = '/';
        // 设定深度字段
        protected $depthColumn = 'depth';
    }

3.闭包表(ClosureTable)

数据库参考KpTree/data/closure

    namespace KpTest\Model\TestTable;
    use KpTree\Model\ClosureTable;
    class TestTable extends ClosureTable{
        // 数据库表名
        protected $table = 'closure';
        // 存储引用关系的表名
        protected $pathsTable = 'closurePaths';
        // $pathsTable表内 祖先字段名
        protected $ancestorColumn = 'ancestor';
        // $pathsTable表内 后辈字段名
        protected $descendantColumn = 'descendant';
        // $table内 深度字段
        protected $depthColumn = 'depth';
    }
    
4.使用
--------

具体可参考  KpTree\Model\TreeTableInterface

添加一个节点

    $table->addNode(['name'=>'kittencup'],4);


移动节点

     $table->moveNode(2, 3)
     
根据节点id获取父节点

    $table->getParentNodeById(12);
    $table->getParentNodeById(12,2)
    $table->getParentNodeById(12,2,'ASC')
    $table->getParentNodeById(12,2,'ASC',['id','name'])

根据节点id获取子节点

    $table->getChildNodeById(12);
    $table->getChildNodeById(12,2)
    $table->getChildNodeById(12,2,'ASC')
    $table->getChildNodeById(12,2,'ASC',['id','name'])

根据节点id 删除所有子元素

    $table->deleteChildNodeById(1);
    $table->deleteChildNodeById(1,false);

根据节点id 删除节点

    $table->deleteNodeById(1);

