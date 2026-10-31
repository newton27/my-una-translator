<?php
$aConfig = [
    'title'=>'My UNA Translator', 'version_from'=>'0.1.11', 'version_to'=>'0.1.12',
    'vendor'=>'My UNA World', 'compatible_with'=>['15.0.x'],
    'home_dir'=>'newton/my_una_translator/updates/update_0.1.11_0.1.12/',
    'home_uri'=>'my_una_translator_update_reporting', 'module_dir'=>'newton/my_una_translator/',
    'module_uri'=>'my_una_translator', 'db_prefix'=>'my_una_translator_', 'class_prefix'=>'MyUnaTranslator',
    'install'=>['execute_sql'=>1,'update_files'=>1,'clear_db_cache'=>1,'clear_template_cache'=>1],
    'delete_files'=>[],
];
