<?php
/**
 * Base Model Class
 * Active Record pattern for database operations
 */

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $hidden = [];
    protected static array $casts = [];

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill model with attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (empty(static::$fillable) || in_array($key, static::$fillable)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Get attribute
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }

        // Check for accessor method
        $method = 'get' . ucfirst($key) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return null;
    }

    /**
     * Set attribute
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Cast attribute based on $casts definition
     */
    private function castAttribute($key, $value)
    {
        if (!isset(static::$casts[$key])) {
            return $value;
        }

        switch (static::$casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'datetime':
                return $value ? new DateTime($value) : null;
            default:
                return $value;
        }
    }

    /**
     * Get all attributes as array
     */
    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, static::$hidden)) {
                $attributes[$key] = $this->castAttribute($key, $value);
            }
        }

        return $attributes;
    }

    /**
     * Get JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Find by ID
     */
    public static function find($id): ?array
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', static::$table, static::$primaryKey);
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find or fail
     */
    public static function findOrFail($id): array
    {
        $result = static::find($id);

        if (!$result) {
            throw new Exception(static::class . " with ID $id not found");
        }

        return $result;
    }

    /**
     * Find by column value
     */
    public static function findBy(string $column, $value): ?array
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = :value LIMIT 1', static::$table, $column);
        return Database::fetchOne($sql, ['value' => $value]);
    }

    /**
     * Get all records
     */
    public static function all(): array
    {
        $sql = sprintf('SELECT * FROM %s', static::$table);
        return Database::fetchAll($sql);
    }

    /**
     * Get records with WHERE clause
     */
    public static function where(string $column, $operator, $value = null): ModelQueryBuilder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return (new ModelQueryBuilder(static::$table))
            ->where($column, $operator, $value);
    }

    /**
     * Create new record
     */
    public static function create(array $data): int
    {
        // Filter to fillable fields
        $fillable = array_intersect_key($data, array_flip(static::$fillable));

        // Handle timestamps
        if (in_array('created_at', static::$fillable)) {
            $fillable['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', static::$fillable)) {
            $fillable['updated_at'] = date('Y-m-d H:i:s');
        }

        return Database::insert(static::$table, $fillable);
    }

    /**
     * Update record
     */
    public static function updateById($id, array $data): int
    {
        // Filter to fillable fields
        $fillable = array_intersect_key($data, array_flip(static::$fillable));

        // Handle timestamps
        if (in_array('updated_at', static::$fillable)) {
            $fillable['updated_at'] = date('Y-m-d H:i:s');
        }

        return Database::update(
            static::$table,
            $fillable,
            static::$primaryKey . ' = :id',
            ['id' => $id]
        );
    }

    /**
     * Delete record
     */
    public static function deleteById($id): int
    {
        return Database::delete(
            static::$table,
            static::$primaryKey . ' = :id',
            ['id' => $id]
        );
    }

    /**
     * Count records
     */
    public static function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) as count FROM %s', static::$table);
        $result = Database::fetchOne($sql);
        return (int) $result['count'];
    }
}

/**
 * Query Builder for Models
 */
class ModelQueryBuilder
{
    private string $table;
    private array $wheres = [];
    private array $params = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Add WHERE clause
     */
    public function where(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $paramKey = 'param_' . count($this->params);
        $this->wheres[] = "$column $operator :$paramKey";
        $this->params[$paramKey] = $value;

        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Execute query and get all results
     */
    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }

        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return Database::fetchAll($sql, $this->params);
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Count results
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $result = Database::fetchOne($sql, $this->params);
        return (int) $result['count'];
    }

    /**
     * Delete matching records
     */
    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new Exception('Cannot delete without WHERE clause');
        }

        return Database::delete(
            $this->table,
            implode(' AND ', $this->wheres),
            $this->params
        );
    }
}
