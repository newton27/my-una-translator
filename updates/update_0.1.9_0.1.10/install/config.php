<?php
/** UNA native updater: 0.1.9 RC4-compatible Translator to 0.1.10. */
$aConfig = [
    'title' => 'My UNA Translator',
    'version_from' => '0.1.9',
    'version_to' => '0.1.10',
    'vendor' => 'My UNA World',
    'compatible_with' => ['15.0.x'],
    'home_dir' => 'newton/my_una_translator/updates/update_0.1.9_0.1.10/',
    'home_uri' => 'my_una_translator_update_019_0110',
    'module_dir' => 'newton/my_una_translator/',
    'module_uri' => 'my_una_translator',
    'db_prefix' => 'my_una_translator_',
    'class_prefix' => 'MyUnaTranslator',
    'install' => [
        'update_files' => 1,
        'clear_db_cache' => 1,
        'clear_template_cache' => 1,
    ],
    'language_category' => 'My UNA Translator',
    'delete_files' => [],
];
