<?php

namespace App\Console\Commands\Sys;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: '_sys:s3-files:clean',
    description: 'Clean up S3 files that are not present in the database'
)]
class S3FilesClean extends Command
{
    protected $signature   = '_sys:s3-files:clean {--dry-run : Only show the files that would be deleted}';
    protected $description = 'Clean up S3 files that are not present in the database';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting S3 cleanup ...');

        // 获取 S3 存储驱动
        $storage = Storage::disk('s3');

        // 获取所有 S3 文件
        $this->info('Retrieving all files from S3...');

        try {
            $s3Files = $storage->allFiles();
            $this->info('Total files found in S3: '.count($s3Files));
        } catch (\Throwable $e) {
            $this->error('Error retrieving files from S3: '.$e->getMessage());

            return 1;
        }

        // 获取数据库中所有文件路径
        $this->info('Retrieving all file paths from the database...');
        $dbFiles = [];

        $entries = $this->getTablesAndColumns();

        foreach ($entries as $entry) {
            $table  = $entry->table_name;
            $column = $entry->column_name;
            $type   = preg_replace('/^.+_/', '', $column);

            try {
                $this->info("Processing table: {$table}, column: {$column}");
                $records = DB::table($table)->select($column)->get();

                foreach ($records as $record) {
                    $columnValue = $record->{$column};

                    if (is_null($columnValue)) {
                        continue;
                    }

                    // 解析 JSON 数据
                    $decoded = json_decode($columnValue, true);

                    if (JSON_ERROR_NONE !== json_last_error()) {
                        $this->warn("Failed to decode JSON in table '{$table}', column '{$column}': ".json_last_error_msg());

                        continue;
                    }

                    // 约定：*_photo/*_file 为单对象，*_photos/*_files 为数组，*_info 取 info_photos；仅收集 path_ 用于比对。
                    switch ($type) {
                        case 'photo':
                        case 'file':
                            $item = $decoded;
                            if (is_array($item) && isset($item['path_'])) {
                                $dbFiles[] = $item['path_'];
                            }

                            break;

                        case 'files':
                        case 'photos':
                            foreach ($decoded as $item) {
                                if (is_array($item) && isset($item['path_'])) {
                                    $dbFiles[] = $item['path_'];
                                }
                            }

                            break;

                        case 'info':
                            foreach ($decoded as $info) {
                                $items = $info['info_photos'] ?? [];
                                if ($items) {
                                    foreach ($items as $item) {
                                        if (is_array($item) && isset($item['path_'])) {
                                            $dbFiles[] = $item['path_'];
                                        }
                                    }
                                }
                            }

                            break;
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Error retrieving files from table '{$table}', column '{$column}': ".$e->getMessage());

                // 继续处理下一个表/列
                continue;
            }
        }

        // 去重
        $dbFiles = array_unique($dbFiles);
        $this->info('Total unique file paths found in DB: '.count($dbFiles));

        // 找出 S3 中存在但数据库中不存在的文件
        $this->info('Comparing S3 files with database records...');
        $filesToDelete = array_diff($s3Files, $dbFiles);
        $this->info('Total files to '.($dryRun ? 'delete (dry run)' : 'delete').': '.count($filesToDelete));

        if (empty($filesToDelete)) {
            $this->info('No files to delete. Cleanup complete.');

            return 0;
        }

        if ($dryRun) {
            $this->info('Dry run enabled. The following files would be deleted:');
            foreach ($filesToDelete as $file) {
                $this->line($file);
            }
            $this->info('Dry run complete. No files were deleted.');

            return 0;
        }

        // 删除不需要的文件
        $this->info('Deleting files from S3...');
        $deletedCount = 0;
        foreach ($filesToDelete as $file) {
            try {
                $storage->delete($file);
                ++$deletedCount;
                $this->line("Deleted: {$file}");
            } catch (\Throwable $e) {
                $this->error("Failed to delete {$file}: ".$e->getMessage());
            }
        }

        $this->info("Cleanup complete. Total files deleted: {$deletedCount}");

        return 0;
    }

    /**
     * Extract all 'path_' values from the decoded JSON data.
     *
     * @param mixed $data
     *
     * @return array
     */
    protected function extractPaths($data)
    {
        $paths = [];

        if (is_array($data)) {
            // 检查是否是关联数组（单个对象）
            if ($this->isAssoc($data)) {
                if (isset($data['path_'])) {
                    $paths[] = $data['path_'];
                }
            } else {
                // 如果是索引数组（对象数组）
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['path_'])) {
                        $paths[] = $item['path_'];
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Determine if an array is associative.
     *
     * @return bool
     */
    protected function isAssoc(array $array)
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 获取需要检查的表和列。
     */
    protected function getTablesAndColumns(): array
    {
        $query = <<<'SQL'
SELECT table_name, column_name
FROM information_schema.COLUMNS
WHERE table_schema = 'public'
  AND (
      column_name LIKE '%_photo'
      OR column_name LIKE '%_photos'
      OR column_name LIKE '%_file'
      OR column_name LIKE '%_files'
      OR column_name LIKE '%_info'
  )
SQL;

        return DB::select($query);
    }
}
