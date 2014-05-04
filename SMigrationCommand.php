<?php

Yii::import('system.cli.commands.MigrateCommand');

class SMigrationCommand extends MigrateCommand {

    private $__moduleContext = null;

    public function actionmodule($args) {
        $module = array_shift($args);
        $this->__moduleContext = $module;
        $function = 'action' . array_shift($args);
        $this->migrationPath = Yii::getPathOfAlias('application.modules.' . $module . '.migrations');
        if (method_exists($this, $function)) {
            call_user_func(array($this, $function), $args);
        }
    }

    protected function createMigrationHistoryTable() {
        $db = $this->getDbConnection();
        echo 'Creating migration history table "' . $this->migrationTable . '"...';
        $db->createCommand()->createTable($this->migrationTable, array(
            'version' => 'string NOT NULL PRIMARY KEY',
            'apply_time' => 'integer',
            'module' => 'string'
        ));
        $db->createCommand()->insert($this->migrationTable, array(
            'version' => self::BASE_MIGRATION,
            'apply_time' => time(),
        ));
        echo "done.\n";
    }

    protected function migrateUp($class) {
        if ($class === MigrateCommand::BASE_MIGRATION)
            return;

        echo "*** applying $class\n";
        $start = microtime(true);
        $migration = $this->instantiateMigration($class);
        if ($migration->up() !== false) {
            $this->getDbConnection()->createCommand()->insert($this->migrationTable, array(
                'version' => $class,
                'apply_time' => time(),
                'module' => $this->__moduleContext,
            ));
            $time = microtime(true) - $start;
            echo "*** applied $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
        } else {
            $time = microtime(true) - $start;
            echo "*** failed to apply $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
            return false;
        }
    }

    protected function getMigrationHistory($limit) {
        $db = $this->getDbConnection();
        if ($db->schema->getTable($this->migrationTable) === null) {
            $this->createMigrationHistoryTable();
        }
        $condition;
        $params = array();
        if ($this->__moduleContext == NULL) {
            $condition = 'module is null';
        } else {
            $condition = 'module =:MODULE';
            $params = array(':MODULE' => $this->__moduleContext);
        }
        return CHtml::listData($db->createCommand()
                                ->select('version, apply_time')
                                ->from($this->migrationTable)
                                ->where($condition, $params)
                                ->order('version DESC')
                                ->limit($limit)
                                ->queryAll(), 'version', 'apply_time');
    }

}