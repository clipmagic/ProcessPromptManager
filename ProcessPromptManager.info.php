<?php namespace ProcessWire;

$info = [
  'title' => __('Prompt Manager', __FILE__),
  'summary' => __('Manage site-aware AI agent prompt definitions tied to template fields.', __FILE__),
  'version' => '0.0.5Beta',
  'author' => 'Clip Magic, Marvin and Dex',
  'icon' => 'commenting-o',
  'requires' => 'ProcessWire>=3.0.0',
  'autoload' => true,
  'permission' => 'prompt-manager',
  'permissions' => [
    'prompt-manager' => __('Manage AI prompt definitions', __FILE__),
  ],
  'page' => [
    'name' => 'prompt-manager',
    'parent' => 'setup',
    'title' => __('Prompt Manager', __FILE__),
  ],
  'nav' => [
    [
      'url' => './',
      'label' => __('List', __FILE__),
      'icon' => 'list',
    ],
    [
      'url' => 'add/',
      'label' => __('Add', __FILE__),
      'icon' => 'plus',
    ],
  ],
];
