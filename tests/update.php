<?php

function removeDirectory($directory) {
    foreach (scandir($directory) as $entity) {
        if ($entity === '.' || $entity === '..') {
            continue;
        }

        if (is_dir($directory . '/' . $entity)) {
            removeDirectory($directory . '/' . $entity);
            continue;
        }

        @unlink($directory . '/' . $entity);
    }

    rmdir($directory);
}

chdir(__DIR__);

echo shell_exec('git clone https://github.com/pugjs/pug');

echo "Backup old cases\n";
rename('cases', 'cases-save');

echo "Extract new cases\n";
rename('pug/packages/pug/test/cases', 'cases');

clearstatcache();

foreach (['pug', 'cases-save'] as $directory) {
    echo "Remove $directory directory\n";
    removeDirectory($directory);
}

echo "Done\n";
