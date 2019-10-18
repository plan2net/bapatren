<?php

defined('TYPO3_MODE') or die('Access denied.');

return [
    'page_tree_data' => [
        'path' => '/page/tree/fetchData',
        'target' => \Plan2net\Bapatren\Backend\Controller\Page\TreeController::class . '::fetchDataAction'
    ]
];
