<?php namespace Laravel\Database\Schema\Grammars;

use Laravel\Fluent;
use Laravel\Database\Schema\Table;

class Postgres extends Grammar {

	/**
	 * Generate the SQL statements for a table creation command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return array
	 */
	public function create(Table $table, Fluent $command)
	{
		$columns = implode(', ', $this->columns($table));

		// First we will generate the base table creation statement. Other than auto
		// incrementing keys, no indexes will be created during the first creation
		// of the table as they're added in separate commands.
		$sql = 'CREATE TABLE '.$this->wrap($table).' ('.$columns.')';

		return $sql;
	}

	/**
	 * Generate the SQL statements for a table modification command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return array
	 */
	public function add(Table $table, Fluent $command)
	{
		$columns = $this->columns($table);
		$changes = $this->changes($table);

		// Once we have the array of column definitions, we need to add "add" to the
		// front of each definition, then we'll concatenate the definitions
		// using commas like normal and generate the SQL.
		$columns = implode(', ', array_map(function($column)
		{
			return 'ADD COLUMN '.$column;

		}, $columns));

		// The changes function outputs full commands for each column change, so
		// we only need to join them with commas.
		$changes = implode(', ', $changes);

		return 'ALTER TABLE '.$this->wrap($table).' '.implode(', ', array($columns, $changes));
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
			// Each of the data type's have their own definition creation method,
			// which is responsible for creating the SQL for the type. This lets
			// us to keep the syntax easy and fluent, while translating the
			// types to the types used by the database.
			$sql = $this->wrap($column).' '.$this->type($column);

			$elements = array('incrementer', 'nullable', 'defaults');

			foreach ($elements as $element)
			{
				$sql .= $this->$element($table, $column);
			}

			$columns[] = $sql;
		}

