<?php

namespace Drupal\Core\Config;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines the Database storage.
 */
class DatabaseStorage implements StorageInterface {
  use DependencySerializationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The database table name.
   *
   * @var string
   */
  protected $table;

  /**
   * Additional database connection options to use in queries.
   *
   * @var array
   */
  protected $options = [];

  /**
   * The storage collection.
   *
   * @var string
   */
  protected $collection = StorageInterface::DEFAULT_COLLECTION;

  /**
   * Constructs a new DatabaseStorage.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Database connection to use for reading and writing configuration data.
   * @param string $table
   *   A database table name to store configuration data in.
   * @param array $options
   *   (optional) Any additional database connection options to use in queries.
   * @param string $collection
   *   (optional) The collection to store configuration in. Defaults to the
   *   default collection.
   */
  public function __construct(Connection $connection, $table, array $options = [], $collection = StorageInterface::DEFAULT_COLLECTION) {
    $this->connection = $connection;
    $this->table = $table;
    $this->options = $options;
    $this->collection = $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    try {
      return (bool) $this->connection->queryRange('SELECT 1 FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [name] = :name', 0, 1, [
        ':collection' => $this->collection,
        ':name' => $name,
      ], $this->options)->fetchField();
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a read without actually having the table available,
      // return false so the caller can handle it.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $data = FALSE;
    try {
      $raw = $this->connection->query('SELECT [data] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [name] = :name', [
        ':collection' => $this->collection,
        ':name' => $name,
      ], $this->options)->fetchField();
      if ($raw !== FALSE) {
        $data = $this->decode($raw);
      }
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a read without actually having the table available,
      // return false so the caller can handle it.
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    if (empty($names)) {
      return [];
    }

    $list = [];
    try {
      $list = $this->connection
        ->query('SELECT [name], [data] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] = :collection AND [name] IN ( :names[] )', [
          ':collection' => $this->collection,
          ':names[]' => $names,
        ], $this->options)
        ->fetchAllKeyed();
      foreach ($list as &$data) {
        $data = $this->decode($data);
      }
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a read without actually having the table available,
      // return an empty array so the caller can handle it.
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    $data = $this->encode($data);
    try {
      return $this->doWrite($name, $data);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if ($this->ensureTableExists()) {
        return $this->doWrite($name, $data);
      }
      // Some other failure that we can not recover from.
      throw new StorageException($e->getMessage(), 0, $e);
    }
  }

  /**
   * Helper method so we can re-try a write.
   *
   * @param string $name
   *   The config name.
   * @param string $data
   *   The config data, already dumped to a string.
   *
   * @return bool
   *   TRUE when the write was successful, FALSE otherwise.
   */
  protected function doWrite($name, $data) {
    return (bool) $this->connection->merge($this->table, $this->options)
      ->keys(['collection', 'name'], [$this->collection, $name])
      ->fields(['data' => $data])
      ->execute();
  }

  /**
   * Check if the config table exists and create it if not.
   *
   * @return bool
   *   TRUE if the table was created, FALSE otherwise.
   *
   * @throws \Drupal\Core\Config\StorageException
   *   If a database error occurs.
   */
  protected function ensureTableExists() {
    try {
      $this->connection->schema()->createTable($this->table, static::schemaDefinition());
    }
    // If another process has already created the config table, attempting to
    // recreate it will throw an exception. In this case just catch the
    // exception and do nothing.
    catch (DatabaseException) {
      return TRUE;
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Defines the schema for the configuration table.
   *
   * @internal
   */
  protected static function schemaDefinition() {
    $schema = [
      'description' => 'The base table for configuration data.',
      'fields' => [
        'collection' => [
          'description' => 'Primary Key: Config object collection.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'name' => [
          'description' => 'Primary Key: Config object name.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
        ],
        'data' => [
          'description' => 'A serialized configuration object data.',
          'type' => 'blob',
          'not null' => FALSE,
          'size' => 'big',
        ],
      ],
      'primary key' => ['collection', 'name'],
    ];
    return $schema;
  }

  /**
   * Implements Drupal\Core\Config\StorageInterface::delete().
   *
   * @throws \PDOException
   *
   * @todo Ignore replica targets for data manipulation operations.
   */
  public function delete($name) {
    return (bool) $this->connection->delete($this->table, $this->options)
      ->condition('collection', $this->collection)
      ->condition('name', $name)
      ->execute();
  }

  /**
   * Implements Drupal\Core\Config\StorageInterface::rename().
   *
   * @throws \PDOException
   */
  public function rename($name, $new_name) {
    return (bool) $this->connection->update($this->table, $this->options)
      ->fields(['name' => $new_name])
      ->condition('name', $name)
      ->condition('collection', $this->collection)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return serialize($data);
  }

  /**
   * Implements Drupal\Core\Config\StorageInterface::decode().
   *
   * @throws \ErrorException
   *   The unserialize() call will trigger E_NOTICE if the string cannot
   *   be unserialized.
   */
  public function decode($raw) {
    $data = @unserialize($raw, ['allowed_classes' => FALSE]);
    return is_array($data) ? $data : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    try {
      $query = $this->connection->select($this->table);
      $query->fields($this->table, ['name']);
      $query->condition('collection', $this->collection, '=');
      $query->condition('name', $prefix . '%', 'LIKE');
      $query->orderBy('collection')->orderBy('name');
      return $query->execute()->fetchCol();
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a read without actually having the table available,
      // return an empty array so the caller can handle it.
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    try {
      return (bool) $this->connection->delete($this->table, $this->options)
        ->condition('name', $prefix . '%', 'LIKE')
        ->condition('collection', $this->collection)
        ->execute();
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a delete without actually having the table available,
      // return false so the caller can handle it.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->connection,
      $this->table,
      $this->options,
      $collection
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    try {
      return $this->connection->query('SELECT DISTINCT [collection] FROM {' . $this->connection->escapeTable($this->table) . '} WHERE [collection] <> :collection ORDER by [collection]', [
        ':collection' => StorageInterface::DEFAULT_COLLECTION,
      ])->fetchCol();
    }
    catch (\Exception $e) {
      if ($this->connection->schema()->tableExists($this->table)) {
        throw $e;
      }
      // If we attempt a read without actually having the table available,
      // return an empty array so the caller can handle it.
      return [];
    }
  }

}
