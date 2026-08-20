<?php

use _Flexagon\Models\DataSourceModel;

/*
 * _Global::$DATA_SOURCES[ID] = new DataSource();
 */
_Global::$DATA_SOURCES['default'] = new DataSourceModel('localhost', 3306, 'username', 'password', 'dbname');
_Global::$DATA_SOURCES['second'] = new DataSourceModel('localhost', 3306, 'username', 'password', 'dbname');
_Global::$DATA_SOURCES['third'] = new DataSourceModel('localhost', 3306, 'username', 'password', 'dbname');

_Global::$SESSION_AUTO_START = false;
_Global::$SESSION_ENCRYPTION_STRING = '';