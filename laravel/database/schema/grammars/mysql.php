<?php namespace Laravel\Database\Schema\Grammars;

use Laravel\Database\Schema\Table;

class MySQL extends Grammar {

	/**
	 * The keyword identifier for the database system.
	 *
	 * @var string
	 */
	public $wrapper = '`';

	/**
	 * Generate the SQL statements for a table creation command.
	 *
	 * @param  Table  $table
	 * @param  array  $command
	 * @return array
	 */
	public function create(Table $table, $command)
	{
		$columns = implode(', ', $this->columns($table));

		// First we will generate the base table creation statement. Other than
		// auto-incrementing keys, no indexes will be created during the first
		// creation of the table. They will be added in separate commands.
		$sql = 'CREATE TABLE '.$this->wrap($table->name).' ('.$columns.')';

		// MySQL supports various "engines" for databas tables. If an engine
		// was specified by the developer, we will set it after adding the
		// columns the table creation statement.
		if ( ! is_null($table->engine))
		{
			$sql .= ' ENGINE = '.$table->engine;
		}

		return (array) $sql;
	}

	/**
	 * Geenrate the SQL statements for a table modification command.
	 *
	 * @param  Table  $table
	 * @param  array  $command
	 * @return array
	 */
	public function add(Table $table, $command)
	{
		$columns = $this->columns($table);

		// Once we the array of column definitions, we need to add "add"
		// to the front of each definition, then we'll concatenate the
		// definitions using commas like normal and generate the SQL.
		$columns = implode(', ', array_map(function($column)
		{
			return 'ADD '.$column;

		}, $columns));

		$sql = 'ALTER TABLE '.$this->wrap($table->name).' '.$columns;

		return (array) $sql;
	}

	/**
	 * Create the individual column definitions for the table.
	 *
	 * @param  Table  $table
	 * @return array
	 */
	protected function columns(Table $table)
	{
		$columns = array();

		foreach ($table->columns as $column)
		{
			// Each of the data type's have their own definition creation
			// method, which is responsible for creating the SQL version
			// of the data type. This allows us to keep the syntax easy
			// and fluent, while translating the types to the types
			// used by the database system.
			$sql = $this->wrap($column->name).' '.$this->type($column);

			$sql .= ($column->nullable) ? ' NULL' : ' NOT NULL';

			// Auto-incrementing IDs are required to be a primary key,
			// so we'll go ahead and add the primary key definition
			// when the column is created.
			if ($column->type() == 'integer' and $column->increment)
			{
				$sql .= ' AUTO_INCREMENT PRIMARY KEY';
			}

			$columns[] = $sql;
		}

		return $columns;
	}

	/**
	 * Generate the SQL statement for creating a primary key.
	 *
	 * @param  Table   $table
	 * @param  array   $command
	 * @return string
	 */
	public function primary(Table $table, $command)
	{
		return $this->key($table, $command, 'PRIMARY KEY');
	}

	/**
	 * Generate the SQL statement for creating a unique index.
	 *
	 * @param  Table   $table
	 * @param  array   $command
	 * @return string
	 */
	public function unique(Table $table, $command)
	{
		return $this->key($table, $command, 'UNIQUE');
	}

	/**
	 * Generate the SQL statement for creating a full-text index.
	 *
	 * @param  Table   $table
	 * @param  array   $command
	 * @return string
	 */
	public function fulltext(Table $table, $command)
	{
		return $this->key($table, $command, 'FULLTEXT');
	}

	/**
	 * Generate the SQL statement for creating a regular index.
	 *
	 * @param  Table   $table
	 * @param  array   $command
	 * @return string
	 */
	public function index(Table $table, $command)
	{
		return $this->key($table, $command, 'INDEX');
	}

	/**
	 * Generate the SQL statement for creating a new index.
	 *
	 * @param  Table   $table
	 * @param  array   $command
	 * @param  string  $type
	 * @return string
	 */
	protected function key(Table $table, $command, $type)
	{
		$keys = $this->columnize($command['columns']);

		return 'ALTER TABLE '.$this->wrap($table->name).' ADD '.$type.' ('.$keys.')';
	}

	/**
	 * Generate the data-type definition for a string.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_string($column)
	{
		return 'VARCHAR('.$column->length.')';
	}

	/**
	 * Generate the data-type definition for an integer.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_integer($column)
	{
		return 'INT';
	}

	/**
	 * Generate the data-type definition for a boolean.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_boolean($column)
	{
		return 'TINYINT';
	}

	/**
	 * Generate the data-type definition for a date.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_date($column)
	{
		return 'DATETIME';
	}

	/**
	 * Generate the data-type definition for a text column.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_text($column)
	{
		return 'TEXT';
	}

	/**
	 * Generate the data-type definition for a blob.
	 *
	 * @param  Column  $column
	 * @return string
	 */
	protected function type_blob($column)
	{
		return 'BLOB';
	}

}