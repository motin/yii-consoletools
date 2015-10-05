<?php
/**
 * MysqldumpCommand class file.
 * @author Christoffer Niska <christoffer.niska@gmail.com>
 * @copyright Copyright &copy; Christoffer Niska 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @package crisu83.yii-consoletools.commands
 */

/**
 * Command for running mysqldump and save the output into a file for later use.
 */
class MysqldumpCommand extends ProcessCommand
{
    /**
     * @var string the path to the mysqldump binary.
     */
    public $binPath;
    /**
     * @var string the path to the directory where the dump-file should be created.
     */
    public $dumpPath = 'protected/data';
    /**
     * @var string the name of the dump-file.
     */
    public $dumpFile = 'dump.sql';
    /**
     * @var array the options for mysqldump.
     * @see http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html
     */
    public $options = array();
    /**
     * @var bool include schema in the dump.
     */
    public $schema = true;
    /**
     * @var bool include data in the dump.
     */
    public $data = true;
    /**
     * @var bool include routines (triggers, routines and stored procedures) in the dump.
     */
    public $routines = false;
    /**
     * @var bool format data in a compact way (set false for more verbose insert statements).
     */
    public $compact = true;
    /**
     * @var string the component ID for the database connection to use.
     */
    public $connectionID = 'db';

    private $_db;

    /**
     * Initializes the database options.
     */
    public function initDbOptions()
    {
        $db = $this->getDb();
        $this->options['user'] = DATABASE_USER;
        $this->options['password'] = DATABASE_PASSWORD;
        $this->options['host'] = DATABASE_HOST;
        $this->options['port'] = DATABASE_PORT;
    }

    /**
     * Runs the command.
     * @param array $args the command-line arguments.
     * @return integer the return code.
     * @throws CException if the mysqldump binary cannot be located or if the actual dump fails.
     */
    public function run($args)
    {
        list($action, $options, $args) = $this->resolveRequest($args);

        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->initDbOptions();

        if (!$this->schema || $this->schema === "false") {
            $this->options["no-create-info"] = null;
        }
        if (!$this->data || $this->data === "false") {
            $this->options["no-data"] = null;
        }
        if (!$this->routines || $this->routines === "false") {
            $this->options["skip-triggers"] = null;
        } else {
            $this->options["triggers"] = null;
            $this->options["routines"] = null;
        }
        if (!$this->compact || $this->compact === "false") {
            $this->options["skip-extended-insert"] = null;
            $this->options["complete-insert"] = null;
        }
        $this->options["no-create-db"] = null;

        $binPath = $this->resolveBinPath();
        $options = $this->normalizeOptions($this->options);
        $database = $this->resolveDatabaseName();
        $dumpPath = $this->resolveDumpPath();

        return $this->process(
            "$binPath $options $database",
            array(
                self::DESCRIPTOR_STDIN => array('pipe', 'r'),
                self::DESCRIPTOR_STDOUT => array('file', $dumpPath, 'w'),
                self::DESCRIPTOR_STDERR => array('pipe', 'w'),
            )
        );
    }

    /**
     * Returns the path to the mysqldump binary file.
     * @return string the path.
     */
    protected function resolveBinPath()
    {
        return isset($this->binPath) ? $this->binPath : 'mysqldump';
    }

    /**
     * Returns the name of the database.
     * @return string the name.
     */
    protected function resolveDatabaseName()
    {
        return $this->getDb()->createCommand('SELECT DATABASE();')->queryScalar();
    }

    /**
     * Returns the path to the dump-file.
     * @return string the path.
     */
    protected function resolveDumpPath()
    {
        $path = $this->basePath . '/' . $this->dumpPath;
        $this->ensureDirectory($path);
        return realpath($path) . '/' . $this->dumpFile;
    }

    /**
     * Normalizes the given options to a string
     * @param array $options the options.
     * @return string the options.
     */
    protected function normalizeOptions($options)
    {
        $result = array();
        foreach ($options as $name => $value) {
            if ($value !== null) {
                $result[] = "--$name=\"$value\"";
            } else {
                $result[] = "--$name";
            }
        }
        return implode(' ', $result);
    }

    /**
     * Returns the database connection component.
     * @return CDbConnection the component.
     * @throws CException if the component is not found.
     */
    protected function getDb()
    {
        if (isset($this->_db)) {
            return $this->_db;
        } else {
            if (($db = Yii::app()->getComponent($this->connectionID)) === null) {
                throw new CException(sprintf(
                    'Failed to get database connection. Component %s not found.',
                    $this->connectionID
                ));
            }
            return $this->_db = $db;
        }
    }
}