		return $columns;
	}

	/**
	 * Create the individual definitions for changes to columns.
	 *
	 * @param  Table $table
	 * @return array
	 */
	protected function changes(Table $table)
	{
		$changes = array();

		foreach ($table->changes as $column)
		{
			// Postgres has a whole bunch of different commands to change columns.
			// Start out with a rename if appropriate.
			if ($column->from != $column->name)
			{
				$changes[] = 'RENAME COLUMN '.$this->wrap($column->from).' TO '.$this->wrap($column);
			}

			// No way at the moment to figure out if the type changed, so always
			// issue a change for that.
			$changes[] = 'ALTER COLUMN '.$this->wrap($column).' TYPE '.$this->type($column);

			$elements = array('alter_nullable', 'alter_defaults');

			foreach ($elements as $element)
			{
				$changes[] = $this->$element($table, $column);
			}
		}

		return $changes;
	}

	/**
	 * Get the SQL syntax for changing if a column is nullable.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function alter_nullable(Table $table, Fluent $column)
	{
		return 'ALTER COLUMN'.$this->wrap($column).($column->nullable ? ' DROP' : ' SET').' NOT NULL';
	}

	/**
	 * Get the SQL syntax for indicating if a column is nullable.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function nullable(Table $table, Fluent $column)
	{
		return ($column->nullable) ? ' NULL' : ' NOT NULL';
	}

	/**
	 * Get the SQL syntax for changing a column's default value.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function alter_defaults(Table $table, Fluent $column)
	{
		$sql = 'ALTER COLUMN'.$this->wrap($column);

		if ( ! is_null($column->default))
		{
			$sql .= ' SET' . $this->defaults($table, $column);
		}
		else
		{
			$sql .= ' DROP DEFAULT';
		}

		return $sql;
	}

	/**
	 * Get the SQL syntax for specifying a default value on a column.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function defaults(Table $table, Fluent $column)
	{
		if ( ! is_null($column->default))
		{
			return " DEFAULT '".$this->default_value($column->default)."'";
		}
	}

	/**
	 * Get the SQL syntax for defining an auto-incrementing column.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function incrementer(Table $table, Fluent $column)
	{
		// We don't actually need to specify an "auto_increment" keyword since we
		// handle the auto-increment definition in the type definition for
		// integers by changing the type to "serial".
		if ($column->type == 'integer' and $column->increment)
		{
			return ' PRIMARY KEY';
		}
	}

	/**
	 * Generate the SQL statement for creating a primary key.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function primary(Table $table, Fluent $command)
	{
		$columns = $this->columnize($command->columns);

		return 'ALTER TABLE '.$this->wrap($table)." ADD PRIMARY KEY ({$columns})";
	}

	/**
	 * Generate the SQL statement for creating a unique index.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function unique(Table $table, Fluent $command)
	{
		$table = $this->wrap($table);

		$columns = $this->columnize($command->columns);

		return "ALTER TABLE $table ADD CONSTRAINT ".$command->name." UNIQUE ($columns)";
	}

	/**
	 * Generate the SQL statement for creating a full-text index.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function fulltext(Table $table, Fluent $command)
	{
		$name = $command->name;

		$columns = $this->columnize($command->columns);

		return "CREATE INDEX {$name} ON ".$this->wrap($table)." USING gin({$columns})";
	}

	/**
	 * Generate the SQL statement for creating a regular index.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function index(Table $table, Fluent $command)
	{
		return $this->key($table, $command);
	}

	/**
	 * Generate the SQL statement for creating a new index.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @param  bool     $unique
	 * @return string
	 */
	protected function key(Table $table, Fluent $command, $unique = false)
	{
		$columns = $this->columnize($command->columns);

		$create = ($unique) ? 'CREATE UNIQUE' : 'CREATE';

		return $create." INDEX {$command->name} ON ".$this->wrap($table)." ({$columns})";
	}

	/**
	 * Generate the SQL statement for a rename table command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function rename(Table $table, Fluent $command)
	{
		return 'ALTER TABLE '.$this->wrap($table).' RENAME TO '.$this->wrap($command->name);
	}

	/**
	 * Generate the SQL statement for a drop column command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_column(Table $table, Fluent $command)
	{
		$columns = array_map(array($this, 'wrap'), $command->columns);

		// Once we the array of column names, we need to add "drop" to the front
		// of each column, then we'll concatenate the columns using commas and
		// generate the alter statement SQL.
		$columns = implode(', ', array_map(function($column)
		{
			return 'DROP COLUMN '.$column;

		}, $columns));

		return 'ALTER TABLE '.$this->wrap($table).' '.$columns;
	}

	/**
	 * Generate the SQL statement for a drop primary key command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_primary(Table $table, Fluent $command)
	{
		return 'ALTER TABLE '.$this->wrap($table).' DROP CONSTRAINT '.$table->name.'_pkey';
	}

	/**
	 * Generate the SQL statement for a drop unique key command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_unique(Table $table, Fluent $command)
	{
		return $this->drop_constraint($table, $command);
	}

	/**
	 * Generate the SQL statement for a drop full-text key command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_fulltext(Table $table, Fluent $command)
	{
		return $this->drop_key($table, $command);
	}

	/**
	 * Generate the SQL statement for a drop index command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	public function drop_index(Table $table, Fluent $command)
	{
		return $this->drop_key($table, $command);
	}

	/**
	 * Generate the SQL statement for a drop key command.
	 *
	 * @param  Table    $table
	 * @param  Fluent   $command
	 * @return string
	 */
	protected function drop_key(Table $table, Fluent $command)
	{
		return 'DROP INDEX '.$command->name;
	}

	/**
	 * Drop a foreign key constraint from the table.
	 *
	 * @param  Table   $table
	 * @param  Fluent  $command
	 * @return string
	 */
	public function drop_foreign(Table $table, Fluent $command)
	{
		return $this->drop_constraint($table, $command);
	}

	/**
	 * Generate the data-type definition for a string.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_string(Fluent $column)
	{
		return 'VARCHAR('.$column->length.')';
	}

	/**
	 * Generate the data-type definition for an integer.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_integer(Fluent $column)
	{
		return ($column->increment) ? 'SERIAL' : 'BIGINT';
	}

	/**
	 * Generate the data-type definition for an integer.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_float(Fluent $column)
	{
		return 'REAL';
	}

	/**
	 * Generate the data-type definition for a decimal.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_decimal(Fluent $column)
	{
		return "DECIMAL({$column->precision}, {$column->scale})";
	}

	/**
	 * Generate the data-type definition for a boolean.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_boolean(Fluent $column)
	{
		return 'SMALLINT';
	}

	/**
	 * Generate the data-type definition for a date.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_date(Fluent $column)
	{
		return 'TIMESTAMP(0) WITHOUT TIME ZONE';
	}

	/**
	 * Generate the data-type definition for a timestamp.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_timestamp(Fluent $column)
	{
		return 'TIMESTAMP';
	}

	/**
	 * Generate the data-type definition for a text column.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_text(Fluent $column)
	{
		return 'TEXT';
	}

	/**
	 * Generate the data-type definition for a blob.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_blob(Fluent $column)
	{
		return 'BYTEA';
	}

	/**
	 * Generate the data-type definition for an enum.
	 *
	 * @param  Fluent  $column
	 * @return string
	 */
	protected function type_enum(Fluent $column)
	{
		$values = implode(', ', array_map(function($value) {
			return Database::escape($value);
		}, $column->values));

		return 'ENUM('.$values.')';
	}

}
