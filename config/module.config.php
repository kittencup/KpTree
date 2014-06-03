<?php
/**
 * Kittencup Module
 *
 * @date 2014 14-6-1 下午5:37
 * @copyright Copyright (c) 2014-2015 Kittencup. (http://www.kittencup.com)
 * @license   http://kittencup.com
 */
return [
    'router' => [
        'routes' =>  [
            'kpModule-admin' =>  [
                'type' => 'Segment',
                'options' =>  [
                    'route' => '/tree',
                    'constraints' =>  [
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'KpTree\Controller',
                        'controller' => 'Test',
                        'action' => 'index'
                    ]
                ]
            ]
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ]
    ],
];