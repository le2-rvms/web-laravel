<?php

namespace App\Console\Commands\Sys;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TableLogTriggersCreate extends Command
{
    protected $signature   = '_sys:table-log-triggers:create {--only-reset : Only reset table log triggers}';
    protected $description = 'Deploy audit schema, tables, function, and triggers based on config';

    protected string $auditSchema;

    protected array $tables;
    protected string $auditFunction = 'record_changes';

    public function handle(): int
    {
        $this->auditSchema = config('setting.dblog.schema');

        foreach (config('setting.dblog.models') as $class_name => $pk) {
            /** @var Model $model */
            $model = new $class_name();
            $table = $model->getTable();

            $this->tables[$table] = $pk;
        }

        // 1. Deploy schema, tables, function and triggers in transaction
        DB::transaction(function () {
            $this->ensureSchema();
            $this->ensureTables();
            $this->deployFunction();
            $this->resetTriggers();
            $onlyReset = (bool) $this->option('only-reset');
            if (!$onlyReset) {
                $this->createTriggers();
            }
        });

        // 2. Create indexes outside transaction to avoid long locks
        $this->ensureIndexes();

        $this->info('Audit infrastructure deployed successfully.');

        return 0;
    }

    protected function ensureSchema(): void
    {
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$this->auditSchema}");
    }

    protected function ensureTables(): void
    {
        foreach ($this->tables as $table => $pk) {
            $log = "{$this->auditSchema}.{$table}";
            DB::statement(
                "CREATE TABLE IF NOT EXISTS {$log} (
                    log_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    action VARCHAR(10) NOT NULL,
                    pk BIGINT NOT NULL,
                    old_data JSONB,
                    new_data JSONB,
                    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $this->info("Ensured log table: {$log}");
        }
    }

    protected function deployFunction(): void
    {
        // 关键约束：基于配置传入的单列主键名，用 row_to_json 提取主键并写入审计表。
        $sql = <<<SQL
CREATE OR REPLACE FUNCTION {$this->auditSchema}.{$this->auditFunction}()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
  sch TEXT := '{$this->auditSchema}';
  tbl TEXT := TG_TABLE_NAME;
  pk_col TEXT := TG_ARGV[0];
  pk_val BIGINT;
BEGIN
  -- compute pk value from JSON conversion of NEW or OLD
  IF TG_OP = 'INSERT' THEN
    pk_val := (row_to_json(NEW)->>pk_col)::bigint;
  ELSE
    pk_val := (row_to_json(OLD)->>pk_col)::bigint;
  END IF;

  -- perform logging
  IF TG_OP = 'INSERT' THEN
    EXECUTE format(
      'INSERT INTO %I.%I(action, pk, new_data) VALUES ($1, $2, $3)',
      sch, tbl
    ) USING TG_OP, pk_val, row_to_json(NEW)::jsonb;
    RETURN NEW;

  ELSIF TG_OP = 'UPDATE' THEN
    EXECUTE format(
      'INSERT INTO %I.%I(action, pk, old_data, new_data) VALUES ($1, $2, $3, $4)',
      sch, tbl
    ) USING TG_OP, pk_val, row_to_json(OLD)::jsonb, row_to_json(NEW)::jsonb;
    RETURN NEW;

  ELSE -- DELETE
    EXECUTE format(
      'INSERT INTO %I.%I(action, pk, old_data) VALUES ($1, $2, $3)',
      sch, tbl
    ) USING TG_OP, pk_val, row_to_json(OLD)::jsonb;
    RETURN OLD;

  END IF;
END;
$$;
SQL;
        DB::unprepared($sql);
        $this->info('Deployed audit function.');
    }

    protected function resetTriggers(): void
    {
        $schema       = DB::getConfig('schema') ?: 'public';
        $placeholders = implode(',', array_fill(0, count($this->tables), '?'));
        $params       = array_merge([$schema], array_keys($this->tables));
        $rows         = DB::select(
            "SELECT event_object_table AS tbl, trigger_name AS trg
             FROM information_schema.triggers
             WHERE trigger_schema = ? AND event_object_table IN ({$placeholders})",
            $params
        );

        foreach ($rows as $r) {
            DB::statement("DROP TRIGGER IF EXISTS \"{$r->trg}\" ON \"{$r->tbl}\"");
            $this->info("Dropped trigger {$r->trg} on {$r->tbl}");
        }
    }

    protected function createTriggers(): void
    {
        foreach ($this->tables as $table => $pk) {
            $trg  = "trg_{$table}_audit";
            $func = "{$this->auditSchema}.{$this->auditFunction}";
            DB::statement(
                "CREATE TRIGGER \"{$trg}\"
                 AFTER INSERT OR UPDATE OR DELETE ON \"{$table}\"
                 FOR EACH ROW EXECUTE FUNCTION {$func}('{$pk}')"
            );
            $this->info("Created trigger {$trg} on {$table}");
        }
    }

    protected function ensureIndexes(): void
    {
        foreach ($this->tables as $table => $pk) {
            $log = "{$this->auditSchema}.{$table}";
            $idx = "idx_{$table}_pk";
            DB::statement(
                "CREATE INDEX IF NOT EXISTS {$idx} ON {$log} (pk)"
            );
            $this->info("Ensured index on {$log}(pk): {$idx}");
        }
    }
}
